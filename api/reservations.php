<?php
/**
 * GymFlow — Admin Reservations API
 * Session-based auth (admin / instructor / superadmin).
 *
 * PUT /api/reservations.php?id=X
 *   body: { status: 'attended' | 'absent' | 'reserved' | 'cancelled' }
 *   → Updates the status of a reservation (for admin manual marking).
 *
 * GET /api/reservations.php?slot_id=X&class_date=YYYY-MM-DD
 *   → Returns roster for a class instance.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('admin', 'instructor', 'superadmin', 'staff');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET: roster by slot + date ────────────────────────────────────────────────
if ($method === 'GET') {
    $slotId = isset($_GET['slot_id']) ? (int) $_GET['slot_id'] : 0;
    $classDate = $_GET['class_date'] ?? '';
    if (!$slotId || !$classDate)
        jsonError('slot_id y class_date son requeridos', 400);

    $stmt = db()->prepare("
        SELECT mr.id, mr.status,
               m.id AS member_id, m.name AS member_name, m.phone
        FROM member_reservations mr
        JOIN members m ON m.id = mr.member_id
        WHERE mr.schedule_slot_id = ? AND mr.class_date = ? AND mr.gym_id = ?
        ORDER BY mr.status, m.name
    ");
    $stmt->execute([$slotId, $classDate, $gymId]);
    jsonResponse($stmt->fetchAll());
}

// ── PUT: update status ────────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $data = getBody();
    $status = $data['status'] ?? '';
    $allowed = ['attended', 'absent', 'reserved', 'cancelled'];
    if (!in_array($status, $allowed, true))
        jsonError('Status inválido. Valores: ' . implode(', ', $allowed), 400);

    // Verify reservation belongs to this gym
    $check = db()->prepare("SELECT id FROM member_reservations WHERE id = ? AND gym_id = ?");
    $check->execute([$id, $gymId]);
    if (!$check->fetch())
        jsonError('Reserva no encontrada', 404);

    db()->prepare("UPDATE member_reservations SET status = ? WHERE id = ?")
        ->execute([$status, $id]);

    jsonResponse(['success' => true, 'id' => $id, 'status' => $status]);
}

jsonError('Método no permitido', 405);
