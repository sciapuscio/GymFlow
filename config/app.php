<?php
/**
 * config/app.php
 *
 * Carga la configuración local (config/local.php) que está en .gitignore.
 * Si no existe, usa defaults seguros (dev local).
 */

$_localConfig = __DIR__ . '/local.php';
if (file_exists($_localConfig)) {
    require_once $_localConfig;
}

// ── BASE_URL: auto-detección por hostname ────────────────────────────────────
// No va en local.php para evitar errores de configuración en producción.
// Local XAMPP (localhost / 127.x)  →  '/Training'
// Cualquier otro host              →  ''\
if (!defined('BASE_URL')) {
    $_gf_host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
    $_gf_is_local = str_starts_with($_gf_host, 'localhost') || str_starts_with($_gf_host, '127.');
    define('BASE_URL', $_gf_is_local ? '/Training' : '');
}

// ── DB defaults (se sobreescriben si local.php los define) ───────────────────
if (!defined('DB_HOST'))
    define('DB_HOST', 'localhost');
if (!defined('DB_NAME'))
    define('DB_NAME', 'gymflow');
if (!defined('DB_USER'))
    define('DB_USER', 'root');
if (!defined('DB_PASS'))
    define('DB_PASS', '');
if (!defined('DB_CHARSET'))
    define('DB_CHARSET', 'utf8mb4');

// ── Socket URL default ───────────────────────────────────────────────────────
if (!defined('SOCKET_URL'))
    define('SOCKET_URL', 'http://localhost:3000');

// ── Timezone ─────────────────────────────────────────────────────────────────
if (!defined('TIMEZONE'))
    define('TIMEZONE', 'America/Argentina/Buenos_Aires');

// ── Constantes fijas ─────────────────────────────────────────────────────────
define('APP_NAME', 'GymFlow');
define('APP_VERSION', '1.0.0');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('SESSION_LIFETIME', 60 * 60 * 8);
define('SESSION_TOKEN_BYTES', 64);

// ── CORS: dominios permitidos ─────────────────────────────────────────────────
// Agregar dominios en local.php via define('ALLOWED_ORIGINS', [...])
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', [
        'https://sistema.gymflow.com.ar',
        'https://training.access.ly',
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1',
    ]);
}

// ── OTP HMAC Key ──────────────────────────────────────────────────────────────
// DEBE definirse en local.php en producción: define('OTP_HMAC_KEY', 'una-clave-aleatoria-larga');
// Generá una con: php -r "echo bin2hex(random_bytes(32));"
if (!defined('OTP_HMAC_KEY')) {
    // En dev: advertencia silenciosa. En producción esto es un error crítico.
    $isLocal = str_starts_with(strtolower($_SERVER['HTTP_HOST'] ?? 'localhost'), 'localhost')
        || str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.');
    if (!$isLocal) {
        error_log('[GymFlow SECURITY] OTP_HMAC_KEY no está definida en local.php — el 2FA no es seguro');
    }
    define('OTP_HMAC_KEY', 'gymflow_otp_insecure_key_' . ($_SERVER['HTTP_HOST'] ?? 'local'));
}

date_default_timezone_set(TIMEZONE);
