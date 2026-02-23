<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/totp.php';

function getCurrentUser(): ?array
{
    $token = getBearerToken() ?? (isset($_COOKIE['gf_token']) ? $_COOKIE['gf_token'] : null);
    if (!$token)
        return null;

    $stmt = db()->prepare(
        "SELECT u.*, g.name as gym_name, g.primary_color, g.secondary_color, g.slug as gym_slug,
                g.active as gym_active
         FROM sessions_auth sa 
         JOIN users u ON sa.user_id = u.id 
         LEFT JOIN gyms g ON u.gym_id = g.id
         WHERE sa.token = ? AND sa.expires_at > NOW() AND u.active = 1"
    );
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function getBearerToken(): ?string
{
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim($m[1]);
    }
    return null;
}

function requireAuth(string ...$roles): array
{
    $user = getCurrentUser();
    $isBrowser = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html');

    if (!$user) {
        if ($isBrowser) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }
    if ($roles && !in_array($user['role'], $roles)) {
        if ($isBrowser) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        http_response_code(403);
        die(json_encode(['error' => 'Forbidden']));
    }

    // ── Gym active gate (skipped for superadmin) ─────────────────────────────
    if ($user['role'] !== 'superadmin' && !empty($user['gym_id'])) {
        if (isset($user['gym_active']) && !(int) $user['gym_active']) {
            if ($isBrowser) {
                header('Location: ' . BASE_URL . '/pages/expired.php?reason=gym_inactive');
                exit;
            }
            http_response_code(403);
            die(json_encode(['error' => 'Gym is inactive', 'code' => 'GYM_INACTIVE']));
        }
    }

    // ── Subscription gate (skipped for superadmin) ───────────────────────────
    if ($user['role'] !== 'superadmin' && !empty($user['gym_id'])) {
        $sub = getGymSubscription((int) $user['gym_id']);
        $blocked = !$sub
            || $sub['status'] === 'suspended'
            || ($sub['status'] !== 'active')
            || (!empty($sub['current_period_end']) && $sub['current_period_end'] < date('Y-m-d'));

        if ($blocked) {
            if ($isBrowser) {
                header('Location: ' . BASE_URL . '/pages/expired.php');
                exit;
            }
            http_response_code(402);
            die(json_encode(['error' => 'Subscription expired', 'code' => 'SUBSCRIPTION_EXPIRED']));
        }
    }

    return $user;
}


function requireGymAccess(array $user, int $gymId): void
{
    if ($user['role'] === 'superadmin')
        return;
    if ((int) $user['gym_id'] !== $gymId) {
        http_response_code(403);
        die(json_encode(['error' => 'Access denied to this gym']));
    }
}

function login(string $email, string $password): ?array
{
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Block login if the user's gym is inactive
    if ($user['role'] !== 'superadmin' && !empty($user['gym_id'])) {
        $gymRow = db()->prepare("SELECT active FROM gyms WHERE id = ?");
        $gymRow->execute([$user['gym_id']]);
        $gymActive = $gymRow->fetchColumn();
        if ($gymActive === false || !(int) $gymActive) {
            return ['error' => 'gym_inactive'];
        }
    }

    // ── OTP check: if enabled, don't create a full session yet ───────────────
    if (!empty($user['otp_enabled']) && !empty($user['otp_secret'])) {
        return [
            'otp_required' => true,
            'otp_token' => _signOtpTempToken((int) $user['id']),
        ];
    }

    return _createSession($user);
}

/**
 * Second step of OTP login: verify the 6-digit code and create a full session.
 * Returns the same shape as login() on success, or null on failure.
 */
function verifyOtpLogin(string $otpToken, string $code): ?array
{
    $userId = _verifyOtpTempToken($otpToken);
    if (!$userId)
        return null;

    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND active = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || empty($user['otp_secret']))
        return null;

    if (!TOTP::verify($user['otp_secret'], $code))
        return null;

    return _createSession($user);
}

/** Build a token that signs user_id + expiry with a server-side HMAC. */
function _signOtpTempToken(int $userId): string
{
    $expires = time() + 300; // 5 minutes
    $payload = $userId . ':' . $expires;
    $sig = hash_hmac('sha256', $payload, defined('OTP_HMAC_KEY') ? OTP_HMAC_KEY : 'gymflow_otp_key');
    return base64_encode($payload . ':' . $sig);
}

/** Verify and extract user_id from a temp OTP token. Returns null if invalid/expired. */
function _verifyOtpTempToken(string $token): ?int
{
    $raw = base64_decode($token);
    if ($raw === false)
        return null;
    $parts = explode(':', $raw, 3);
    if (count($parts) !== 3)
        return null;
    [$userId, $expires, $sig] = $parts;
    $payload = $userId . ':' . $expires;
    $expected = hash_hmac('sha256', $payload, defined('OTP_HMAC_KEY') ? OTP_HMAC_KEY : 'gymflow_otp_key');
    if (!hash_equals($expected, $sig))
        return null;
    if ((int) $expires < time())
        return null;
    return (int) $userId;
}

/** Create a full session for a user row and return the auth payload. */
function _createSession(array $user): array
{
    $token = bin2hex(random_bytes(SESSION_TOKEN_BYTES));
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    db()->prepare("INSERT INTO sessions_auth (user_id, token, expires_at) VALUES (?,?,?)")
        ->execute([$user['id'], $token, $expires]);

    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ? AND last_login IS NOT NULL")
        ->execute([$user['id']]);

    unset($user['password_hash'], $user['otp_secret']);
    $user['token'] = $token;
    $user['expires_at'] = $expires;
    return $user;
}

function logout(string $token): void
{
    db()->prepare("DELETE FROM sessions_auth WHERE token = ?")->execute([$token]);
}

function getGymBranding(int $gymId): array
{
    $stmt = db()->prepare("SELECT primary_color, secondary_color, font_family, font_display, logo_path, name FROM gyms WHERE id = ?");
    $stmt->execute([$gymId]);
    return $stmt->fetch() ?: [];
}

function getGymSubscription(int $gymId): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM gym_subscriptions WHERE gym_id = ? LIMIT 1"
    );
    $stmt->execute([$gymId]);
    return $stmt->fetch() ?: null;
}

