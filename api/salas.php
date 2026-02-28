<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/plans.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// GET by display code (public — for Display screen & presence poller)
// Must be checked BEFORE the authenticated endpoints below.
if ($method === 'GET' && isset($_GET['code'])) {
    $stmt = db()->prepare(
        "SELECT s.*, g.primary_color, g.secondary_color, g.font_family, g.font_display,
                g.logo_path as gym_logo, g.name as gym_name, g.slug as gym_slug
         FROM salas s JOIN gyms g ON s.gym_id = g.id WHERE s.display_code = ?"
    );
    $stmt->execute([$_GET['code']]);
    $sala = $stmt->fetch();
    if (!$sala)
        jsonError('Sala not found', 404);
    jsonResponse($sala);
}

// GET all salas (optionally ?gym_id=N)
if ($method === 'GET' && !$id) {
    $user = requireAuth();
    $gymId = isset($_GET['gym_id']) ? (int) $_GET['gym_id'] : null;
    if ($user['role'] !== 'superadmin')
        $gymId = (int) $user['gym_id'];
    $where = $gymId ? "WHERE s.gym_id = ?" : "";
    $params = $gymId ? [$gymId] : [];
    $stmt = db()->prepare(
        "SELECT s.*, g.name as gym_name, 
                (SELECT COUNT(*) FROM gym_sessions gs WHERE gs.sala_id = s.id AND gs.status IN ('playing','paused')) as active_sessions
         FROM salas s JOIN gyms g ON s.gym_id = g.id $where ORDER BY g.name, s.name"
    );
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

// GET single sala
if ($method === 'GET' && $id) {
    $user = requireAuth();
    $stmt = db()->prepare("SELECT s.*, g.primary_color, g.secondary_color, g.font_family, g.font_display, g.logo_path as gym_logo, g.name as gym_name FROM salas s JOIN gyms g ON s.gym_id = g.id WHERE s.id = ?");
    $stmt->execute([$id]);
    $sala = $stmt->fetch();
    if (!$sala)
        jsonError('Sala not found', 404);
    if ($user['role'] !== 'superadmin')
        requireGymAccess($user, $sala['gym_id']);
    jsonResponse($sala);
}

// POST — create sala
if ($method === 'POST') {
    $user = requireAuth('superadmin', 'admin');
    $data = getBody();
    if (empty($data['name']))
        jsonError('Name required');
    $gymId = $user['role'] === 'superadmin' ? (int) ($data['gym_id'] ?? 0) : (int) $user['gym_id'];
    if (!$gymId)
        jsonError('gym_id required');

    // ── Plan limit check (skipped for superadmin) ───────────────────────────
    if ($user['role'] !== 'superadmin') {
        if (!checkSalaLimit($gymId)) {
            $info = getGymPlanInfo($gymId);
            $limit = $info['limits']['salas'] ?? 1;
            jsonResponse([
                'error' => 'Límite de salas alcanzado para tu plan.',
                'code' => 'SALA_LIMIT',
                'limit' => $limit,
                'current' => $info['usage']['salas'] ?? 0,
            ], 403);
        }
    }

    $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($data['name'])));
    $code = substr($code, 0, 6) . '-' . strtoupper(substr(uniqid(), -4));

    $stmt = db()->prepare("INSERT INTO salas (gym_id, sede_id, name, display_code, accent_color, bg_color) VALUES (?,?,?,?,?,?)");
    $stmt$sedeId = !empty($data['sede_id']) ? (int) $data['sede_id'] : null;
    $stmt->execute([$gymId, $sedeId, sanitize($data['name']), $code, $data['accent_color'] ?? null, $data['bg_color'] ?? null]);
    $newId = db()->lastInsertId();
    db()->prepare("INSERT INTO sync_state (sala_id) VALUES (?)")->execute([$newId]);
    jsonResponse(['id' => $newId, 'display_code' => $code], 201);
}

// PUT — update sala
if ($method === 'PUT' && $id) {
    $user = requireAuth('superadmin', 'admin');
    $data = getBody();
    $allowed = ['name', 'accent_color', 'bg_color', 'active', 'equipment_json'];
    $fields = $params = [];
    foreach ($allowed as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
        }
    }
    if (!$fields)
        jsonError('No fields');
    $params[] = $id;
    db()->prepare("UPDATE salas SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

    // If the name changed, notify the display screen in real time
    if (!empty($data['name'])) {
        @file_get_contents(
            'http://localhost:3001/internal/sala-renamed?sala_id=' . $id
            . '&name=' . urlencode($data['name']),
            false,
            stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]])
        );
    }

    jsonResponse(['success' => true]);
}

// DELETE 
if ($method === 'DELETE' && $id) {
    $user = requireAuth('superadmin', 'admin');
    db()->prepare("DELETE FROM salas WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true]);
}
