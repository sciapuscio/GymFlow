<?php
/**
 * GymFlow — Member Bookings API
 *
 * GET  /api/member-bookings.php?action=stats
 *   → Estadísticas del alumno: reservas activas, asistencias, ausentes (membresía activa), disponibles
 *
 * GET  /api/member-bookings.php?action=list
 *   → Lista las reservas del alumno (próximas + recientes)
 *
 * POST /api/member-bookings.php?action=book
 *   body: { slot_id: int, class_date: "YYYY-MM-DD" }
 *   → Crea reserva + descuenta sesión de membresía activa
 *
 * DELETE /api/member-bookings.php?action=cancel&slot_id=X&class_date=YYYY-MM-DD
 *   → Cancela reserva + restaura sesión de membresía
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
});

// ── Auth ─────────────────────────────────────────────────────────────────────
$token = getBearerToken();
if (!$token)
    jsonError('No autorizado', 401);

$authStmt = db()->prepare("
    SELECT m.id AS member_id, m.gym_id
    FROM member_auth_tokens mat
    JOIN members m ON m.id = mat.member_id
    WHERE mat.token = ? AND mat.expires_at > NOW() AND m.active = 1
");
$authStmt->execute([$token]);
$auth = $authStmt->fetch();
if (!$auth)
    jsonError('Token inválido o expirado', 401);

$memberId = (int) $auth['member_id'];
$gymId = (int) $auth['gym_id'];

$action = $_GET['action'] ?? '';

// ── STATS ─────────────────────────────────────────────────────────────────────
if ($action === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get active membership period to scope all stats
    $memStmt = db()->prepare("
        SELECT id, sessions_limit, sessions_used, start_date
        FROM member_memberships
        WHERE member_id = ? AND gym_id = ?
          AND start_date <= CURDATE() AND end_date >= CURDATE()
        ORDER BY end_date ASC LIMIT 1
    ");
    $memStmt->execute([$memberId, $gymId]);
    $mem = $memStmt->fetch();
    $membershipStart = $mem ? $mem['start_date'] : '1970-01-01';

    // Active reservations (future or today)
    $s1 = db()->prepare("
        SELECT COUNT(*) AS n FROM member_reservations
        WHERE member_id = ? AND gym_id = ? AND status = 'reserved' AND class_date >= CURDATE()
    ");
    $s1->execute([$memberId, $gymId]);
    $activeReservations = (int) $s1->fetch()['n'];

    // Real attendances (QR check-ins) — scoped to current membership
    $s2 = db()->prepare("
        SELECT COUNT(*) AS n FROM member_attendances
        WHERE member_id = ? AND gym_id = ? AND DATE(checked_in_at) >= ?
    ");
    $s2->execute([$memberId, $gymId, $membershipStart]);
    $attended = (int) $s2->fetch()['n'];

    // Absences: past reserved with no QR check-in — scoped to current membership
    $s3 = db()->prepare("
        SELECT COUNT(*) AS n
        FROM member_reservations r
        LEFT JOIN member_attendances a
            ON a.member_id = r.member_id
            AND a.gym_id   = r.gym_id
            AND DATE(a.checked_in_at) = r.class_date
        WHERE r.member_id = ? AND r.gym_id = ?
          AND r.status    = 'reserved'
          AND r.class_date >= ?
          AND r.class_date < CURDATE()
          AND a.id IS NULL
    ");
    $s3->execute([$memberId, $gymId, $membershipStart]);
    $absent = (int) $s3->fetch()['n'];

    $available = null;
    if ($mem && $mem['sessions_limit'] !== null) {
        $available = max(0, (int) $mem['sessions_limit'] - (int) $mem['sessions_used']);
    }

    jsonResponse([
        'active_reservations' => $activeReservations,
        'attended' => $attended,
        'absent' => $absent,
        'available' => $available, // null = unlimited
    ]);
}

// ── LIST ──────────────────────────────────────────────────────────────────────
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $listStmt = db()->prepare("
        SELECT
            r.id,
            r.class_date,
            r.class_time,
            r.status,
            r.cancel_deadline,
            r.cancelled_at,
            ss.id             AS slot_id,
            ss.label          AS class_name,
            ss.color,
            s.name            AS sala_name,
            u.name            AS instructor_name
        FROM member_reservations r
        JOIN schedule_slots ss ON ss.id = r.schedule_slot_id
        LEFT JOIN salas s ON s.id = ss.sala_id
        LEFT JOIN users u ON u.id = ss.instructor_id AND u.role = 'instructor'
        WHERE r.member_id = ? AND r.gym_id = ?
        ORDER BY
            CASE WHEN r.class_date >= CURDATE() THEN 0 ELSE 1 END ASC,
            r.class_date ASC,
            r.class_time ASC
        LIMIT 100
    ");
    $listStmt->execute([$memberId, $gymId]);
    $rows = $listStmt->fetchAll();
    jsonResponse(['reservations' => array_values($rows)]);
}

// ── Resolve slot_id + class_date for book/cancel ──────────────────────────────
$data = getBody();
$slotId = isset($data['slot_id']) ? (int) $data['slot_id'] : (int) ($_GET['slot_id'] ?? 0);
$classDate = trim($data['class_date'] ?? ($_GET['class_date'] ?? ''));

if (!$slotId)
    jsonError('slot_id requerido', 400);

// Validate slot belongs to this gym
$slotStmt = db()->prepare("
    SELECT id, day_of_week, start_time, end_time, label, capacity
    FROM schedule_slots
    WHERE id = ? AND gym_id = ?
");
$slotStmt->execute([$slotId, $gymId]);
$slot = $slotStmt->fetch();
if (!$slot)
    jsonError('Slot no encontrado', 404);

// Auto-resolve class_date if not provided
if (!$classDate) {
    $today = new DateTime('today', new DateTimeZone(TIMEZONE));
    $slotDay = (int) $slot['day_of_week'];          // 0=Mon..6=Sun (DB)
    $todayDow = (int) $today->format('N') - 1;       // 0=Mon..6=Sun
    $daysAhead = ($slotDay - $todayDow + 7) % 7;
    $classDate = (clone $today)->modify("+{$daysAhead} days")->format('Y-m-d');
}

// Helper: find active membership
function getActiveMembership(int $memberId, int $gymId): ?array
{
    $stmt = db()->prepare("
        SELECT id, sessions_limit, sessions_used
        FROM member_memberships
        WHERE member_id = ? AND gym_id = ?
          AND start_date <= CURDATE() AND end_date >= CURDATE()
        ORDER BY end_date ASC
        LIMIT 1
    ");
    $stmt->execute([$memberId, $gymId]);
    return $stmt->fetch() ?: null;
}

// ── BOOK ─────────────────────────────────────────────────────────────────────
if ($action === 'book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reject booking past classes
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));
    $classDatetime = new DateTime($classDate . ' ' . $slot['start_time'], new DateTimeZone(TIMEZONE));
    if ($classDatetime <= $now) {
        jsonError('No podés reservar una clase que ya pasó.', 409);
    }

    // Check capacity
    if ($slot['capacity'] !== null) {
        $countStmt = db()->prepare("
            SELECT COUNT(*) AS cnt
            FROM member_reservations
            WHERE schedule_slot_id = ? AND class_date = ? AND status = 'reserved'
        ");
        $countStmt->execute([$slotId, $classDate]);
        $booked = (int) $countStmt->fetch()['cnt'];
        if ($booked >= (int) $slot['capacity']) {
            jsonError('No hay lugares disponibles para esta clase.', 409);
        }
    }

    // Check session balance
    $membership = getActiveMembership($memberId, $gymId);
    if ($membership && $membership['sessions_limit'] !== null) {
        $remaining = (int) $membership['sessions_limit'] - (int) $membership['sessions_used'];
        if ($remaining <= 0) {
            jsonError('No tenés clases disponibles en tu membresía actual.', 409);
        }
    }

    // Check duplicate
    $dupStmt = db()->prepare("
        SELECT id, status FROM member_reservations
        WHERE member_id = ? AND schedule_slot_id = ? AND class_date = ?
    ");
    $dupStmt->execute([$memberId, $slotId, $classDate]);
    $existing = $dupStmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'reserved') {
            jsonError('Ya tenés una reserva para esta clase.', 409);
        }
        if ($existing['status'] === 'present') {
            jsonError('Ya registraste presencia en esta clase.', 409);
        }
        // Re-activate cancelled booking + deduct session
        db()->prepare("
            UPDATE member_reservations
            SET status = 'reserved', cancelled_at = NULL, cancel_reason = NULL
            WHERE id = ?
        ")->execute([$existing['id']]);

        if ($membership) {
            db()->prepare("
                UPDATE member_memberships SET sessions_used = sessions_used + 1 WHERE id = ?
            ")->execute([$membership['id']]);
        }
        jsonResponse(['ok' => true, 'message' => 'Reserva reactivada.', 'reservation_id' => (int) $existing['id']]);
    }

    // Compute cancel deadline
    $gymCfg = db()->prepare("SELECT cancel_cutoff_minutes FROM gyms WHERE id = ?");
    $gymCfg->execute([$gymId]);
    $cutoff = (int) ($gymCfg->fetch()['cancel_cutoff_minutes'] ?? 120);

    $classDatetime = new DateTime($classDate . ' ' . $slot['start_time'], new DateTimeZone(TIMEZONE));
    $cancelDeadline = (clone $classDatetime)->modify("-{$cutoff} minutes")->format('Y-m-d H:i:s');

    db()->prepare("
        INSERT INTO member_reservations
            (gym_id, member_id, schedule_slot_id, class_date, class_time, status, cancel_deadline)
        VALUES (?,?,?,?,?,?,?)
    ")->execute([$gymId, $memberId, $slotId, $classDate, $slot['start_time'], 'reserved', $cancelDeadline]);

    $newId = (int) db()->lastInsertId();

    // Deduct session from active membership
    if ($membership) {
        db()->prepare("
            UPDATE member_memberships SET sessions_used = sessions_used + 1 WHERE id = ?
        ")->execute([$membership['id']]);
    }

    jsonResponse(['ok' => true, 'message' => '¡Reserva confirmada! 🎉', 'reservation_id' => $newId, 'class_date' => $classDate], 201);
}

// ── CANCEL ───────────────────────────────────────────────────────────────────
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $resStmt = db()->prepare("
        SELECT id, status, cancel_deadline
        FROM member_reservations
        WHERE member_id = ? AND schedule_slot_id = ? AND class_date = ?
    ");
    $resStmt->execute([$memberId, $slotId, $classDate]);
    $res = $resStmt->fetch();

    if (!$res)
        jsonError('No tenés una reserva para esta clase.', 404);
    if ($res['status'] === 'cancelled' || $res['status'] === 'absent')
        jsonError('La reserva ya fue cancelada o marcada como ausente.', 409);

    // Check if we're past the cancellation deadline
    $now = new DateTime('now', new DateTimeZone(TIMEZONE));
    $deadline = $res['cancel_deadline']
        ? new DateTime($res['cancel_deadline'], new DateTimeZone(TIMEZONE))
        : null;

    $isLate = $deadline !== null && $now > $deadline;

    if ($isLate) {
        // Outside cancellation window → mark as absent, NO session refund
        db()->prepare("
            UPDATE member_reservations
            SET status = 'absent', cancelled_at = NOW()
            WHERE id = ?
        ")->execute([$res['id']]);

        jsonResponse([
            'ok' => true,
            'absent' => true,
            'message' => 'Cancelación tardía: quedaste registrado como ausente. No se restauró tu crédito.',
        ]);
    }

    // Within window → normal cancel + session refund
    db()->prepare("
        UPDATE member_reservations
        SET status = 'cancelled', cancelled_at = NOW()
        WHERE id = ?
    ")->execute([$res['id']]);

    $membership = getActiveMembership($memberId, $gymId);
    if ($membership && $membership['sessions_used'] > 0) {
        db()->prepare("
            UPDATE member_memberships SET sessions_used = sessions_used - 1 WHERE id = ?
        ")->execute([$membership['id']]);
    }

    jsonResponse(['ok' => true, 'absent' => false, 'message' => 'Reserva cancelada. Tu crédito fue restaurado.']);
}

jsonError('Acción no válida', 400);
