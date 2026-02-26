<?php
function jsonResponse(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $code = 400): never
{
    jsonResponse(['error' => $message], $code);
}

function getBody(): array
{
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

/**
 * Reads the Bearer token from the Authorization header.
 * Handles Apache stripping HTTP_AUTHORIZATION via multiple fallbacks.
 * Returns the raw token string, or null if not present.
 */
if (!function_exists('getBearerToken')) {
    function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '')
            ?? '';
        if (!str_starts_with($header, 'Bearer '))
            return null;
        return trim(substr($header, 7));
    }
}

function sanitize(string $val): string
{
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function computeBlockDuration(array $block): int
{
    $cfg = $block['config'] ?? [];
    switch ($block['type']) {
        case 'interval': {
            $rounds = (int) ($cfg['rounds'] ?? 1);
            $work = (int) ($cfg['work'] ?? 40);
            $rest = (int) ($cfg['rest'] ?? 20);
            // No trailing rest after last round
            return $rounds * $work + ($rounds - 1) * $rest;
        }
        case 'tabata': {
            $rounds = (int) ($cfg['rounds'] ?? 8);
            $work = (int) ($cfg['work'] ?? 20);
            $rest = (int) ($cfg['rest'] ?? 10);
            // No trailing rest after last round
            return $rounds * $work + ($rounds - 1) * $rest;
        }
        case 'amrap':
        case 'emom':
        case 'fortime':
            return (int) ($cfg['duration'] ?? 600);
        case 'rest':
        case 'briefing':
            return (int) ($cfg['duration'] ?? 60);
        case 'series':
            return (int) ($cfg['sets'] ?? 3) * ((int) ($cfg['rest'] ?? 60) + 30);
        case 'circuit':
            return count($block['exercises'] ?? []) * (int) ($cfg['station_time'] ?? 40) * (int) ($cfg['rounds'] ?? 1);
        default:
            return 300;
    }
}

function computeTemplateTotal(array $blocks): int
{
    return array_sum(array_map('computeBlockDuration', $blocks));
}

function handleCors(): void
{
    $allowed = defined('ALLOWED_ORIGINS')
        ? ALLOWED_ORIGINS
        : ['http://localhost', 'http://127.0.0.1'];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Always allow same-origin (no Origin header) and loopback
    if ($origin === '' || in_array($origin, $allowed, true)) {
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function formatDuration(int $seconds): string
{
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0)
        return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}

function uploadFile(array $file, string $subdir = ''): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK)
        return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
    if (!in_array($ext, $allowed))
        return null;
    $dir = UPLOAD_PATH . ($subdir ? $subdir . '/' : '');
    if (!is_dir($dir))
        mkdir($dir, 0755, true);
    $filename = uniqid('img_', true) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return BASE_URL . '/assets/uploads/' . ($subdir ? $subdir . '/' : '') . $filename;
    }
    return null;
}

function randomIntelligent(array $exercises, array $options = []): array
{
    $level = $options['level'] ?? 'all';
    $equipment = $options['equipment'] ?? [];
    $excludeMuscle = $options['exclude_muscle'] ?? [];
    $count = $options['count'] ?? 3;

    $pool = array_filter($exercises, function ($ex) use ($level, $equipment, $excludeMuscle) {
        if ($level !== 'all' && $ex['level'] !== 'all' && $ex['level'] !== $level)
            return false;
        if ($excludeMuscle && in_array($ex['muscle_group'], $excludeMuscle))
            return false;
        if ($equipment) {
            $exEq = json_decode($ex['equipment'] ?? '[]', true) ?: [];
            if ($exEq && !array_intersect($exEq, $equipment))
                return false;
        }
        return true;
    });

    shuffle($pool);
    return array_slice(array_values($pool), 0, $count);
}
