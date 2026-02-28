<?php
/**
 * GymFlow — Sedes API
 *
 * GET    /api/sedes.php           → lista de sedes del gym (auth: admin/superadmin)
 * GET    /api/sedes.php?id=N      → detalle de una sede
 * GET    /api/sedes.php?qr_token= → info pública por QR (sin autenticación, para checkin)
 * POST   /api/sedes.php           → crear sede
 * PUT    /api/sedes.php?id=N      → editar sede
 * DELETE /api/sedes.php?id=N      → eliminar sede
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET por qr_token (público — para resolver sede en check-in) ───────────────
if ($method === 'GET' && isset($_GET['qr_token'])) {
    $stmt = db()->prepare("
        SELECT s.id, s.name, s.address, s.gym_id,
               g.name AS gym_name, g.primary_color, g.logo_path
        FROM sedes s
        JOIN gyms g ON g.id = s.gym_id
        WHERE s.qr_token = ? AND s.active = 1
    ");
    $stmt->execute([$_GET['qr_token']]);
    $sede = $stmt->fetch();
    if (!$sede)
        jsonError('Sede no encontrada', 404);
    jsonResponse($sede);
}

// ── A partir de aquí requiere autenticación ───────────────────────────────────
$user = requireAuth('admin', 'superadmin', 'staff', 'instructor');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? $user['gym_id'])
    : (int) $user['gym_id'];

// ── GET all ───────────────────────────────────────────────────────────────────
if ($method === 'GET' && !$id) {
    $stmt = db()->prepare("
        SELECT s.*,
               COUNT(DISTINCT ss.id)  AS slots_count,
               COUNT(DISTINCT sa.id)  AS salas_count
        FROM sedes s
        LEFT JOIN schedule_slots ss ON ss.sede_id = s.id
        LEFT JOIN salas          sa ON sa.sede_id = s.id
        WHERE s.gym_id = ?
        GROUP BY s.id
        ORDER BY s.name
    ");
    $stmt->execute([$gymId]);
    jsonResponse($stmt->fetchAll());
}

// ── GET single ────────────────────────────────────────────────────────────────
if ($method === 'GET' && $id) {
    $stmt = db()->prepare("SELECT * FROM sedes WHERE id = ? AND gym_id = ?");
    $stmt->execute([$id, $gymId]);
    $sede = $stmt->fetch();
    if (!$sede)
        jsonError('Sede no encontrada', 404);
    jsonResponse($sede);
}

// ── POST — crear sede ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    requireAuth('admin', 'superadmin');
    $data = getBody();
    if (empty($data['name']))
        jsonError('name requerido');

    $qrToken = bin2hex(random_bytes(16)); // UUID-like token único
    $stmt = db()->prepare("
        INSERT INTO sedes (gym_id, name, address, qr_token)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $gymId,
        sanitize($data['name']),
        sanitize($data['address'] ?? ''),
        $qrToken,
    ]);
    $newId = db()->lastInsertId();
    jsonResponse(['id' => $newId, 'qr_token' => $qrToken], 201);
}

// ── PUT — editar sede ─────────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    requireAuth('admin', 'superadmin');
    $data = getBody();
    $allowed = ['name', 'address', 'active'];
    $fields = $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = is_string($data[$f]) ? sanitize($data[$f]) : $data[$f];
        }
    }
    if (!$fields)
        jsonError('Sin campos para actualizar');
    $params[] = $id;
    $params[] = $gymId;
    db()->prepare("UPDATE sedes SET " . implode(', ', $fields) . " WHERE id = ? AND gym_id = ?")
        ->execute($params);
    jsonResponse(['success' => true]);
}

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    requireAuth('admin', 'superadmin');
    // Chequear si tiene slots o alumnos asociados
    $slots = db()->prepare("SELECT COUNT(*) FROM schedule_slots WHERE sede_id = ?")->execute([$id]);
    db()->prepare("DELETE FROM sedes WHERE id = ? AND gym_id = ?")->execute([$id, $gymId]);
    jsonResponse(['success' => true]);
}

jsonError('Método no permitido', 405);
