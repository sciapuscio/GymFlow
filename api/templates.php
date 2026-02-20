<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// GET — list templates
if ($method === 'GET' && !$id) {
    $user = requireAuth();
    $gymId = (int) $user['gym_id'];
    $stmt = db()->prepare(
        "SELECT t.*, u.name as author_name 
         FROM templates t LEFT JOIN users u ON t.created_by = u.id
         WHERE t.gym_id = ? AND (t.created_by = ? OR t.is_shared = 1)
         ORDER BY t.updated_at DESC"
    );
    $stmt->execute([$gymId, $user['id']]);
    jsonResponse($stmt->fetchAll());
}

// GET single
if ($method === 'GET' && $id) {
    $user = requireAuth();
    $stmt = db()->prepare("SELECT t.*, u.name as author_name FROM templates t LEFT JOIN users u ON t.created_by = u.id WHERE t.id = ?");
    $stmt->execute([$id]);
    $tpl = $stmt->fetch();
    if (!$tpl)
        jsonError('Not found', 404);
    jsonResponse($tpl);
}

// POST — create
if ($method === 'POST') {
    $user = requireAuth('admin', 'instructor');
    $data = getBody();
    if (empty($data['name']))
        jsonError('Name required');

    $blocks = $data['blocks_json'] ?? [];
    $total = computeTemplateTotal($blocks);

    $stmt = db()->prepare(
        "INSERT INTO templates (gym_id, created_by, name, description, blocks_json, is_shared, share_mode, tags_json, total_duration, class_level) 
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        (int) $user['gym_id'],
        $user['id'],
        sanitize($data['name']),
        $data['description'] ?? null,
        json_encode($blocks),
        $data['is_shared'] ?? 0,
        $data['share_mode'] ?? 'copy',
        json_encode($data['tags_json'] ?? []),
        $total,
        $data['class_level'] ?? 'mixed',
    ]);
    jsonResponse(['id' => db()->lastInsertId(), 'total_duration' => $total], 201);
}

// PUT — update
if ($method === 'PUT' && $id) {
    $user = requireAuth();
    $data = getBody();
    $allowed = ['name', 'description', 'blocks_json', 'is_shared', 'share_mode', 'tags_json', 'class_level'];
    $fields = $params = [];
    foreach ($allowed as $f) {
        if (isset($data[$f])) {
            $val = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
            $fields[] = "$f = ?";
            $params[] = $val;
        }
    }
    if (isset($data['blocks_json'])) {
        $total = computeTemplateTotal($data['blocks_json']);
        $fields[] = "total_duration = ?";
        $params[] = $total;
        $fields[] = "version = version + 1";
    }
    if (!$fields)
        jsonError('No fields');
    $params[] = $id;
    db()->prepare("UPDATE templates SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    jsonResponse(['success' => true]);
}

// DELETE
if ($method === 'DELETE' && $id) {
    $user = requireAuth();
    db()->prepare("DELETE FROM templates WHERE id = ? AND created_by = ?")->execute([$id, $user['id']]);
    jsonResponse(['success' => true]);
}

// Share template
if ($method === 'POST' && isset($_GET['share']) && $id) {
    $user = requireAuth();
    $data = getBody();
    $stmt = db()->prepare("INSERT INTO shared_items (item_type, item_id, shared_by, target_type, target_id, share_mode, message) VALUES ('template',?,?,?,?,?,?)");
    $stmt->execute([$id, $user['id'], $data['target_type'] ?? 'user', $data['target_id'], $data['share_mode'] ?? 'copy', $data['message'] ?? null]);
    jsonResponse(['success' => true]);
}
