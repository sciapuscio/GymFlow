<?php
/**
 * GymFlow — Instructor Clients API
 *
 * GET    /api/instructor-clients.php           → list my clients
 * POST   /api/instructor-clients.php           → add client { client_email, client_name }
 * DELETE /api/instructor-clients.php?id=X      → remove client
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list clients ────────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = db()->prepare("
        SELECT ic.*,
               m.name  AS member_name_linked,
               m.email AS member_email_linked,
               (SELECT COUNT(*) FROM session_access_grants sag
                JOIN gym_sessions gs ON gs.id = sag.session_id
                WHERE sag.client_id = ic.id AND gs.gym_id = ?) AS session_count
        FROM instructor_clients ic
        LEFT JOIN members m ON m.id = ic.client_member_id
        WHERE ic.gym_id = ?
        ORDER BY ic.client_name
    ");
    $stmt->execute([$gymId, $gymId]);
    jsonResponse($stmt->fetchAll());
}

// ── POST: add client ─────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = getBody();
    $email = strtolower(trim($body['client_email'] ?? ''));
    $name = trim($body['client_name'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
        jsonError('Email inválido', 400);
    if (!$name)
        jsonError('Nombre requerido', 400);

    // Try to link to existing member
    $memberStmt = db()->prepare("SELECT id FROM members WHERE LOWER(email) = ? AND gym_id = ?");
    $memberStmt->execute([$email, $gymId]);
    $memberId = $memberStmt->fetchColumn() ?: null;

    // Also check members of other gyms (external clients of this instructor)
    if (!$memberId) {
        $extStmt = db()->prepare("SELECT id FROM members WHERE LOWER(email) = ? LIMIT 1");
        $extStmt->execute([$email]);
        $memberId = $extStmt->fetchColumn() ?: null;
    }

    try {
        db()->prepare("
            INSERT INTO instructor_clients (gym_id, client_member_id, client_email, client_name)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE client_name = VALUES(client_name),
                                    client_member_id = COALESCE(VALUES(client_member_id), client_member_id),
                                    status = 'active'
        ")->execute([$gymId, $memberId, $email, $name]);

        $id = db()->lastInsertId() ?: null;

        // If ON DUPLICATE triggered, fetch the existing id
        if (!$id) {
            $id = db()->prepare("SELECT id FROM instructor_clients WHERE gym_id = ? AND client_email = ?");
            $id->execute([$gymId, $email]);
            $id = $id->fetchColumn();
        }

        jsonResponse([
            'id' => (int) $id,
            'client_email' => $email,
            'client_name' => $name,
            'client_member_id' => $memberId,
            'linked' => $memberId !== null,
        ]);
    } catch (\PDOException $e) {
        jsonError('Error al guardar cliente: ' . $e->getMessage(), 500);
    }
}

// ── DELETE: remove client ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id)
        jsonError('ID requerido', 400);

    // Verify ownership
    $check = db()->prepare("SELECT id FROM instructor_clients WHERE id = ? AND gym_id = ?");
    $check->execute([$id, $gymId]);
    if (!$check->fetch())
        jsonError('Cliente no encontrado', 404);

    // Remove access grants first
    db()->prepare("
        DELETE sag FROM session_access_grants sag
        JOIN gym_sessions gs ON gs.id = sag.session_id
        WHERE sag.client_id = ? AND gs.gym_id = ?
    ")->execute([$id, $gymId]);

    db()->prepare("DELETE FROM instructor_clients WHERE id = ? AND gym_id = ?")
        ->execute([$id, $gymId]);

    jsonResponse(['ok' => true]);
}

jsonError('Método no permitido', 405);
