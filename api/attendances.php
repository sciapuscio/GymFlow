<?php
/**
 * GymFlow CRM — Attendances API
 * GET  ?session_id=X — lista de presentes en una sesión
 * GET  ?member_id=X  — historial de asistencias de un alumno
 * POST               — registrar asistencia
 * DELETE ?id=X       — eliminar check-in por error
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('admin', 'instructor', 'superadmin');
$gymId = (int) $user['gym_id'];
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($method === 'GET') {
    if (isset($_GET['session_id'])) {
        $sessionId = (int) $_GET['session_id'];
        $stmt = db()->prepare("
            SELECT a.id, a.checked_in_at, a.method,
                   m.id AS member_id, m.name AS member_name, m.phone
            FROM member_attendances a
            JOIN members m ON m.id = a.member_id
            WHERE a.gym_session_id = ? AND a.gym_id = ?
            ORDER BY a.checked_in_at ASC
        ");
        $stmt->execute([$sessionId, $gymId]);
        jsonResponse($stmt->fetchAll());
    }

    if (isset($_GET['member_id'])) {
        $memberId = (int) $_GET['member_id'];
        $limit = min((int) ($_GET['limit'] ?? 30), 200);
        $stmt = db()->prepare("
            SELECT a.id, a.checked_in_at, a.method,
                   gs.name AS session_name
            FROM member_attendances a
            LEFT JOIN gym_sessions gs ON gs.id = a.gym_session_id
            WHERE a.member_id = ? AND a.gym_id = ?
            ORDER BY a.checked_in_at DESC
            LIMIT $limit
        ");
        $stmt->execute([$memberId, $gymId]);
        jsonResponse($stmt->fetchAll());
    }

    jsonError('Missing parameter', 400);
}

if ($method === 'POST') {
    $data = getBody();
    $memberId = (int) ($data['member_id'] ?? 0);
    $sessionId = !empty($data['gym_session_id']) ? (int) $data['gym_session_id'] : null;
    $method_in = in_array($data['method'] ?? '', ['manual', 'qr']) ? $data['method'] : 'manual';

    if (!$memberId)
        jsonError('member_id required');

    // Verify member belongs to gym
    $check = db()->prepare("SELECT id FROM members WHERE id = ? AND gym_id = ?");
    $check->execute([$memberId, $gymId]);
    if (!$check->fetch())
        jsonError('Member not found', 404);

    // Avoid duplicate check-in for same session
    if ($sessionId) {
        $dup = db()->prepare("SELECT id FROM member_attendances WHERE member_id = ? AND gym_session_id = ? AND gym_id = ?");
        $dup->execute([$memberId, $sessionId, $gymId]);
        if ($dup->fetch())
            jsonError('Already checked in', 409);
    }

    // Find active membership
    $mem = db()->prepare("
        SELECT id FROM member_memberships
        WHERE member_id = ? AND gym_id = ? AND end_date >= CURDATE()
        ORDER BY end_date DESC LIMIT 1
    ");
    $mem->execute([$memberId, $gymId]);
    $membershipId = $mem->fetchColumn() ?: null;

    db()->prepare("
        INSERT INTO member_attendances (gym_id, member_id, membership_id, gym_session_id, method)
        VALUES (?,?,?,?,?)
    ")->execute([$gymId, $memberId, $membershipId, $sessionId, $method_in]);

    // Increment sessions_used on active membership
    if ($membershipId) {
        db()->prepare("UPDATE member_memberships SET sessions_used = sessions_used + 1 WHERE id = ?")
            ->execute([$membershipId]);
    }

    jsonResponse(['success' => true, 'id' => db()->lastInsertId()], 201);
}

if ($method === 'DELETE' && $id) {
    // Only admin can delete attendance records
    requireAuth('admin', 'superadmin');
    db()->prepare("DELETE FROM member_attendances WHERE id = ? AND gym_id = ?")->execute([$id, $gymId]);
    jsonResponse(['success' => true]);
}

jsonError('Method not allowed', 405);
