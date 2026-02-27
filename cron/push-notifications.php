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

// ─── Load Firebase Service Account ────────────────────────────────────────────
$serviceAccountPath = __DIR__ . '/../config/firebase-service-account.json';
if (!file_exists($serviceAccountPath)) {
    error_log('[GymFlow Push] firebase-service-account.json not found. Aborting.');
    exit(1);
}
$serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
$projectId = $serviceAccount['project_id'];

// ─── Get OAuth2 access token via JWT ──────────────────────────────────────────
function getFcmAccessToken(array $sa): string
{
    $now = time();
    $header = base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claim = base64url(json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));

    $signInput = "$header.$claim";
    openssl_sign($signInput, $signature, $sa['private_key'], 'SHA256');
    $jwt = "$signInput." . base64url($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['access_token'] ?? '';
}

function base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// ─── Send a single FCM push ───────────────────────────────────────────────────
function sendPush(string $fcmToken, string $title, string $body, string $projectId, string $accessToken): bool
{
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    $payload = json_encode([
        'message' => [
            'token' => $fcmToken,
            'notification' => ['title' => $title, 'body' => $body],
            'android' => ['priority' => 'high'],
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("[GymFlow Push] FCM error $code: $res");
        return false;
    }
    return true;
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

    $ok = sendPush($row['fcm_token'], $title, $body, $projectId, $accessToken);
    if ($ok) {
        // Mark as notified to avoid re-sending
        db()->prepare("UPDATE member_reservations SET notified_30min = 1 WHERE id = ?")
            ->execute([$row['reservation_id']]);
        $sent++;
        echo "[OK] Notified member {$row['member_id']} for class {$row['class_name']} at {$time}\n";
    }
}

echo "[GymFlow Push] Done. Sent: $sent notifications.\n";
