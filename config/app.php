<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gymflow');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'GymFlow');
define('APP_VERSION', '1.0.0');
// Auto-detect base URL based on the request hostname:
// - On localhost/127.0.0.1 → '/Training' (XAMPP local subfolder)
// - On any external host (training.access.ly, etc.) → '' (served at root via proxy)
$_gf_host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
$_gf_is_local = (str_starts_with($_gf_host, 'localhost') || str_starts_with($_gf_host, '127.'));
define('BASE_URL', $_gf_is_local ? '/Training' : '');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('SESSION_LIFETIME', 60 * 60 * 8); // 8 hours
define('SESSION_TOKEN_BYTES', 64);
define('TIMEZONE', 'America/Argentina/Buenos_Aires');

date_default_timezone_set(TIMEZONE);
