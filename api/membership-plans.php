<?php
/**
 * GymFlow CRM — Membership Plans API
 * GET    /api/membership-plans.php       — list active plans
 * POST   /api/membership-plans.php       — create plan
 * PUT    /api/membership-plans.php?id=X  — update plan
 * DELETE /api/membership-plans.php?id=X  — deactivate plan
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('admin', 'superadmin');
$gymId = (int) $user['gym_id'];
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($method === 'GET') {
    $all = isset($_GET['all']); // include inactive
    $stmt = db()->prepare(
        "SELECT * FROM membership_plans WHERE gym_id = ?" . ($all ? '' : ' AND active = 1') . " ORDER BY price ASC"
    );
    $stmt->execute([$gymId]);
    jsonResponse($stmt->fetchAll());
}

if ($method === 'POST') {
    $data = getBody();
    $name = trim($data['name'] ?? '');
    if (!$name)
        jsonError('name required');
    $price = (float) ($data['price'] ?? 0);
    $days = max(1, (int) ($data['duration_days'] ?? 30));

    $stmt = db()->prepare("
        INSERT INTO membership_plans (gym_id, name, description, price, currency, duration_days, sessions_limit)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $gymId,
        $name,
        trim($data['description'] ?? '') ?: null,
        $price,
        strtoupper(trim($data['currency'] ?? 'ARS')),
        $days,
        isset($data['sessions_limit']) && $data['sessions_limit'] !== '' ? (int) $data['sessions_limit'] : null,
    ]);
    jsonResponse(['success' => true, 'id' => db()->lastInsertId()], 201);
}

if ($method === 'PUT' && $id) {
    $data = getBody();
    $allowed = ['name', 'description', 'price', 'currency', 'duration_days', 'sessions_limit', 'active'];
    $fields = $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = $data[$f] === '' ? null : $data[$f];
        }
    }
    if (!$fields)
        jsonError('No fields');
    $params[] = $id;
    $params[] = $gymId;
    db()->prepare("UPDATE membership_plans SET " . implode(', ', $fields) . " WHERE id = ? AND gym_id = ?")
        ->execute($params);
    jsonResponse(['success' => true]);
}

if ($method === 'DELETE' && $id) {
    db()->prepare("UPDATE membership_plans SET active = 0 WHERE id = ? AND gym_id = ?")->execute([$id, $gymId]);
    jsonResponse(['success' => true]);
}

jsonError('Method not allowed', 405);
