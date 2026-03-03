<?php
/**
 * GymFlow — Member: change password
 *
 * POST /api/member-change-password.php
 *   header: Authorization: Bearer <token>
 *   body  : { new_password: "...", current_password?: "...", voluntary?: true }
 *
 * - voluntary=true  → verifica contraseña actual antes de cambiar
 * - voluntary=false → flujo de PIN temporal, solo limpia temp_pin y must_change_pwd
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
    SELECT m.id, m.password_hash, mat.gym_id
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
$voluntary = (bool) ($data['voluntary'] ?? false);

if (strlen($newPassword) < 6)
    jsonError('La contraseña debe tener al menos 6 caracteres', 400);

// En flujo voluntario: verificar la contraseña actual
if ($voluntary) {
    $currentPassword = $data['current_password'] ?? '';
    if (!$currentPassword)
        jsonError('Ingresá tu contraseña actual', 400);
    if (!$row['password_hash'] || !password_verify($currentPassword, $row['password_hash']))
        jsonError('La contraseña actual es incorrecta', 403);

    // Solo cambiar la contraseña (no limpiar temp_pin — no aplica)
    db()->prepare("
        UPDATE members SET password_hash = ? WHERE id = ?
    ")->execute([password_hash($newPassword, PASSWORD_DEFAULT), $row['id']]);
} else {
    // Flujo forzado por PIN: limpiar temp_pin y must_change_pwd
    db()->prepare("
        UPDATE members
        SET password_hash   = ?,
            temp_pin        = NULL,
            must_change_pwd = 0
        WHERE id = ?
    ")->execute([password_hash($newPassword, PASSWORD_DEFAULT), $row['id']]);
}

jsonResponse(['ok' => true, 'message' => 'Contraseña actualizada correctamente']);
