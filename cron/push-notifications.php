<?php
/**
 * GymFlow — Push Notification Cron
 *
 * Runs every 5 minutes via Task Scheduler.
 * Finds reservations starting in 25–35 min and sends FCM push to member's device.
 *
 * Usage:
 *   php C:\xampp\htdocs\Training\cron\push-notifications.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/fcm.php';

// ─── Load Firebase Service Account ────────────────────────────────────────────
try {
    [$serviceAccount, $projectId] = loadFcmServiceAccount();
} catch (\RuntimeException $e) {
    error_log('[GymFlow Push] ' . $e->getMessage());
    exit(1);
}

// ─── Main ─────────────────────────────────────────────────────────────────────
$accessToken = getFcmAccessToken($serviceAccount);
if (!$accessToken) {
    error_log('[GymFlow Push] Could not get FCM access token. Aborting.');
    exit(1);
}

// Find reservations whose class starts in 25–35 minutes (window to avoid double-send)
$reservations = db()->prepare("
    SELECT
        mr.id           AS reservation_id,
        mr.member_id,
        mr.class_date,
        mr.class_time,
        ss.label        AS class_name,
        mdt.fcm_token,
        m.name          AS member_name
    FROM member_reservations mr
    JOIN schedule_slots ss   ON ss.id = mr.schedule_slot_id
    JOIN members m           ON m.id  = mr.member_id
    JOIN member_device_tokens mdt ON mdt.member_id = mr.member_id
    WHERE mr.status = 'reserved'
      AND mr.notified_30min = 0
      AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(mr.class_date, ' ', mr.class_time)) BETWEEN 25 AND 35
");
$reservations->execute();
$rows = $reservations->fetchAll();

$sent = 0;
foreach ($rows as $row) {
    $time = date('H:i', strtotime($row['class_time']));
    $title = "¡Tu clase empieza en 30 min! 🏋️";
    $body = "{$row['class_name']} — {$time}hs. ¡No llegues tarde!";

    $staleTokens = ['UNREGISTERED', 'INVALID_ARGUMENT', 'REGISTRATION_TOKEN_NOT_REGISTERED'];
    $result = sendFcmPush($row['fcm_token'], $title, $body, $projectId, $accessToken);
    if ($result === 'ok') {
        // Mark as notified to avoid re-sending
        db()->prepare("UPDATE member_reservations SET notified_30min = 1 WHERE id = ?")
            ->execute([$row['reservation_id']]);
        $sent++;
        echo "[OK] Notified member {$row['member_id']} for class {$row['class_name']} at {$time}\n";
    }
}

echo "[GymFlow Push] Done. Sent: $sent notifications.\n";
