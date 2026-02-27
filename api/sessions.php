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
    $user = requireAuth('instructor', 'admin', 'superadmin');
    $data = getBody();
    if (empty($data['name']))
        jsonError('Name required');

    // superadmin uses gym_id from request body; regular users use their session gym_id
    if ($user['role'] === 'superadmin') {
        $gymId = (int) ($data['gym_id'] ?? 0);
        if (!$gymId)
            jsonError('gym_id required — superadmin must pass gym_id in body');
    } else {
        $gymId = (int) $user['gym_id'];
    }

    $blocks = $data['blocks_json'] ?? [];
    $total = computeTemplateTotal($blocks);

    $stmt = db()->prepare(
        "INSERT INTO gym_sessions (gym_id, sala_id, instructor_id, template_id, name, blocks_json, spotify_playlist_uri, scheduled_at, total_duration) 
         VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $gymId,
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
    $user = requireAuth('instructor', 'admin', 'superadmin');
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
            $prepRemaining = max(0, (int) ($data['prep_remaining'] ?? 0));
            $startedAt = $session['started_at'] ?? $now;
            // Save block_resumed_at so server can calculate elapsed in real-time
            db()->prepare("UPDATE gym_sessions SET status='playing', started_at=COALESCE(started_at,?), block_resumed_at=?, updated_at=? WHERE id=?")
                ->execute([$startedAt, $now, $now, $id]);
            break;

        case 'pause':
            // Accumulate elapsed into base before clearing resume timestamp
            if ($session['block_resumed_at']) {
                $resumedTs = strtotime($session['block_resumed_at']);
                $accumulated = (int) $session['current_block_elapsed'] + (time() - $resumedTs);
            } else {
                $accumulated = (int) $session['current_block_elapsed'];
            }
            db()->prepare("UPDATE gym_sessions SET status='paused', current_block_elapsed=?, block_resumed_at=NULL, updated_at=? WHERE id=?")
                ->execute([$accumulated, $now, $id]);
            break;
        case 'stop':
            db()->prepare("UPDATE gym_sessions SET status='finished', finished_at=?, block_resumed_at=NULL, updated_at=? WHERE id=?")
                ->execute([$now, $now, $id]);
            break;
        case 'skip':
            $nextIdx = min($idx + 1, count($blocks) - 1);
            $finished = ($nextIdx >= count($blocks) - 1 && $idx === $nextIdx) ? 'finished' : $session['status'];
            $resumeTs = ($session['status'] === 'playing') ? $now : null;
            db()->prepare("UPDATE gym_sessions SET current_block_index=?, current_block_elapsed=0, block_resumed_at=?, status=?, updated_at=? WHERE id=?")
                ->execute([$nextIdx, $resumeTs, $finished, $now, $id]);
            $idx = $nextIdx;
            break;
        case 'prev':
            $prevIdx = max($idx - 1, 0);
            $resumeTs = ($session['status'] === 'playing') ? $now : null;
            db()->prepare("UPDATE gym_sessions SET current_block_index=?, current_block_elapsed=0, block_resumed_at=?, updated_at=? WHERE id=?")
                ->execute([$prevIdx, $resumeTs, $now, $id]);
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
            $resumeTs = ($session['status'] === 'playing') ? $now : null;
            db()->prepare("UPDATE gym_sessions SET current_block_index=?, current_block_elapsed=0, block_resumed_at=?, updated_at=? WHERE id=?")
                ->execute([$targetIdx, $resumeTs, $now, $id]);
            $idx = $targetIdx;
            break;
        case 'set_sala':
            $newSalaId = !empty($data['sala_id']) ? (int) $data['sala_id'] : null;
            $oldSalaId = (int) $session['sala_id'];
            // Limpiar sala anterior si se está desacoplando
            if ($oldSalaId && $oldSalaId !== $newSalaId) {
                db()->prepare("UPDATE salas SET current_session_id=NULL WHERE id=? AND current_session_id=?")
                    ->execute([$oldSalaId, $id]);
                // Notificar al sync-server para que limpie estado en memoria y avise al display
                @file_get_contents(
                    'http://localhost:3001/internal/detach-sala?sala_id=' . $oldSalaId,
                    false,
                    stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]])
                );
            }
            db()->prepare("UPDATE gym_sessions SET sala_id=? WHERE id=?")->execute([$newSalaId, $id]);
            if ($newSalaId) {
                db()->prepare("UPDATE salas SET current_session_id=? WHERE id=?")->execute([$id, $newSalaId]);
            }
            break;
        case 'update_elapsed':
            // Legacy fallback: only update base elapsed when NOT playing
            // (server now tracks elapsed via block_resumed_at timestamp)
            $prepRemaining = max(0, (int) ($data['prep_remaining'] ?? 0));
            if ($session['status'] !== 'playing') {
                db()->prepare("UPDATE gym_sessions SET current_block_elapsed=?, updated_at=? WHERE id=?")
                    ->execute([(int) ($data['elapsed'] ?? 0), $now, $id]);
            } else {
                // Tylko actualizar updated_at para que SSE dispare
                db()->prepare("UPDATE gym_sessions SET updated_at=? WHERE id=?")->execute([$now, $id]);
            }
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

        // Calculate real-time elapsed using server timestamps
        $baseElapsed = (int) $updated['current_block_elapsed'];
        if ($updated['status'] === 'playing' && !empty($updated['block_resumed_at'])) {
            $resumedTs = strtotime($updated['block_resumed_at']);
            $realtimeElapsed = $baseElapsed + (time() - $resumedTs);
        } else {
            $realtimeElapsed = $baseElapsed;
        }

        $state = [
            'session_id' => $id,
            'session_name' => $updated['name'],
            'status' => $updated['status'],
            'current_block_index' => $ci,
            'current_block' => $blocksDecoded[$ci] ?? null,
            'next_block' => $blocksDecoded[$ci + 1] ?? null,
            'total_blocks' => count($blocksDecoded),
            'elapsed' => $realtimeElapsed,
            'prep_remaining' => $prepRemaining ?? 0,
            'total_duration' => (int) $updated['total_duration'],
            'server_ts' => time(), // client can use this to detect SSE latency
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

    // If blocks changed, tell the sync server to refresh its in-memory state
    if (isset($data['blocks_json'])) {
        @file_get_contents(
            'http://localhost:3001/internal/reload?session_id=' . $id,
            false,
            stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]])
        );
    }

    jsonResponse(['success' => true]);
}

// DELETE
if ($method === 'DELETE' && $id) {
    $user = requireAuth();
    db()->prepare("DELETE FROM gym_sessions WHERE id = ? AND instructor_id = ?")->execute([$id, $user['id']]);
    jsonResponse(['success' => true]);
}
