<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// GET all exercises
if ($method === 'GET' && !$id) {
    $user = requireAuth();
    $gymId = (int) ($user['gym_id'] ?? 0);
    $muscle = $_GET['muscle'] ?? null;
    $level = $_GET['level'] ?? null;
    $search = $_GET['q'] ?? null;

    $where = ["(e.is_global = 1 OR e.gym_id = ?)"];
    $params = [$gymId];

    if ($muscle) {
        $where[] = "e.muscle_group = ?";
        $params[] = $muscle;
    }
    if ($level) {
        $where[] = "(e.level = ? OR e.level = 'all')";
        $params[] = $level;
    }
    if ($search) {
        $where[] = "(e.name LIKE ? OR e.name_es LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $stmt = db()->prepare("SELECT e.*, u.name as created_by_name FROM exercises e LEFT JOIN users u ON e.created_by = u.id WHERE " . implode(' AND ', $where) . " AND e.active = 1 ORDER BY e.name");
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

// GET single
if ($method === 'GET' && $id) {
    $user = requireAuth();
    $stmt = db()->prepare("SELECT * FROM exercises WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse($stmt->fetch() ?: [], $stmt->fetch() ? 200 : 404);
}

// Random intelligent generation
if ($method === 'POST' && isset($_GET['random'])) {
    $user = requireAuth();
    $data = getBody();
    $gymId = (int) $user['gym_id'];
    $stmt = db()->prepare("SELECT * FROM exercises WHERE (is_global = 1 OR gym_id = ?) AND active = 1");
    $stmt->execute([$gymId]);
    $exercises = $stmt->fetchAll();
    $result = randomIntelligent($exercises, [
        'level' => $data['level'] ?? 'all',
        'equipment' => $data['equipment'] ?? [],
        'exclude_muscle' => $data['exclude_muscle'] ?? [],
        'count' => (int) ($data['count'] ?? 3),
    ]);
    jsonResponse($result);
}

// POST — create exercise
if ($method === 'POST') {
    $user = requireAuth();
    $data = getBody();
    if (empty($data['name']))
        jsonError('Name required');

    $gymId = $user['role'] === 'superadmin' ? (isset($data['gym_id']) ? (int) $data['gym_id'] : null) : (int) $user['gym_id'];
    $isGlobal = ($user['role'] === 'superadmin' && ($data['is_global'] ?? false)) ? 1 : 0;

    $stmt = db()->prepare(
        "INSERT INTO exercises (gym_id, created_by, name, name_es, muscle_group, equipment, level, tags_json, duration_rec, description, is_global) 
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $gymId,
        $user['id'],
        sanitize($data['name']),
        isset($data['name_es']) ? sanitize($data['name_es']) : null,
        $data['muscle_group'] ?? 'full_body',
        is_array($data['equipment'] ?? null) ? json_encode($data['equipment']) : ($data['equipment'] ?? '[]'),
        $data['level'] ?? 'all',
        is_array($data['tags_json'] ?? null) ? json_encode($data['tags_json']) : '[]',
        $data['duration_rec'] ?? 30,
        $data['description'] ?? null,
        $isGlobal,
    ]);
    jsonResponse(['id' => db()->lastInsertId()], 201);
}

// PUT — update
if ($method === 'PUT' && $id) {
    $user = requireAuth();
    $data = getBody();
    $allowed = ['name', 'name_es', 'muscle_group', 'equipment', 'level', 'tags_json', 'duration_rec', 'description', 'active'];
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
    db()->prepare("UPDATE exercises SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    jsonResponse(['success' => true]);
}

// DELETE
if ($method === 'DELETE' && $id) {
    $user = requireAuth();
    db()->prepare("UPDATE exercises SET active = 0 WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true]);
}
