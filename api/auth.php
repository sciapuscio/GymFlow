<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/totp.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Login step 1: email + password ────────────────────────────────────────────
if ($method === 'POST' && $action === 'login') {
    $data = getBody();
    $user = login($data['email'] ?? '', $data['password'] ?? '');
    if (!$user)
        jsonError('Credenciales incorrectas', 401);

    // OTP required: return temp token but no session yet
    if (!empty($user['otp_required'])) {
        jsonResponse(['otp_required' => true, 'otp_token' => $user['otp_token']]);
    }

    // Normal login (no OTP)
    setcookie('gf_token', $user['token'], time() + SESSION_LIFETIME, '/', '', false, true);
    jsonResponse($user);
}

// ── Login step 2: verify OTP code ─────────────────────────────────────────────
if ($method === 'POST' && $action === 'otp_verify') {
    $data = getBody();
    $otpTok = trim($data['otp_token'] ?? '');
    $code = trim($data['code'] ?? '');

    if (!$otpTok || !$code)
        jsonError('Datos incompletos', 400);

    $user = verifyOtpLogin($otpTok, $code);
    if (!$user)
        jsonError('Código incorrecto o expirado', 401);

    setcookie('gf_token', $user['token'], time() + SESSION_LIFETIME, '/', '', false, true);
    jsonResponse($user);
}

// ── Logout ────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'logout') {
    $token = getBearerToken() ?? ($_COOKIE['gf_token'] ?? '');
    if ($token)
        logout($token);
    setcookie('gf_token', '', time() - 3600, '/');
    jsonResponse(['success' => true]);
}

// ── Me ────────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'me') {
    $user = requireAuth();
    unset($user['password_hash'], $user['otp_secret']);
    $user['otp_enabled'] = (bool) ($user['otp_enabled'] ?? false);
    jsonResponse($user);
}

// ── First login (tour dismiss) ────────────────────────────────────────────────
if ($method === 'POST' && $action === 'first_login') {
    $user = requireAuth();
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
        ->execute([$user['id']]);
    jsonResponse(['success' => true]);
}

// ── OTP: generate secret + QR for setup ───────────────────────────────────────
if ($method === 'POST' && $action === 'otp_setup') {
    $user = requireAuth();
    $secret = TOTP::generateSecret();
    $qrUrl = TOTP::getQrUrl($secret, $user['email'], defined('APP_NAME') ? APP_NAME : 'GymFlow');

    // Store secret temporarily (not yet enabled — user must confirm with a code)
    db()->prepare("UPDATE users SET otp_secret = ?, otp_enabled = 0 WHERE id = ?")
        ->execute([$secret, $user['id']]);

    jsonResponse(['secret' => $secret, 'qr_url' => $qrUrl]);
}

// ── OTP: confirm setup with first code (enables OTP) ─────────────────────────
if ($method === 'POST' && $action === 'otp_enable') {
    $user = requireAuth();
    $data = getBody();
    $code = trim($data['code'] ?? '');

    $row = db()->prepare("SELECT otp_secret FROM users WHERE id = ?");
    $row->execute([$user['id']]);
    $secret = $row->fetchColumn();

    if (!$secret || !TOTP::verify($secret, $code))
        jsonError('Código incorrecto', 400);

    db()->prepare("UPDATE users SET otp_enabled = 1 WHERE id = ?")
        ->execute([$user['id']]);

    jsonResponse(['ok' => true]);
}

// ── OTP: disable OTP (requires valid current code) ────────────────────────────
if ($method === 'POST' && $action === 'otp_disable') {
    $user = requireAuth();
    $data = getBody();
    $code = trim($data['code'] ?? '');

    $row = db()->prepare("SELECT otp_secret FROM users WHERE id = ?");
    $row->execute([$user['id']]);
    $secret = $row->fetchColumn();

    if (!$secret || !TOTP::verify($secret, $code))
        jsonError('Código incorrecto', 400);

    db()->prepare("UPDATE users SET otp_secret = NULL, otp_enabled = 0 WHERE id = ?")
        ->execute([$user['id']]);

    jsonResponse(['ok' => true]);
}
