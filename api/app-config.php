<?php
// ── /api/app-config.php ──────────────────────────────────────────────────────
// Superadmin-only endpoint to read/write app_config values.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = requireAuth();
if (($user['role'] ?? '') !== 'superadmin') {
    jsonResponse(['error' => 'Forbidden'], 403);
}

// ── GET: load all known config keys ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $rows = db()->query(
            "SELECT `key`, `value` FROM app_config WHERE `key` IN
             ('min_app_version','android_store_url','ios_store_url')"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        jsonResponse(['ok' => true, 'config' => $rows]);
    } catch (\Throwable $e) {
        jsonResponse(['ok' => true, 'config' => []]);
    }
}

// ── POST: upsert one or more keys ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getBody();
    $allowed = ['min_app_version', 'android_store_url', 'ios_store_url'];
    $updated = 0;
    try {
        $stmt = db()->prepare(
            "INSERT INTO app_config (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $stmt->execute([$key, trim($data[$key])]);
                $updated++;
            }
        }
        jsonResponse(['ok' => true, 'updated' => $updated]);
    } catch (\Throwable $e) {
        jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
