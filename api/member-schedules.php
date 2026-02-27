<?php
/**
 * GymFlow — Member Schedules API (para app mobile Flutter)
 *
 * GET /api/member-schedules.php
 *   header: Authorization: Bearer <member_token>
 *   → Devuelve el horario semanal del gym + estado de reserva del alumno.
 *
 * Cada slot incluye:
 *   id, day_of_week, start_time, end_time, class_name, sala_name,
 *   instructor_name, color, capacity, next_date,
 *   booked_count, my_reservation (null | {id, class_date, status})
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

// ── Auth ─────────────────────────────────────────────────────────────────────
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

$gymId = (int) $row['gym_id'];
$memberId = (int) $row['member_id'];

// ── Check if capacity column exists (migration may not be run yet) ─────────────
$hasCapacity = false;
try {
    $colCheck = db()->prepare("SHOW COLUMNS FROM schedule_slots LIKE 'capacity'");
    $colCheck->execute();
    $hasCapacity = (bool) $colCheck->fetch();
} catch (\Throwable $e) {
    $hasCapacity = false;
}

// ── Fetch all slots for the gym ───────────────────────────────────────────────
$capacitySql = $hasCapacity ? 'ss.capacity,' : 'NULL AS capacity,';
$slotsStmt = db()->prepare("
    SELECT
        ss.id,
        ss.day_of_week,
        ss.start_time,
        ss.end_time,
        ss.label         AS class_name,
        ss.color,
        {$capacitySql}
        s.name           AS sala_name,
        u.name           AS instructor_name
    FROM schedule_slots ss
    LEFT JOIN salas s ON s.id = ss.sala_id
    LEFT JOIN users u ON u.id = ss.instructor_id AND u.role = 'instructor'
    WHERE ss.gym_id = ?
    ORDER BY ss.day_of_week, ss.start_time
");
$slotsStmt->execute([$gymId]);
$rawSlots = $slotsStmt->fetchAll();


// ── Compute base date for the requested week ──────────────────────────────────
// If week_start is provided (YYYY-MM-DD, must be a Monday), use it.
// Otherwise fall back to the Monday of the current week.
$weekStartParam = trim($_GET['week_start'] ?? '');
$baseMonday = null;
if ($weekStartParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartParam)) {
    try {
        $baseMonday = new DateTime($weekStartParam, new DateTimeZone(TIMEZONE));
        // Ensure it's really a Monday (weekday N=1); if not, snap to Monday
        if ((int) $baseMonday->format('N') !== 1) {
            $baseMonday->modify('last monday');
        }
    } catch (\Throwable $e) {
        $baseMonday = null;
    }
}
if (!$baseMonday) {
    $today = new DateTime('today', new DateTimeZone(TIMEZONE));
    $todayDow = (int) $today->format('N'); // 1=Mon..7=Sun
    $baseMonday = (clone $today)->modify('-' . ($todayDow - 1) . ' days');
}

$enrichedSlots = [];
foreach ($rawSlots as $slot) {
    $slotId = (int) $slot['id'];
    $dow = (int) $slot['day_of_week']; // 0=Mon..6=Sun

    // Compute the date of this weekday within the target week.
    // $baseMonday is Monday (dow=0), so we add $dow days.
    $slotDate = (clone $baseMonday)->modify("+{$dow} days");
    $nextDate = $slotDate->format('Y-m-d');

    // Enrichment: booking counts + member reservation (fail-safe)
    $bookedCount = 0;
    $myRes = null;
    try {
        $cntStmt = db()->prepare("
            SELECT COUNT(*) AS cnt
            FROM member_reservations
            WHERE schedule_slot_id = ? AND class_date = ? AND status IN ('reserved','present')
        ");
        $cntStmt->execute([$slotId, $nextDate]);
        $bookedCount = (int) $cntStmt->fetch()['cnt'];

        $myStmt = db()->prepare("
            SELECT id, class_date, status
            FROM member_reservations
            WHERE member_id = ? AND schedule_slot_id = ? AND class_date = ?
        ");
        $myStmt->execute([$memberId, $slotId, $nextDate]);
        $myRes = $myStmt->fetch() ?: null;
    } catch (\Throwable $e) {
        error_log('[GymFlow] schedules enrichment failed: ' . $e->getMessage());
    }

    $enrichedSlots[] = [
        'id' => $slotId,
        'day_of_week' => $dow,
        'start_time' => $slot['start_time'],
        'end_time' => $slot['end_time'],
        'class_name' => $slot['class_name'],
        'color' => $slot['color'],
        'capacity' => $slot['capacity'] !== null ? (int) $slot['capacity'] : null,
        'sala_name' => $slot['sala_name'],
        'instructor_name' => $slot['instructor_name'],
        'next_date' => $nextDate,
        'booked_count' => $bookedCount,
        'my_reservation' => $myRes ? [
            'id' => (int) $myRes['id'],
            'class_date' => $myRes['class_date'],
            'status' => $myRes['status'],
        ] : null,
    ];
}

// ── Gym branding ──────────────────────────────────────────────────────────────
$gymStmt = db()->prepare("SELECT name, primary_color, secondary_color, logo_path, slug FROM gyms WHERE id = ?");
$gymStmt->execute([$gymId]);
$gymData = $gymStmt->fetch();

jsonResponse([
    'gym' => $gymData ?: null,
    'slots' => $enrichedSlots,
]);
