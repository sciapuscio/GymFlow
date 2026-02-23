<?php
/**
 * config/app.php
 *
 * Carga la configuración local (config/local.php) que está en .gitignore.
 * Si no existe (primer deploy), lanza un error claro.
 */

$_localConfig = __DIR__ . '/local.php';

if (!file_exists($_localConfig)) {
    $msg = "⚠️  Falta config/local.php\n\n" .
        "Copiá config/local.example.php como config/local.php y completá los valores reales.\n\n" .
        "  cp config/local.example.php config/local.php";
    if (php_sapi_name() === 'cli') {
        die($msg . "\n");
    }
    http_response_code(503);
    die('<pre style="font-family:monospace;padding:20px">' . htmlspecialchars($msg) . '</pre>');
}

require_once $_localConfig;

// ── Constantes derivadas (no editables) ──────────────────────────────────────
define('APP_NAME', 'GymFlow');
define('APP_VERSION', '1.0.0');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 horas
define('SESSION_TOKEN_BYTES', 64);

date_default_timezone_set(TIMEZONE);
