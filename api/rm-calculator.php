<?php
/**
 * GymFlow — RM Calculator API
 *
 * GET  /api/rm-calculator.php?session_id=X
 *   → Returns exercises (with reps) from a gym_session's blocks_json
 *
 * GET  /api/rm-calculator.php?action=history&exercise=NAME[&limit=30]
 *   → Returns RM log history for the authenticated member + exercise
 *
 * POST /api/rm-calculator.php
 *   body: { session_id: int|null, entries: [{exercise_name, exercise_id?, weight_kg, reps}] }
 *   → Saves RM log entries (Brzycki formula applied server-side)
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
});

// ── Auth ─────────────────────────────────────────────────────────────────────
$token = getBearerToken();
if (!$token)
    jsonError('No autorizado', 401);

$authStmt = db()->prepare("
    SELECT m.id AS member_id, m.gym_id
    FROM member_auth_tokens mat
    JOIN members m ON m.id = mat.member_id
    WHERE mat.token = ? AND mat.expires_at > NOW() AND m.active = 1
");
$authStmt->execute([$token]);
$auth = $authStmt->fetch();
if (!$auth)
    jsonError('Token inválido o expirado', 401);

$memberId = (int) $auth['member_id'];
$gymId = (int) $auth['gym_id'];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: session exercises ────────────────────────────────────────────────────
if ($method === 'GET' && isset($_GET['session_id'])) {
    $sessionId = (int) $_GET['session_id'];

    $stmt = db()->prepare("
        SELECT id, name, blocks_json
        FROM gym_sessions
        WHERE id = ? AND gym_id = ?
    ");
    $stmt->execute([$sessionId, $gymId]);
    $session = $stmt->fetch();

    if (!$session)
        jsonError('Sesión no encontrada', 404);

    $blocks = json_decode($session['blocks_json'] ?? '[]', true) ?: [];
    $exercises = [];
    $seen = [];

    // Block types that can have weight-based exercises
    $workTypes = ['circuit', 'interval', 'tabata', 'emom', 'series', 'amrap', 'fortime'];

    foreach ($blocks as $block) {
        if (!in_array($block['type'] ?? '', $workTypes))
            continue;
        foreach ($block['exercises'] ?? [] as $ex) {
            $name = trim($ex['name'] ?? '');
            if (!$name || isset($seen[$name]))
                continue;
            // Only include exercises with explicit rep counts
            // (excludes cardio like Jump Rope, Run, duration-only like Plank/Kettlebell 20s)
            if (!isset($ex['reps']) || (int) $ex['reps'] <= 0)
                continue;

            $seen[$name] = true;
            $exercises[] = [
                'id' => $ex['id'] ?? null,
                'name' => $name,
                'reps' => (int) $ex['reps'],
                'block_name' => $block['name'] ?? '',
                'block_type' => $block['type'] ?? '',
            ];
        }
    }

    jsonResponse([
        'session_id' => $sessionId,
        'session_name' => $session['name'],
        'exercises' => $exercises,
    ]);
}

// ── GET: history ──────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'history') {
    $exerciseName = trim($_GET['exercise'] ?? '');
    if (!$exerciseName)
        jsonError('exercise requerido', 400);

    $limit = min(60, max(5, (int) ($_GET['limit'] ?? 30)));

    $stmt = db()->prepare("
        SELECT id, exercise_name, weight_kg, reps, rm_estimated, session_id,
               DATE_FORMAT(logged_at, '%Y-%m-%d') AS date,
               logged_at
        FROM rm_logs
        WHERE member_id = ? AND gym_id = ? AND exercise_name = ?
        ORDER BY logged_at DESC
        LIMIT $limit
    ");
    $stmt->execute([$memberId, $gymId, $exerciseName]);
    $logs = $stmt->fetchAll();

    // Also get PR (personal record)
    $prStmt = db()->prepare("
        SELECT MAX(rm_estimated) AS pr FROM rm_logs
        WHERE member_id = ? AND gym_id = ? AND exercise_name = ?
    ");
    $prStmt->execute([$memberId, $gymId, $exerciseName]);
    $pr = (float) ($prStmt->fetch()['pr'] ?? 0);

    jsonResponse([
        'exercise' => $exerciseName,
        'logs' => array_values(array_reverse($logs)), // chronological for chart
        'pr' => $pr,
    ]);
}

// ── GET: list logged exercises ─────────────────────────────────────────────────
if ($method === 'GET' && $action === 'exercises') {
    $stmt = db()->prepare("
        SELECT exercise_name,
               MAX(rm_estimated) AS pr,
               COUNT(*)          AS total_logs,
               MAX(logged_at)    AS last_logged
        FROM rm_logs
        WHERE member_id = ? AND gym_id = ?
        GROUP BY exercise_name
        ORDER BY last_logged DESC
    ");
    $stmt->execute([$memberId, $gymId]);
    jsonResponse(['exercises' => $stmt->fetchAll()]);
}

// ── GET: WOD history (grouped by session) ────────────────────────────────────
if ($method === 'GET' && $action === 'wod-history') {
    $limit = min(50, max(5, (int) ($_GET['limit'] ?? 30)));

    // Fetch all logs for this member, ordered by session and date
    $stmt = db()->prepare("
        SELECT
            rl.id,
            rl.session_id,
            COALESCE(gs.name, 'Entrada manual') AS session_name,
            DATE_FORMAT(COALESCE(gs.started_at, gs.scheduled_at, rl.logged_at), '%Y-%m-%d') AS session_date,
            rl.exercise_name,
            rl.weight_kg,
            rl.reps,
            rl.rm_estimated,
            DATE_FORMAT(rl.logged_at, '%Y-%m-%d') AS logged_date
        FROM rm_logs rl
        LEFT JOIN gym_sessions gs ON gs.id = rl.session_id
        WHERE rl.member_id = ? AND rl.gym_id = ?
        ORDER BY
            COALESCE(gs.started_at, gs.scheduled_at, rl.logged_at) DESC,
            rl.session_id DESC,
            rl.logged_at DESC
    ");
    $stmt->execute([$memberId, $gymId]);
    $rows = $stmt->fetchAll();

    // Group by session_id (null → 'manual')
    $grouped = [];
    foreach ($rows as $row) {
        $key = $row['session_id'] !== null ? (int) $row['session_id'] : 'manual';
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'session_id' => $row['session_id'] !== null ? (int) $row['session_id'] : null,
                'session_name' => $row['session_name'],
                'session_date' => $row['session_date'],
                'entries' => [],
            ];
        }
        $grouped[$key]['entries'][] = [
            'exercise_name' => $row['exercise_name'],
            'weight_kg' => (float) $row['weight_kg'],
            'reps' => (int) $row['reps'],
            'rm_estimated' => (float) $row['rm_estimated'],
        ];
    }

    // Apply limit (number of WOD groups, not rows)
    $wods = array_values(array_slice($grouped, 0, $limit));

    jsonResponse(['wods' => $wods]);
}

// ── POST: save RM log ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = getBody();
    $sessionId = isset($data['session_id']) ? (int) $data['session_id'] : null;
    $entries = $data['entries'] ?? [];

    if (!is_array($entries) || empty($entries))
        jsonError('entries requerido', 400);

    $insert = db()->prepare("
        INSERT INTO rm_logs
            (member_id, gym_id, session_id, exercise_id, exercise_name, weight_kg, reps, rm_estimated)
        VALUES (?,?,?,?,?,?,?,?)
    ");

    $saved = [];
    foreach ($entries as $entry) {
        $exName = trim($entry['exercise_name'] ?? '');
        $exId = isset($entry['exercise_id']) ? (int) $entry['exercise_id'] : null;
        $weightKg = (float) ($entry['weight_kg'] ?? 0);
        $reps = (int) ($entry['reps'] ?? 0);

        if (!$exName || $weightKg <= 0 || $reps <= 0 || $reps >= 37) {
            continue; // skip invalid (Brzycki is undefined at reps >= 37)
        }

        // Brzycki formula: RM = weight × (36 / (37 - reps))
        $rm = round($weightKg * (36 / (37 - $reps)), 2);

        $insert->execute([$memberId, $gymId, $sessionId, $exId, $exName, $weightKg, $reps, $rm]);
        $saved[] = [
            'exercise_name' => $exName,
            'weight_kg' => $weightKg,
            'reps' => $reps,
            'rm_estimated' => $rm,
        ];
    }

    if (empty($saved))
        jsonError('No se guardó ninguna entrada válida', 400);

    jsonResponse(['ok' => true, 'saved' => $saved, 'count' => count($saved)], 201);
}

jsonError('Acción no válida', 400);
