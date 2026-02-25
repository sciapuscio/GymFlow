<?php
/**
 * config/local.example.php
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  PLANTILLA DE CONFIGURACIÓN LOCAL — commiteada en git           │
 * │  Copiá este archivo como config/local.php y completá los        │
 * │  valores reales para tu entorno (local o producción).           │
 * │  config/local.php está en .gitignore y NUNCA se sube al repo.   │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * NOTA: BASE_URL se auto-detecta por hostname en config/app.php.
 *       Solo necesitás sobreescribirla aquí si el auto-detect falla.
 *       - localhost / 127.x  →  '/Training'
 *       - cualquier otro     →  ''  (producción)
 */

// ── Base de datos ────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');     // servidor MySQL
define('DB_NAME', 'gymflow');       // nombre de la base de datos
define('DB_USER', 'root');          // usuario MySQL
define('DB_PASS', '');              // contraseña MySQL (cambiar en prod)
define('DB_CHARSET', 'utf8mb4');

// ── Socket.IO (sync-server) ──────────────────────────────────────────────────
// URL del servidor de sincronización en tiempo real
// Local:       'http://localhost:3000'
// Producción:  'https://trainingsocket.access.ly'
define('SOCKET_URL', 'http://localhost:3000');

// ── Timezone (opcional, default: America/Argentina/Buenos_Aires) ─────────────
// define('TIMEZONE', 'America/Argentina/Buenos_Aires');

// ── Seguridad: OTP HMAC Key (OBLIGATORIA en producción) ──────────────────────
// Generá una clave aleatoria con: php -r "echo bin2hex(random_bytes(32));"
// define('OTP_HMAC_KEY', 'pega-aqui-tu-clave-aleatoria-de-64-chars-hex');

// ── CORS: dominios permitidos (opcional, tiene defaults en app.php) ───────────
// Solo necesario si agregás más dominios o usás un entorno diferente.
// define('ALLOWED_ORIGINS', [
//     'https://sistema.gymflow.com.ar',
//     'https://training.access.ly',
//     'http://localhost',
// ]);

