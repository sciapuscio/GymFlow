<?php
// api/subscriptions.php — Gym subscription management (superadmin only)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('superadmin');
$method = $_SERVER['REQUEST_METHOD'];
$gymId = isset($_GET['gym_id']) ? (int) $_GET['gym_id'] : null;

// ── GET ?gym_id=X — fetch subscription for a gym ───────────────────────────
if ($method === 'GET' && $gymId) {
    $stmt = db()->prepare(
        "SELECT gs.*, g.name as gym_name 
         FROM gym_subscriptions gs 
         JOIN gyms g ON g.id = gs.gym_id
         WHERE gs.gym_id = ?"
    );
    $stmt->execute([$gymId]);
    $sub = $stmt->fetch();
    if (!$sub)
        jsonError('Subscription not found', 404);
    jsonResponse($sub);
}

// ── GET (all) — list all gyms with subscription info ───────────────────────
if ($method === 'GET') {
    $rows = db()->query(
        "SELECT g.id, g.name, g.slug, g.active,
                gs.plan, gs.status, gs.trial_ends_at,
                gs.current_period_start, gs.current_period_end, gs.notes,
                CASE
                    WHEN gs.id IS NULL THEN 'no_subscription'
                    WHEN gs.status = 'suspended' THEN 'suspended'
                    WHEN gs.current_period_end < CURDATE() THEN 'expired'
                    WHEN gs.current_period_end <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expiring_soon'
                    ELSE 'active'
                END as computed_status
         FROM gyms g
         LEFT JOIN gym_subscriptions gs ON gs.gym_id = g.id
         ORDER BY g.name"
    )->fetchAll();
    jsonResponse($rows);
}

// ── POST — create subscription (used internally on gym creation) ─────────── 
if ($method === 'POST') {
    $data = getBody();
    if (empty($data['gym_id']))
        jsonError('gym_id required');

    $gid = (int) $data['gym_id'];
    $plan = $data['plan'] ?? 'trial';
    $days = $plan === 'annual' ? 365 : 30;
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime("+{$days} days"));

    $stmt = db()->prepare(
        "INSERT INTO gym_subscriptions 
            (gym_id, plan, status, trial_ends_at, current_period_start, current_period_end, notes)
         VALUES (?, ?, 'active', ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            plan = VALUES(plan),
            status = 'active',
            current_period_start = VALUES(current_period_start),
            current_period_end = VALUES(current_period_end),
            notes = VALUES(notes),
            updated_at = NOW()"
    );
    $trialEnd = $plan === 'trial' ? $end : null;
    $stmt->execute([$gid, $plan, $trialEnd, $start, $end, $data['notes'] ?? null]);

    jsonResponse(['success' => true, 'period_end' => $end], 201);
}

// ── PUT ?gym_id=X — extend / modify subscription ───────────────────────────
if ($method === 'PUT' && $gymId) {
    $data = getBody();

    $fields = [];
    $params = [];

    $allowed = ['plan', 'status', 'current_period_start', 'current_period_end', 'trial_ends_at', 'notes'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }

    // Shorthand: extend by N days from today or from current end (whichever is later)
    if (!empty($data['extend_days'])) {
        $days = (int) $data['extend_days'];
        $stmt = db()->prepare("SELECT current_period_end FROM gym_subscriptions WHERE gym_id = ?");
        $stmt->execute([$gymId]);
        $cur = $stmt->fetchColumn();
        $base = ($cur && $cur > date('Y-m-d')) ? $cur : date('Y-m-d');
        $newEnd = date('Y-m-d', strtotime($base . " +{$days} days"));
        $fields[] = "current_period_end = ?";
        $params[] = $newEnd;
        $fields[] = "status = 'active'";
    }

    if (empty($fields))
        jsonError('No fields to update');

    $params[] = $gymId;
    db()->prepare(
        "INSERT INTO gym_subscriptions (gym_id) VALUES (?)
         ON DUPLICATE KEY UPDATE gym_id = gym_id"
    )->execute([$gymId]);

    db()->prepare(
        "UPDATE gym_subscriptions SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE gym_id = ?"
    )->execute($params);

    jsonResponse(['success' => true]);
}

jsonError('Method not allowed', 405);
