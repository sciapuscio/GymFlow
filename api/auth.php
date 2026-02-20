<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'login') {
    $data = getBody();
    $user = login($data['email'] ?? '', $data['password'] ?? '');
    if (!$user)
        jsonError('Credenciales incorrectas', 401);

    // Set cookie too
    setcookie('gf_token', $user['token'], time() + SESSION_LIFETIME, '/', '', false, true);
    jsonResponse($user);
}

if ($method === 'POST' && $action === 'logout') {
    $token = getBearerToken() ?? ($_COOKIE['gf_token'] ?? '');
    if ($token)
        logout($token);
    setcookie('gf_token', '', time() - 3600, '/');
    jsonResponse(['success' => true]);
}

if ($method === 'GET' && $action === 'me') {
    $user = requireAuth();
    unset($user['password_hash']);
    jsonResponse($user);
}
