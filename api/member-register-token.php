<?php
/**
 * GymFlow — Register FCM device token
 *
 * POST /api/member-register-token.php
 *   Authorization: Bearer <member_token>
 *   { fcm_token: "...", platform: "android" }
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

// Authenticate member
$token = getBearerToken();
if (!$token)
    jsonError('No autorizado', 401);

$stmt = db()->prepare("
    SELECT mat.member_id
    FROM member_auth_tokens mat
    WHERE mat.token = ? AND mat.expires_at > NOW()
");
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row)
    jsonError('Token inválido o expirado', 401);

$memberId = (int) $row['member_id'];
$body = getBody();
$fcmToken = trim($body['fcm_token'] ?? '');
$platform = trim($body['platform'] ?? 'android');

if (!$fcmToken)
    jsonError('fcm_token requerido', 400);

// Upsert: one row per (member, token) pair, update timestamp on conflict
db()->prepare("
    INSERT INTO member_device_tokens (member_id, fcm_token, platform)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE platform = VALUES(platform), updated_at = NOW()
")->execute([$memberId, $fcmToken, $platform]);

jsonResponse(['ok' => true, 'message' => 'Token registrado']);
