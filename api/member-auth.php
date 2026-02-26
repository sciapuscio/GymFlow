<?php
/**
 * GymFlow — Member Auth API
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

// Catch any uncaught exception → JSON error
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
    exit;
});

// Catch PHP fatal errors → JSON error
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode(['error' => $err['message'], 'file' => basename($err['file']), 'line' => $err['line']]);
    }
});

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: get authenticated member from Bearer token ──────────────────────
function getAuthMember(): ?array
{
    $token = getBearerToken();
    if (!$token)
        return null;

    $stmt = db()->prepare("
        SELECT m.*, mat.id AS token_id, mat.gym_id
        FROM member_auth_tokens mat
        JOIN members m ON m.id = mat.member_id
        WHERE mat.token = ? AND mat.expires_at > NOW() AND m.active = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

// ── Helper: generate secure token ───────────────────────────────────────────
function generateMemberToken(int $memberId, int $gymId, string $device = ''): string
{
    $token = bin2hex(random_bytes(32)); // 64-char hex
    $expires = date('Y-m-d H:i:s', strtotime('+90 days'));
    db()->prepare("
        INSERT INTO member_auth_tokens (member_id, gym_id, token, device_name, expires_at)
        VALUES (?,?,?,?,?)
    ")->execute([$memberId, $gymId, $token, $device ?: null, $expires]);
    return $token;
}

// ── Helper: build member response ────────────────────────────────────────────
function memberPayload(array $m, int $gymId): array
{
    // Active membership
    $ms = db()->prepare("
        SELECT mm.*, mp.name AS plan_name
        FROM member_memberships mm
        LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
        WHERE mm.member_id = ? AND mm.gym_id = ? AND mm.end_date >= CURDATE()
        ORDER BY mm.end_date DESC LIMIT 1
    ");
    $ms->execute([$m['id'], $gymId]);
    $membership = $ms->fetch();

    // Gym branding — wrapped in try/catch so a missing column never crashes /me
    $gym = null;
    try {
        $gymStmt = db()->prepare("SELECT name, logo_path, primary_color, secondary_color FROM gyms WHERE id = ?");
        $gymStmt->execute([$gymId]);
        $gym = $gymStmt->fetch() ?: null;
    } catch (\Throwable $e) {
        // Column might not exist yet — silently ignore
        error_log('gym branding query failed: ' . $e->getMessage());
    }

    return [
        'id' => (int) $m['id'],
        'name' => $m['name'],
        'email' => $m['email'],
        'phone' => $m['phone'],
        'qr_token' => $m['qr_token'],
        'membership' => $membership ?: null,
        'gym' => $gym ? [
            'name' => $gym['name'],
            'logo_path' => $gym['logo_path'],
            'primary_color' => $gym['primary_color'],
            'secondary_color' => $gym['secondary_color'],
        ] : null,
    ];
}

// ── REGISTER ─────────────────────────────────────────────────────────────────
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getBody();
    $email = trim(strtolower($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $slug = trim($data['gym_slug'] ?? '');

    if (!$email || !$password || !$slug)
        jsonError('email, password y gym_slug requeridos', 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonError('Email inválido', 400);
    if (strlen($password) < 6)
        jsonError('Contraseña de al menos 6 caracteres', 400);

    // Resolve gym
    $gym = db()->prepare("SELECT id FROM gyms WHERE slug = ? AND active = 1");
    $gym->execute([$slug]);
    $gym = $gym->fetch();
    if (!$gym)
        jsonError('Gym no encontrado', 404);
    $gymId = (int) $gym['id'];

    // Find or create member
    $member = db()->prepare("SELECT * FROM members WHERE email = ? AND gym_id = ?");
    $member->execute([$email, $gymId]);
    $member = $member->fetch();

    if ($member) {
        // Update credentials if not set
        if ($member['password_hash'])
            jsonError('El alumno ya tiene cuenta. Usá login.', 409);
        db()->prepare("UPDATE members SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_DEFAULT), $member['id']]);
    } else {
        // Create new member
        $name = trim($data['name'] ?? $email);
        db()->prepare("
            INSERT INTO members (gym_id, name, email, password_hash, qr_token)
            VALUES (?,?,?,?,UUID())
        ")->execute([$gymId, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $member = db()->prepare("SELECT * FROM members WHERE id = ?");
        $member->execute([db()->lastInsertId()]);
        $member = $member->fetch();
    }

    $token = generateMemberToken($member['id'], $gymId, $data['device'] ?? '');
    jsonResponse(['token' => $token, 'member' => memberPayload($member, $gymId)], 201);
}

// ── LOGIN ─────────────────────────────────────────────────────────────────────
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getBody();
    $email = trim(strtolower($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $slug = trim($data['gym_slug'] ?? '');

    if (!$email || !$password || !$slug)
        jsonError('email, password y gym_slug requeridos', 400);

    $gym = db()->prepare("SELECT id FROM gyms WHERE slug = ? AND active = 1");
    $gym->execute([$slug]);
    $gym = $gym->fetch();
    if (!$gym)
        jsonError('Gym no encontrado', 404);
    $gymId = (int) $gym['id'];

    $member = db()->prepare("SELECT * FROM members WHERE email = ? AND gym_id = ? AND active = 1");
    $member->execute([$email, $gymId]);
    $member = $member->fetch();

    if (!$member || !$member['password_hash'] || !password_verify($password, $member['password_hash'])) {
        jsonError('Credenciales incorrectas', 401);
    }

    $token = generateMemberToken($member['id'], $gymId, $data['device'] ?? '');
    jsonResponse(['token' => $token, 'member' => memberPayload($member, $gymId)]);
}

// ── ME ────────────────────────────────────────────────────────────────────────
if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $member = getAuthMember();
    if (!$member)
        jsonError('No autorizado', 401);
    jsonResponse(memberPayload($member, (int) $member['gym_id']));
}

// ── LOGOUT ────────────────────────────────────────────────────────────────────
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($header, 'Bearer ')) {
        $token = trim(substr($header, 7));
        db()->prepare("DELETE FROM member_auth_tokens WHERE token = ?")->execute([$token]);
    }
    jsonResponse(['success' => true]);
}

jsonError('Acción no válida', 400);
