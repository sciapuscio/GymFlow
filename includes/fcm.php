<?php
/**
 * GymFlow — FCM Helper
 * Shared functions for Firebase Cloud Messaging via FCM HTTP v1 API.
 * Used by: cron/push-notifications.php, api/admin-push.php
 */

function base64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Exchange a Google service account for an OAuth2 access token.
 * @param array $sa  Decoded firebase-service-account.json
 */
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

/**
 * Send a single FCM push notification.
 * @return string  'ok' on success, FCM error code string on failure (e.g. 'UNREGISTERED')
 */
function sendFcmPush(string $fcmToken, string $title, string $body, string $projectId, string $accessToken): string
{
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    $payload = json_encode([
        'message' => [
            'token' => $fcmToken,
            'notification' => ['title' => $title, 'body' => $body],
            'android' => ['priority' => 'high'],
            'apns' => ['headers' => ['apns-priority' => '10']],
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

    if ($code === 200)
        return 'ok';

    // Extract FCM error code for caller to handle
    $decoded = json_decode($res, true);
    $fcmError = $decoded['error']['details'][0]['errorCode']
        ?? $decoded['error']['status']
        ?? 'UNKNOWN';
    error_log("[GymFlow FCM] HTTP $code | $fcmError | token: " . substr($fcmToken, 0, 20) . '…');
    return $fcmError;
}

/**
 * Load the service account from the standard path.
 * Returns [serviceAccount, projectId] or throws.
 */
function loadFcmServiceAccount(): array
{
    $path = __DIR__ . '/../config/firebase-service-account.json';
    if (!file_exists($path)) {
        throw new \RuntimeException('firebase-service-account.json not found');
    }
    $sa = json_decode(file_get_contents($path), true);
    return [$sa, $sa['project_id']];
}
