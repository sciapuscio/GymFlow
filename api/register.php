<?php
/**
 * GymFlow — Public Registration Endpoint
 * POST /api/register.php
 * 
 * Creates atomically:
 *   1. Gym
 *   2. Admin user (the owner)
 *   3. Default sala
 *   4. 30-day trial gym_subscription
 *
 * Returns: gym data + auth token so the user is logged in immediately.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

$data = getBody();

// ── Validation ────────────────────────────────────────────────────────────────

$errors = [];

// Gym fields
$gymName = trim($data['gym_name'] ?? '');
$city = trim($data['city'] ?? '');
$phone = trim($data['phone'] ?? '');
$gymType = trim($data['gym_type'] ?? '');

// Admin user fields
$adminName = trim($data['admin_name'] ?? '');
$adminEmail = strtolower(trim($data['admin_email'] ?? ''));
$adminPass = $data['admin_password'] ?? '';
$adminPass2 = $data['admin_password2'] ?? '';

if (empty($gymName))
    $errors['gym_name'] = 'El nombre del gimnasio es requerido.';
if (empty($adminName))
    $errors['admin_name'] = 'Tu nombre es requerido.';
if (empty($adminEmail))
    $errors['admin_email'] = 'El email es requerido.';
elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL))
    $errors['admin_email'] = 'Email inválido.';
if (strlen($adminPass) < 8)
    $errors['admin_password'] = 'La contraseña debe tener al menos 8 caracteres.';
if ($adminPass !== $adminPass2)
    $errors['admin_password2'] = 'Las contraseñas no coinciden.';

if (!empty($errors)) {
    jsonResponse(['error' => 'Validation failed', 'fields' => $errors], 422);
}

// ── Check email uniqueness ─────────────────────────────────────────────────────

$stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Email already registered', 'fields' => ['admin_email' => 'Este email ya está registrado.']], 409);
}

// ── Generate slug (unique) ─────────────────────────────────────────────────────

function makeSlug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return substr($slug, 0, 50);
}

$baseSlug = makeSlug($gymName);
$slug = $baseSlug;
$attempt = 0;
do {
    $s = db()->prepare("SELECT id FROM gyms WHERE slug = ?");
    $s->execute([$slug]);
    if (!$s->fetch())
        break;
    $attempt++;
    $slug = $baseSlug . '-' . $attempt;
} while (true);

// ── Atomic transaction ─────────────────────────────────────────────────────────

try {
    db()->beginTransaction();

    // 1. Create gym
    $stmt = db()->prepare(
        "INSERT INTO gyms (name, slug, primary_color, secondary_color, font_family, font_display, spotify_mode, active)
         VALUES (?, ?, '#e5ff3d', '#6c63ff', 'Inter', 'Outfit', 'disabled', 1)"
    );
    $stmt->execute([sanitize($gymName), $slug]);
    $gymId = (int) db()->lastInsertId();

    // 2. Create admin user (role = admin, linked to this gym)
    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        "INSERT INTO users (gym_id, name, email, password_hash, role, active)
         VALUES (?, ?, ?, ?, 'admin', 1)"
    );
    $stmt->execute([$gymId, sanitize($adminName), $adminEmail, $hash]);
    $userId = (int) db()->lastInsertId();

    // 3. Create instructor_profile for the admin (optional but keeps schema consistent)
    db()->prepare("INSERT INTO instructor_profiles (user_id) VALUES (?)")
        ->execute([$userId]);

    // 4. Create default sala
    $displayCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $gymName), 0, 4));
    if (strlen($displayCode) < 2)
        $displayCode = 'SALA';
    // Ensure display_code uniqueness
    $dcBase = $displayCode . '1';
    $dc = $dcBase;
    $dcAttempt = 2;
    do {
        $s2 = db()->prepare("SELECT id FROM salas WHERE display_code = ?");
        $s2->execute([$dc]);
        if (!$s2->fetch())
            break;
        $dc = $displayCode . $dcAttempt++;
    } while (true);

    $stmt = db()->prepare(
        "INSERT INTO salas (gym_id, name, display_code, active) VALUES (?, 'Sala Principal', ?, 1)"
    );
    $stmt->execute([$gymId, $dc]);

    // 5. Create 30-day trial subscription
    $trialEnd = date('Y-m-d', strtotime('+30 days'));
    db()->prepare(
        "INSERT INTO gym_subscriptions
            (gym_id, plan, status, trial_ends_at, current_period_start, current_period_end)
         VALUES (?, 'trial', 'active', ?, CURDATE(), ?)"
    )->execute([$gymId, $trialEnd, $trialEnd]);

    // 6. Generate auth session token → log the user in immediately
    require_once __DIR__ . '/../config/app.php';
    $token = bin2hex(random_bytes(SESSION_TOKEN_BYTES));
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    db()->prepare("INSERT INTO sessions_auth (user_id, token, expires_at) VALUES (?,?,?)")
        ->execute([$userId, $token, $expires]);
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
        ->execute([$userId]);

    db()->commit();

    // Set cookie for web clients (same as login flow)
    setcookie('gf_token', $token, [
        'expires' => time() + SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    jsonResponse([
        'success' => true,
        'message' => '¡Bienvenido a GymFlow! Tu prueba gratuita de 30 días ha comenzado.',
        'token' => $token,
        'expires_at' => $expires,
        'gym_id' => $gymId,
        'gym_name' => $gymName,
        'gym_slug' => $slug,
        'user_id' => $userId,
        'user_name' => $adminName,
        'user_email' => $adminEmail,
        'role' => 'admin',
        'trial_ends' => $trialEnd,
        'redirect' => '../index.php',
    ], 201);

} catch (Exception $e) {
    db()->rollBack();
    error_log('[register.php] ' . $e->getMessage());
    jsonError('Error interno del servidor. Por favor intentá de nuevo.', 500);
}
