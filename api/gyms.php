<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// GET /api/gyms.php — list gyms (superadmin only)
if ($method === 'GET' && !$id) {
    $user = requireAuth('superadmin', 'admin');
    if ($user['role'] === 'superadmin') {
        $gyms = db()->query("SELECT id, name, slug, logo_path, primary_color, secondary_color, font_family, font_display, spotify_mode, active, created_at FROM gyms ORDER BY name")->fetchAll();
    } else {
        $gyms = db()->prepare("SELECT id, name, slug, logo_path, primary_color, secondary_color, font_family, font_display, spotify_mode, active FROM gyms WHERE id = ?")->execute([$user['gym_id']]) ? [] : [];
        $stmt = db()->prepare("SELECT id, name, slug, logo_path, primary_color, secondary_color, font_family, font_display, spotify_mode, active FROM gyms WHERE id = ?");
        $stmt->execute([$user['gym_id']]);
        $gyms = [$stmt->fetch()];
    }
    jsonResponse($gyms);
}

// GET /api/gyms.php?id=N
if ($method === 'GET' && $id) {
    $user = requireAuth('superadmin', 'admin');
    requireGymAccess($user, $id);
    $stmt = db()->prepare("SELECT * FROM gyms WHERE id = ?");
    $stmt->execute([$id]);
    $gym = $stmt->fetch();
    if (!$gym)
        jsonError('Gym not found', 404);
    jsonResponse($gym);
}

// Upload logo — must come BEFORE the generic POST handler
if ($method === 'POST' && isset($_GET['logo']) && $id) {
    $user = requireAuth('superadmin', 'admin');
    requireGymAccess($user, $id);
    if (isset($_FILES['logo'])) {
        $path = uploadFile($_FILES['logo'], 'logos/gyms');
        if ($path) {
            db()->prepare("UPDATE gyms SET logo_path = ? WHERE id = ?")->execute([$path, $id]);
            jsonResponse(['path' => $path]);
        }
    }
    jsonError('Upload failed');
}

// POST — create gym (superadmin)
if ($method === 'POST' && !isset($_GET['logo'])) {
    $user = requireAuth('superadmin');
    $data = getBody();

    if (empty($data['name']))
        jsonError('Name required');
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $data['name']));

    $stmt = db()->prepare(
        "INSERT INTO gyms (name, slug, primary_color, secondary_color, font_family, font_display, spotify_mode, qr_token) 
         VALUES (?, ?, ?, ?, ?, ?, ?, UUID())"
    );
    $stmt->execute([
        sanitize($data['name']),
        $slug,
        $data['primary_color'] ?? '#00f5d4',
        $data['secondary_color'] ?? '#ff6b35',
        $data['font_family'] ?? 'Inter',
        $data['font_display'] ?? 'Bebas Neue',
        $data['spotify_mode'] ?? 'disabled',
    ]);
    $newId = db()->lastInsertId();

    // Auto-create a 30-day trial subscription for the new gym
    $trialEnd = date('Y-m-d', strtotime('+30 days'));
    db()->prepare(
        "INSERT IGNORE INTO gym_subscriptions 
            (gym_id, plan, status, trial_ends_at, current_period_start, current_period_end, extra_salas, price_ars)
         VALUES (?, 'instructor', 'active', ?, CURDATE(), ?, 0, 0)"
    )->execute([$newId, $trialEnd, $trialEnd]);

    jsonResponse(['id' => $newId, 'slug' => $slug], 201);

}

// PUT — update gym
if ($method === 'PUT' && $id) {
    $user = requireAuth('superadmin', 'admin');
    requireGymAccess($user, $id);
    $data = getBody();

    $fields = [];
    $params = [];
    $allowed = ['name', 'primary_color', 'secondary_color', 'font_family', 'font_display', 'spotify_mode', 'spotify_client_id', 'spotify_client_secret', 'active', 'logo_path'];
    foreach ($allowed as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f = ?";
            $params[] = $data[$f];
        }
    }
    if (empty($fields))
        jsonError('No fields to update');
    $params[] = $id;
    db()->prepare("UPDATE gyms SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    jsonResponse(['success' => true]);
}

// DELETE
if ($method === 'DELETE' && $id) {
    $user = requireAuth('superadmin');
    db()->prepare("DELETE FROM gyms WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true]);
}

// (logo upload handled above)
