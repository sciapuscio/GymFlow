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
 */

// ── Base de datos ────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');     // servidor MySQL
define('DB_NAME', 'gymflow');       // nombre de la base de datos
define('DB_USER', 'root');          // usuario MySQL
define('DB_PASS', '');              // contraseña MySQL
define('DB_CHARSET', 'utf8mb4');

// ── Aplicación ───────────────────────────────────────────────────────────────

// URL pública base de la aplicación web (sin barra al final)
// Local XAMPP:           '/Training'
// Producción en raíz:   ''
// Producción en subdir: '/gymflow'
define('BASE_URL', '/Training');

// ── Socket.IO (sync-server) ──────────────────────────────────────────────────

// URL del servidor de sincronización en tiempo real
// Local:       'http://localhost:3000'
// Producción:  'https://tu-dominio.com:3000'  o  'https://sync.tu-dominio.com'
define('SOCKET_URL', 'http://localhost:3000');

// ── Timezone ─────────────────────────────────────────────────────────────────
define('TIMEZONE', 'America/Argentina/Buenos_Aires');
