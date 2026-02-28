<?php
/**
 * GymFlow — Auto-Absent API
 * Called internally by the socket server (setInterval) to mark
 * confirmed reservations as 'absent' when the check-in window has closed.
 *
 * POST /api/auto-absent.php  (loopback only via sync-server)
 * Returns: { marked: N, gym_ids: [...] }
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow loopback calls (from sync-server)
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get gym-level checkin_window from gym_settings (default 15 min if not configured)
// Default window: 15 minutes after start_time has passed → mark absent
// Formula: NOW() > CONCAT(class_date, ' ', start_time) + INTERVAL window MINUTE

$sql = "
    SELECT
        mr.id        AS reservation_id,
        mr.gym_id,
        mr.member_id,
        mr.slot_id,
        mr.class_date
    FROM member_reservations mr
    JOIN schedule_slots ss ON ss.id = mr.slot_id
    LEFT JOIN gym_settings gs
        ON gs.gym_id = mr.gym_id AND gs.setting_key = 'checkin_window_minutes'
    WHERE mr.status = 'confirmed'
      AND mr.class_date = CURDATE()
      AND NOW() > ADDTIME(
              CONCAT(mr.class_date, ' ', ss.start_time),
              SEC_TO_TIME(COALESCE(CAST(gs.setting_value AS UNSIGNED), 15) * 60)
          )
";

$stmt = db()->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo json_encode(['marked' => 0, 'gym_ids' => []]);
    exit;
}

$ids = array_column($rows, 'reservation_id');
$gymIds = array_unique(array_column($rows, 'gym_id'));

// Bulk update to 'absent'
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$upd = db()->prepare("UPDATE member_reservations SET status = 'absent' WHERE id IN ($placeholders)");
$upd->execute($ids);

echo json_encode([
    'marked' => count($ids),
    'gym_ids' => array_values($gymIds),
    'ids' => $ids,
]);
