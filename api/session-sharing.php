<?php
/**
 * GymFlow — Session Sharing API
 *
 * GET  ?session_id=X         → clients + who has access to that session (instructor view)
 * GET  ?shared_with_me=1     → sessions shared with the logged-in member (client view)
 * POST { session_id, shared, share_description, client_ids[] }
 *                            → update sharing settings + grant/revoke access
 * DELETE ?session_id=X&client_id=Y → revoke single access
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('instructor', 'admin', 'superadmin', 'member');
$gymId = (int) $user['gym_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: instructor sees who has access to a session ─────────────────────────
if ($method === 'GET' && isset($_GET['session_id'])) {
    $sessionId = (int) $_GET['session_id'];

    // Verify instructor owns this session
    $sess = db()->prepare("SELECT id, name, shared, share_description FROM gym_sessions WHERE id = ? AND gym_id = ?");
    $sess->execute([$sessionId, $gymId]);
    $session = $sess->fetch();
    if (!$session)
        jsonError('Sesión no encontrada', 404);

    // All clients of this gym + whether they have access
    $clients = db()->prepare("
        SELECT ic.*,
               IF(sag.id IS NOT NULL, 1, 0) AS has_access,
               m.name AS member_name_linked
        FROM instructor_clients ic
        LEFT JOIN session_access_grants sag ON sag.client_id = ic.id AND sag.session_id = ?
        LEFT JOIN members m ON m.id = ic.client_member_id
        WHERE ic.gym_id = ? AND ic.status = 'active'
        ORDER BY ic.client_name
    ");
    $clients->execute([$sessionId, $gymId]);

    jsonResponse(['session' => $session, 'clients' => $clients->fetchAll()]);
}

// ── GET: client sees sessions shared with them ───────────────────────────────
if ($method === 'GET' && isset($_GET['shared_with_me'])) {
    // Must be logged in as member (or instructor/admin viewing their own)
    $memberEmail = $user['email'] ?? null;
    if (!$memberEmail)
        jsonError('Email de usuario no disponible', 400);

    $stmt = db()->prepare("
        SELECT gs.id, gs.name, gs.share_description, gs.total_duration,
               u.name AS instructor_name,
               g.name AS gym_name,
               ic.id  AS client_id,
               gs.created_at
        FROM session_access_grants sag
        JOIN instructor_clients ic  ON ic.id = sag.client_id
        JOIN gym_sessions gs        ON gs.id = sag.session_id AND gs.shared = 1
        JOIN gyms g                 ON g.id  = gs.gym_id
        LEFT JOIN users u           ON u.id  = gs.instructor_id
        WHERE LOWER(ic.client_email) = LOWER(?)
          AND ic.status = 'active'
        ORDER BY gs.created_at DESC
    ");
    $stmt->execute([$memberEmail]);
    jsonResponse($stmt->fetchAll());
}

// ── POST: update session sharing settings ────────────────────────────────────
if ($method === 'POST') {
    // Must be instructor/admin
    if ($user['role'] === 'member')
        jsonError('Sin permisos', 403);

    $body = getBody();
    $sessionId = (int) ($body['session_id'] ?? 0);
    $shared = isset($body['shared']) ? (int) $body['shared'] : null;
    $description = isset($body['share_description']) ? trim($body['share_description']) : null;
    $clientIds = $body['client_ids'] ?? null; // array of instructor_clients.id

    if (!$sessionId)
        jsonError('session_id requerido', 400);

    // Verify ownership
    $check = db()->prepare("SELECT id FROM gym_sessions WHERE id = ? AND gym_id = ?");
    $check->execute([$sessionId, $gymId]);
    if (!$check->fetch())
        jsonError('Sesión no encontrada', 404);

    // Update shared flag / description if provided
    if ($shared !== null || $description !== null) {
        $fields = [];
        $vals = [];
        if ($shared !== null) {
            $fields[] = 'shared = ?';
            $vals[] = $shared ? 1 : 0;
        }
        if ($description !== null) {
            $fields[] = 'share_description = ?';
            $vals[] = $description;
        }
        $vals[] = $sessionId;
        db()->prepare("UPDATE gym_sessions SET " . implode(', ', $fields) . " WHERE id = ?")->execute($vals);
    }

    // Update client access list if provided
    if (is_array($clientIds)) {
        // Delete existing grants for this session
        db()->prepare("DELETE FROM session_access_grants WHERE session_id = ?")->execute([$sessionId]);

        // Re-insert selected
        $ins = db()->prepare("INSERT IGNORE INTO session_access_grants (session_id, client_id) VALUES (?, ?)");
        foreach ($clientIds as $cId) {
            $cId = (int) $cId;
            if (!$cId)
                continue;
            // Verify client belongs to this gym
            $own = db()->prepare("SELECT id FROM instructor_clients WHERE id = ? AND gym_id = ?");
            $own->execute([$cId, $gymId]);
            if ($own->fetch())
                $ins->execute([$sessionId, $cId]);
        }
    }

    jsonResponse(['ok' => true]);
}

// ── DELETE: revoke single access ─────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($user['role'] === 'member')
        jsonError('Sin permisos', 403);

    $sessionId = (int) ($_GET['session_id'] ?? 0);
    $clientId = (int) ($_GET['client_id'] ?? 0);
    if (!$sessionId || !$clientId)
        jsonError('Parámetros requeridos', 400);

    db()->prepare("DELETE FROM session_access_grants WHERE session_id = ? AND client_id = ?")
        ->execute([$sessionId, $clientId]);

    jsonResponse(['ok' => true]);
}

jsonError('Método no permitido', 405);
