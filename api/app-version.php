<?php
// ── /api/app-version.php ─────────────────────────────────────────────────────
// Public endpoint — no auth required.
// Returns the minimum app version required to use GymFlow, plus store URLs.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $rows = db()->query(
        "SELECT `key`, `value` FROM app_config WHERE `key` IN ('min_app_version','android_store_url','ios_store_url')"
    )->fetchAll(\PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'ok' => true,
        'min_version' => $rows['min_app_version'] ?? '1.0.0',
        'android_url' => $rows['android_store_url'] ?? null,
        'ios_url' => $rows['ios_store_url'] ?? null,
    ]);
} catch (\Throwable $e) {
    // If the table doesn't exist yet, return a safe default so the app is not blocked
    echo json_encode([
        'ok' => true,
        'min_version' => '1.0.0',
        'android_url' => null,
        'ios_url' => null,
    ]);
}
