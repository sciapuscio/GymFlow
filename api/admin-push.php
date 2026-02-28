<?php
/**
 * GymFlow — Admin Push Notification API
 *
 * POST /api/admin-push.php
 *   { title: string, body: string }
 *   → Broadcasts a push notification to all members of the gym with a registered FCM token.
 *   → Returns { sent, failed }
 *
 * GET /api/admin-push.php
 *   → Returns last 20 push_log entries for this gym.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/fcm.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('admin', 'superadmin');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: push history ────────────────────────────────────────────────────────
if ($method === 'GET') {
    // Ensure log table exists
    db()->exec("CREATE TABLE IF NOT EXISTS push_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        gym_id INT UNSIGNED NOT NULL,
        title VARCHAR(100) NOT NULL,
        body TEXT NOT NULL,
        sent INT UNSIGNED NOT NULL DEFAULT 0,
        failed INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_gym (gym_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Device count
    $countStmt = db()->prepare("
        SELECT COUNT(DISTINCT mdt.id) AS total
        FROM member_device_tokens mdt
        JOIN members m ON m.id = mdt.member_id
        WHERE m.gym_id = ?
    ");
    $countStmt->execute([$gymId]);
    $deviceCount = (int) $countStmt->fetchColumn();

    // History
    $hist = db()->prepare("SELECT id, title, body, sent, failed, created_at FROM push_log WHERE gym_id = ? ORDER BY created_at DESC LIMIT 20");
    $hist->execute([$gymId]);

    jsonResponse(['device_count' => $deviceCount, 'history' => $hist->fetchAll()]);
}

// ── POST: broadcast ──────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = getBody();
    $title = trim($data['title'] ?? '');
    $body = trim($data['body'] ?? '');

    if (!$title)
        jsonError('title es requerido', 400);
    if (!$body)
        jsonError('body es requerido', 400);
    if (mb_strlen($title) > 100)
        jsonError('title demasiado largo (máx 100 chars)', 400);
    if (mb_strlen($body) > 500)
        jsonError('body demasiado largo (máx 500 chars)', 400);

    // Load FCM credentials
    try {
        [$sa, $projectId] = loadFcmServiceAccount();
    } catch (\RuntimeException $e) {
        jsonError('FCM no configurado: ' . $e->getMessage(), 503);
    }

    $accessToken = getFcmAccessToken($sa);
    if (!$accessToken)
        jsonError('No se pudo obtener token FCM', 503);

    // Fetch all device tokens for this gym's members
    $tokensStmt = db()->prepare("
        SELECT DISTINCT mdt.fcm_token
        FROM member_device_tokens mdt
        JOIN members m ON m.id = mdt.member_id
        WHERE m.gym_id = ? AND m.active = 1
    ");
    $tokensStmt->execute([$gymId]);
    $tokens = $tokensStmt->fetchAll(\PDO::FETCH_COLUMN);

    if (empty($tokens)) {
        jsonResponse(['sent' => 0, 'failed' => 0, 'message' => 'No hay dispositivos registrados para este gym.']);
    }

    $sent = 0;
    $failed = 0;
    $cleaned = 0;
    // Tokens that FCM reports as invalid → delete from DB
    $staleTokens = ['UNREGISTERED', 'INVALID_ARGUMENT', 'REGISTRATION_TOKEN_NOT_REGISTERED'];

    foreach ($tokens as $token) {
        $result = sendFcmPush($token, $title, $body, $projectId, $accessToken);
        if ($result === 'ok') {
            $sent++;
        } else {
            $failed++;
            if (in_array($result, $staleTokens, true)) {
                db()->prepare("DELETE FROM member_device_tokens WHERE fcm_token = ?")->execute([$token]);
                $cleaned++;
            }
        }
    }

    // Log
    db()->prepare("
        INSERT INTO push_log (gym_id, title, body, sent, failed)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$gymId, $title, $body, $sent, $failed]);

    jsonResponse([
        'sent' => $sent,
        'failed' => $failed,
        'total' => count($tokens),
        'message' => "✅ Enviada a {$sent} dispositivo(s)." . ($failed > 0 ? " ⚠️ {$failed} fallido(s)." : ''),
    ]);
}

jsonError('Método no permitido', 405);
