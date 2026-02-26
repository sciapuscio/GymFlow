<?php
/**
 * GymFlow — Gym Portal API (bloques editoriales de portada)
 *
 * GET  /api/gym-portal.php?action=blocks
 *   header: Authorization: Bearer <member_token>
 *   → Devuelve los bloques activos del gym + next_class + membership header data
 *
 * (Sólo lectura — escribir lo hacen los admin via pages/admin/gym-portal.php)
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

// ── GET /blocks ───────────────────────────────────────────────────────────────
if ($action === 'blocks' && $_SERVER['REQUEST_METHOD'] === 'GET') {

    // 1) Portal blocks
    $blocks = [];
    try {
        $bStmt = db()->prepare("
            SELECT id, type, content, caption
            FROM gym_portal_blocks
            WHERE gym_id = ? AND active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $bStmt->execute([$gymId]);
        foreach ($bStmt->fetchAll() as $row) {
            $contentVal = $row['content'];
            // If image URL is relative, make it absolute so Flutter can load it
            if ($row['type'] === 'image' && str_starts_with($contentVal, '/')) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'training.access.ly';
                $contentVal = $scheme . '://' . $host . $contentVal;
            }
            $blocks[] = [
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'content' => $contentVal,
                'caption' => $row['caption'],
            ];
        }
    } catch (\Throwable $e) {
        // Table might not exist yet — return empty blocks, don't crash
        error_log('[GymFlow] gym_portal_blocks query failed: ' . $e->getMessage());
    }

    // 2) Active membership stats (for header banner)
    $membership = null;
    try {
        $msStmt = db()->prepare("
            SELECT mm.end_date, mm.sessions_limit, mm.sessions_used, mp.name AS plan_name
            FROM member_memberships mm
            LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
            WHERE mm.member_id = ? AND mm.gym_id = ?
              AND mm.start_date <= CURDATE() AND mm.end_date >= CURDATE()
            ORDER BY mm.end_date ASC
            LIMIT 1
        ");
        $msStmt->execute([$memberId, $gymId]);
        $ms = $msStmt->fetch();
        if ($ms) {
            $endDate = new DateTime($ms['end_date'], new DateTimeZone(TIMEZONE));
            $today = new DateTime('today', new DateTimeZone(TIMEZONE));
            $daysLeft = (int) $today->diff($endDate)->days;

            $sessionsLimit = $ms['sessions_limit'] !== null ? (int) $ms['sessions_limit'] : null;
            $sessionsUsed = (int) $ms['sessions_used'];
            $credits = $sessionsLimit !== null ? max(0, $sessionsLimit - $sessionsUsed) : null;

            $membership = [
                'end_date' => $ms['end_date'],
                'days_left' => $daysLeft,
                'credits_remaining' => $credits,   // null = unlimited
                'plan_name' => $ms['plan_name'],
            ];
        }
    } catch (\Throwable $e) {
        error_log('[GymFlow] portal membership query failed: ' . $e->getMessage());
    }

    // 3) Next upcoming reservation (próximo turno)
    $nextClass = null;
    try {
        $now = new DateTime('now', new DateTimeZone(TIMEZONE));
        $nowStr = $now->format('Y-m-d H:i:s');
        $ncStmt = db()->prepare("
            SELECT
                r.class_date,
                r.class_time,
                ss.label      AS class_name,
                ss.color,
                u.name        AS instructor_name,
                s.name        AS sala_name
            FROM member_reservations r
            JOIN schedule_slots ss ON ss.id = r.schedule_slot_id
            LEFT JOIN salas s ON s.id = ss.sala_id
            LEFT JOIN users u ON u.id = ss.instructor_id AND u.role = 'instructor'
            WHERE r.member_id = ? AND r.gym_id = ? AND r.status = 'reserved'
              AND CONCAT(r.class_date, ' ', r.class_time) > ?
            ORDER BY r.class_date ASC, r.class_time ASC
            LIMIT 1
        ");
        $ncStmt->execute([$memberId, $gymId, $nowStr]);
        $nc = $ncStmt->fetch();
        if ($nc) {
            $nextClass = [
                'class_date' => $nc['class_date'],
                'class_time' => substr($nc['class_time'], 0, 5), // HH:MM
                'class_name' => $nc['class_name'],
                'color' => $nc['color'],
                'instructor_name' => $nc['instructor_name'],
                'sala_name' => $nc['sala_name'],
            ];
        }
    } catch (\Throwable $e) {
        error_log('[GymFlow] portal next_class query failed: ' . $e->getMessage());
    }

    jsonResponse([
        'blocks' => $blocks,
        'membership' => $membership,
        'next_class' => $nextClass,
    ]);
}

jsonError('Acción no válida', 400);
