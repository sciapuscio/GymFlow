<?php
/**
 * GymFlow — Save Member Sede Preference
 * PATCH /api/member-sede-preference.php
 * Body: { sede_id: N|null }
 * Saves the member's preferred sede to members.sede_id_preferred
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PATCH', 'PUT'])) {
    jsonError('Método no permitido', 405);
}

$token = getBearerToken();
if (!$token)
    jsonError('No autorizado', 401);

$stmt = db()->prepare("
    SELECT m.id AS member_id, m.gym_id
    FROM member_auth_tokens mat
    JOIN members m ON m.id = mat.member_id
    WHERE mat.token = ? AND mat.expires_at > NOW() AND m.active = 1
");
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row)
    jsonError('Token inválido o expirado', 401);

$memberId = (int) $row['member_id'];

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$sedeId = isset($body['sede_id']) && $body['sede_id'] !== null
    ? (int) $body['sede_id']
    : null;

db()->prepare("UPDATE members SET sede_id_preferred = ? WHERE id = ?")
    ->execute([$sedeId, $memberId]);

jsonResponse(['ok' => true, 'sede_id_preferred' => $sedeId]);
