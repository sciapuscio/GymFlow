<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// GET — list sessions for instructor
if ($method === 'GET' && !$id) {
    $user = requireAuth();
    $gymId = (int) $user['gym_id'];
    $salaId = isset($_GET['sala_id']) ? (int) $_GET['sala_id'] : null;

    $where = ["gs.gym_id = ?"];
    $params = [$gymId];
    if ($user['role'] === 'instructor') {
        $where[] = "gs.instructor_id = ?";
        $params[] = $user['id'];
    }
    if ($salaId) {
        $where[] = "gs.sala_id = ?";
        $params[] = $salaId;
    }

    $stmt = db()->prepare(
        "SELECT gs.id, gs.name, gs.status, gs.sala_id, gs.scheduled_at, gs.started_at, gs.total_duration,
                gs.current_block_index, u.name as instructor_name, s.name as sala_name, gs.created_at
         FROM gym_sessions gs 
         LEFT JOIN users u ON gs.instructor_id = u.id
         LEFT JOIN salas s ON gs.sala_id = s.id
         WHERE " . implode(' AND ', $where) . " ORDER BY gs.created_at DESC LIMIT 50"
    );
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

// GET single with full blocks
if ($method === 'GET' && $id) {
    $user = requireAuth();
    $stmt = db()->prepare(
        "SELECT gs.*, u.name as instructor_name, s.name as sala_name, s.display_code,
                g.primary_color, g.secondary_color, g.font_display
         FROM gym_sessions gs 
         JOIN users u ON gs.instructor_id = u.id
         LEFT JOIN salas s ON gs.sala_id = s.id
         LEFT JOIN gyms g ON gs.gym_id = g.id
         WHERE gs.id = ?"
    );
    $stmt->execute([$id]);
    $session = $stmt->fetch();
    if (!$session)
        jsonError('Session not found', 404);
    $session['blocks_json'] = json_decode($session['blocks_json'], true);
    jsonResponse($session);
}

// POST — create session
if ($method === 'POST' && !isset($_GET['action'])) {
    $user = requireAuth('instructor', 'admin');
    $data = getBody();
    if (empty($data['name']))
        jsonError('Name required');

    $blocks = $data['blocks_json'] ?? [];
    $total = computeTemplateTotal($blocks);

    $stmt = db()->prepare(
        "INSERT INTO gym_sessions (gym_id, sala_id, instructor_id, template_id, name, blocks_json, spotify_playlist_uri, scheduled_at, total_duration) 
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        (int) $user['gym_id'],
        $data['sala_id'] ?? null,
        $user['id'],
        $data['template_id'] ?? null,
        sanitize($data['name']),
        json_encode($blocks),
        $data['spotify_playlist_uri'] ?? null,
        $data['scheduled_at'] ?? null,
        $total,
    ]);
    $newId = (int) db()->lastInsertId();
    jsonResponse(['id' => $newId, 'total_duration' => $total], 201);
}

// POST — control actions: play, pause, stop, skip, extend, swap
if ($method === 'POST' && isset($_GET['action']) && $id) {
    $user = requireAuth('instructor', 'admin');
    $action = $_GET['action'];
    $data = getBody();

    $stmt = db()->prepare("SELECT * FROM gym_sessions WHERE id = ?");
    $stmt->execute([$id]);
    $session = $stmt->fetch();
    if (!$session)
        jsonError('Session not found', 404);

    $blocks = json_decode($session['blocks_json'], true);
    $idx = (int) $session['current_block_index'];
    $now = date('Y-m-d H:i:s');

    switch ($action) {
        case 'play':
            $startedAt = $session['started_at'] ?? $now;
            db()->prepare("UPDATE gym_sessions SET status='playing', started_at=COALESCE(started_at,?), updated_at=? WHERE id=?")
                ->execute([$startedAt, $now, $id]);
            break;
        case 'pause':
            db()->prepare("UPDATE gym_sessions SET status='paused', updated_at=? WHERE id=?")->execute([$now, $id]);
            break;
        case 'stop':
            db()->prepare("UPDATE gym_sessions SET status='finished', finished_at=?, updated_at=? WHERE id=?")->execute([$now, $now, $id]);
            break;
        case 'skip':
            $nextIdx = min($idx + 1, count($blocks) - 1);
            $finished = ($nextIdx >= count($blocks) - 1 && $idx === $nextIdx) ? 'finished' : $session['status'];
            db()->prepare("UPDATE gym_sessions SET current_block_index=?, current_block_elapsed=0, status=?, updated_at=? WHERE id=?")
                ->execute([$nextIdx, $finished, $now, $id]);
            $idx = $nextIdx;
            break;
        case 'prev':
            $prevIdx = max($idx - 1, 0);
            db()->prepare("UPDATE gym_sessions SET current_block_index=?, current_block_elapsed=0, updated_at=? WHERE id=?")->execute([$prevIdx, $now, $id]);
            $idx = $prevIdx;
            break;
        case 'extend':
            $secs = (int) ($data['seconds'] ?? 30);
            if (isset($blocks[$idx]['config']['duration'])) {
                $blocks[$idx]['config']['duration'] += $secs;
                db()->prepare("UPDATE gym_sessions SET blocks_json=?, updated_at=? WHERE id=?")->execute([json_encode($blocks), $now, $id]);
            }
            break;
        case 'goto':
            $targetIdx = max(0, min((int) ($data['index'] ?? 0), count($blocks) - 1));
            db()->prepare("UPDATE gym_sessions SET current_block_index=?, current_block_elapsed=0, updated_at=? WHERE id=?")->execute([$targetIdx, $now, $id]);
            $idx = $targetIdx;
            break;
        case 'set_sala':
            $salaId = (int) ($data['sala_id'] ?? 0);
            db()->prepare("UPDATE gym_sessions SET sala_id=? WHERE id=?")->execute([$salaId, $id]);
            db()->prepare("UPDATE salas SET current_session_id=? WHERE id=?")->execute([$id, $salaId]);
            break;
        case 'update_elapsed':
            db()->prepare("UPDATE gym_sessions SET current_block_elapsed=?, updated_at=? WHERE id=?")->execute([(int) ($data['elapsed'] ?? 0), $now, $id]);
            break;
        default:
            jsonError("Unknown action: $action");
    }

    // Refresh session and push sync
    $stmt = db()->prepare("SELECT * FROM gym_sessions WHERE id = ?");
    $stmt->execute([$id]);
    $updated = $stmt->fetch();
    $salaId = (int) $updated['sala_id'];

    if ($salaId) {
        $blocksDecoded = json_decode($updated['blocks_json'], true);
        $ci = (int) $updated['current_block_index'];
        $state = [
            'session_id' => $id,
            'session_name' => $updated['name'],
            'status' => $updated['status'],
            'current_block_index' => $ci,
            'current_block' => $blocksDecoded[$ci] ?? null,
            'next_block' => $blocksDecoded[$ci + 1] ?? null,
            'total_blocks' => count($blocksDecoded),
            'elapsed' => (int) $updated['current_block_elapsed'],
            'total_duration' => (int) $updated['total_duration'],
            'updated_at' => $now,
        ];
        db()->prepare("INSERT INTO sync_state (sala_id, session_id, state_json, updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE session_id=VALUES(session_id), state_json=VALUES(state_json), updated_at=VALUES(updated_at)")
            ->execute([$salaId, $id, json_encode($state), $now]);
        db()->prepare("UPDATE salas SET current_session_id=?, last_sync_at=? WHERE id=?")->execute([$id, $now, $salaId]);
        jsonResponse(['success' => true, 'state' => $state]);
    }
    jsonResponse(['success' => true]);
}

// PUT — update session (rename, change sala, etc.)
if ($method === 'PUT' && $id) {
    $user = requireAuth();
    $data = getBody();
    $allowed = ['name', 'sala_id', 'blocks_json', 'spotify_playlist_uri', 'scheduled_at'];
    $fields = $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
        }
    }
    if (isset($data['blocks_json'])) {
        $total = computeTemplateTotal(is_array($data['blocks_json']) ? $data['blocks_json'] : json_decode($data['blocks_json'], true));
        $fields[] = "total_duration = ?";
        $params[] = $total;
    }
    if (!$fields)
        jsonError('No fields');
    $params[] = $id;
    db()->prepare("UPDATE gym_sessions SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    jsonResponse(['success' => true]);
}

// DELETE
if ($method === 'DELETE' && $id) {
    $user = requireAuth();
    db()->prepare("DELETE FROM gym_sessions WHERE id = ? AND instructor_id = ?")->execute([$id, $user['id']]);
    jsonResponse(['success' => true]);
}
