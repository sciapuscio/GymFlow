<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: return the active notice (any authenticated user) ────────────────
if ($method === 'GET') {
    $stmt = db()->query("SELECT id, message, type, created_at FROM system_notices WHERE active = 1 ORDER BY id DESC LIMIT 1");
    $row = $stmt->fetch();
    jsonResponse($row ?: null);
}

// ── Superadmin only from here ─────────────────────────────────────────────
if (($user['role'] ?? '') !== 'superadmin') {
    jsonResponse(['error' => 'Forbidden'], 403);
}

// ── POST: create new notice ───────────────────────────────────────────────
if ($method === 'POST') {
    $data = getBody();
    $message = trim($data['message'] ?? '');
    $type = in_array($data['type'] ?? '', ['info', 'warning', 'error']) ? $data['type'] : 'warning';

    if (!$message) {
        jsonResponse(['error' => 'El mensaje no puede estar vacío.'], 422);
    }

    db()->prepare(
        "INSERT INTO system_notices (message, type, active, created_by) VALUES (?, ?, 1, ?)"
    )->execute([$message, $type, $user['id']]);

    jsonResponse(['ok' => true, 'id' => (int) db()->lastInsertId()], 201);
}

// ── DELETE: deactivate a notice ───────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id)
        jsonResponse(['error' => 'Missing id'], 422);

    db()->prepare("UPDATE system_notices SET active = 0 WHERE id = ?")->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
