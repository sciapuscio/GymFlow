<?php
/**
 * GymFlow — Member: change password (after temp PIN login)
 *
 * POST /api/member-change-password.php
 *   header: Authorization: Bearer <token>
 *   body  : { new_password: "..." }
 *
 * Clears temp_pin and must_change_pwd flag.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    jsonError('Método no permitido', 405);

// Auth via Bearer token
$token = getBearerToken();
if (!$token)
    jsonError('No autorizado', 401);

$stmt = db()->prepare("
    SELECT m.id, mat.gym_id
    FROM member_auth_tokens mat
    JOIN members m ON m.id = mat.member_id
    WHERE mat.token = ? AND mat.expires_at > NOW() AND m.active = 1
");
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row)
    jsonError('No autorizado', 401);

$data = getBody();
$newPassword = trim($data['new_password'] ?? '');

if (strlen($newPassword) < 6)
    jsonError('La contraseña debe tener al menos 6 caracteres', 400);

db()->prepare("
    UPDATE members
    SET password_hash  = ?,
        temp_pin       = NULL,
        must_change_pwd = 0
    WHERE id = ?
")->execute([password_hash($newPassword, PASSWORD_DEFAULT), $row['id']]);

jsonResponse(['ok' => true, 'message' => 'Contraseña actualizada correctamente']);
