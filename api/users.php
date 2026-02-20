<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$gymId = isset($_GET['gym_id']) ? (int) $_GET['gym_id'] : null;

// GET — list users
if ($method === 'GET') {
    $user = requireAuth('superadmin', 'admin');
    if ($user['role'] === 'admin')
        $gymId = (int) $user['gym_id'];
    $where = $gymId ? "WHERE u.gym_id = ?" : "";
    $params = $gymId ? [$gymId] : [];
    $stmt = db()->prepare("SELECT u.id, u.name, u.email, u.role, u.active, u.last_login, u.gym_id, g.name as gym_name FROM users u LEFT JOIN gyms g ON u.gym_id = g.id $where ORDER BY u.role, u.name");
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

// POST — create user
if ($method === 'POST') {
    $user = requireAuth('superadmin', 'admin');
    $data = getBody();
    if (empty($data['email']) || empty($data['password']))
        jsonError('Email and password required');
    $targetGymId = $user['role'] === 'superadmin' ? ($data['gym_id'] ?? null) : $user['gym_id'];
    $stmt = db()->prepare("INSERT INTO users (gym_id, name, email, password_hash, role) VALUES (?,?,?,?,?)");
    $stmt->execute([$targetGymId, sanitize($data['name'] ?? ''), strtolower(trim($data['email'])), password_hash($data['password'], PASSWORD_BCRYPT), $data['role'] ?? 'instructor']);
    $newId = db()->lastInsertId();
    if (($data['role'] ?? '') === 'instructor')
        db()->prepare("INSERT INTO instructor_profiles (user_id) VALUES (?)")->execute([$newId]);
    jsonResponse(['id' => $newId], 201);
}

// PUT — update user
if ($method === 'PUT' && isset($_GET['id'])) {
    $user = requireAuth('superadmin', 'admin');
    $id = (int) $_GET['id'];
    $data = getBody();
    $fields = $params = [];
    if (isset($data['name'])) {
        $fields[] = "name=?";
        $params[] = sanitize($data['name']);
    }
    if (isset($data['active'])) {
        $fields[] = "active=?";
        $params[] = $data['active'];
    }
    if (isset($data['role']) && $user['role'] === 'superadmin') {
        $fields[] = "role=?";
        $params[] = $data['role'];
    }
    if (isset($data['password'])) {
        $fields[] = "password_hash=?";
        $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
    }
    if (!$fields)
        jsonError('No fields');
    $params[] = $id;
    db()->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id=?")->execute($params);
    jsonResponse(['success' => true]);
}

if ($method === 'DELETE' && isset($_GET['id'])) {
    $user = requireAuth('superadmin', 'admin');
    db()->prepare("UPDATE users SET active=0 WHERE id=?")->execute([(int) $_GET['id']]);
    jsonResponse(['success' => true]);
}
