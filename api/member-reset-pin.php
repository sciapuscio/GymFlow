<?php
/**
 * GymFlow — Staff: set temp PIN for member password reset
 *
 * POST /api/member-reset-pin.php
 *   body: { member_id: X }
 *   Requires admin/staff session cookie.
 *
 * Returns: { pin: "XXXX" }
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('admin', 'superadmin', 'staff');
$gymId = (int) $user['gym_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    jsonError('Método no permitido', 405);

$data = getBody();
$memberId = (int) ($data['member_id'] ?? 0);
if (!$memberId)
    jsonError('member_id requerido', 400);

// Verify member belongs to this gym
$check = db()->prepare("SELECT id FROM members WHERE id = ? AND gym_id = ?");
$check->execute([$memberId, $gymId]);
if (!$check->fetch())
    jsonError('Alumno no encontrado', 404);

// Generate 4-digit PIN
$pin = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

// Store plain PIN + flag (on login we compare plain PIN directly)
db()->prepare("
    UPDATE members
    SET temp_pin = ?, must_change_pwd = 1
    WHERE id = ?
")->execute([$pin, $memberId]);

jsonResponse(['ok' => true, 'pin' => $pin]);
