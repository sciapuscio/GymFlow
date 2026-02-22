<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (($user['role'] ?? '') !== 'superadmin') {
    jsonResponse(['error' => 'Forbidden'], 403);
}

$data = getBody();
$message = trim($data['message'] ?? '');
$type = in_array($data['type'] ?? '', ['info', 'warning', 'error']) ? $data['type'] : 'info';

if (!$message) {
    jsonResponse(['error' => 'El mensaje no puede estar vacío.'], 422);
}

// Call the sync-server internal endpoint
$payload = json_encode(['message' => $message, 'type' => $type]);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
        'content' => $payload,
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]);

$result = @file_get_contents('http://localhost:3001/internal/broadcast', false, $context);

if ($result === false) {
    // Server may be offline — still return OK to caller, just warn
    jsonResponse(['ok' => false, 'warning' => 'Sync server no disponible. El mensaje no fue enviado en tiempo real.'], 200);
}

$decoded = json_decode($result, true);
jsonResponse(['ok' => true, 'server' => $decoded]);
