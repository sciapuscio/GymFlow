<?php
/**
 * GymFlow — Support / Helpdesk API
 *
 * GET  (no params)          → list tickets   (filtered by gym for non-superadmin)
 * GET  ?id=X                → ticket detail + messages
 * POST (no params)          → create ticket  {subject, message, priority?}
 * POST ?ticket_id=X         → add message    {message, is_internal?}
 * PUT  ?id=X                → update status  {status}
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$user = requireAuth('admin', 'superadmin', 'instructor', 'staff');
$isSA = $user['role'] === 'superadmin';
$gymId = (int) ($user['gym_id'] ?? 0);
$userId = (int) $user['id'];
$method = $_SERVER['REQUEST_METHOD'];

/* ── helpers ─────────────────────────────────────────────── */
function ok(mixed $data = null): never
{
    echo json_encode($data ?? ['ok' => true]);
    exit;
}
function err(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}
function body(): array
{
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
function canAccessTicket(array $ticket, array $user): bool
{
    if ($user['role'] === 'superadmin')
        return true;
    return (int) $ticket['gym_id'] === (int) $user['gym_id'];
}

/* ── GET ─────────────────────────────────────────────────── */
if ($method === 'GET') {
    $ticketId = (int) ($_GET['id'] ?? 0);

    // Detail + messages
    if ($ticketId) {
        $t = db()->prepare("SELECT t.*, u.name AS creator_name, g.name AS gym_name
            FROM support_tickets t
            JOIN users u ON u.id = t.created_by
            JOIN gyms  g ON g.id = t.gym_id
            WHERE t.id = ?");
        $t->execute([$ticketId]);
        $ticket = $t->fetch();
        if (!$ticket || !canAccessTicket($ticket, $user))
            err('Not found', 404);

        $visibility = $isSA ? '' : 'AND is_internal = 0';
        $m = db()->prepare("SELECT m.*, u.name AS author_name, u.role AS author_role
            FROM support_messages m
            JOIN users u ON u.id = m.user_id
            WHERE m.ticket_id = ? $visibility
            ORDER BY m.created_at ASC");
        $m->execute([$ticketId]);
        $ticket['messages'] = $m->fetchAll();
        ok($ticket);
    }

    // List
    if ($isSA) {
        $status = $_GET['status'] ?? '';
        $where = $status ? "WHERE t.status = " . db()->quote($status) : '';
        $rows = db()->query("SELECT t.*, u.name AS creator_name, g.name AS gym_name,
                (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) AS msg_count
            FROM support_tickets t
            JOIN users u ON u.id = t.created_by
            JOIN gyms  g ON g.id = t.gym_id
            $where
            ORDER BY t.updated_at DESC")->fetchAll();
    } else {
        $rows = db()->prepare("SELECT t.*,
                (SELECT COUNT(*) FROM support_messages WHERE ticket_id = t.id) AS msg_count
            FROM support_tickets t
            WHERE t.gym_id = ?
            ORDER BY t.updated_at DESC");
        $rows->execute([$gymId]);
        $rows = $rows->fetchAll();
    }
    ok($rows);
}

/* ── POST ────────────────────────────────────────────────── */
if ($method === 'POST') {
    $data = body();
    $ticketId = (int) ($_GET['ticket_id'] ?? 0);

    // Add message to existing ticket
    if ($ticketId) {
        $msg = trim($data['message'] ?? '');
        if (!$msg)
            err('Message required');
        $isInternal = $isSA ? (int) ($data['is_internal'] ?? 0) : 0;

        $t = db()->prepare("SELECT * FROM support_tickets WHERE id = ?");
        $t->execute([$ticketId]);
        $ticket = $t->fetch();
        if (!$ticket || !canAccessTicket($ticket, $user))
            err('Not found', 404);
        if ($ticket['status'] === 'closed')
            err('Ticket is closed');

        db()->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_internal)
            VALUES (?,?,?,?)")->execute([$ticketId, $userId, $msg, $isInternal]);

        // Auto-set in_progress when superadmin replies
        if ($isSA && $ticket['status'] === 'open') {
            db()->prepare("UPDATE support_tickets SET status='in_progress' WHERE id=?")
                ->execute([$ticketId]);
        }
        ok(['id' => db()->lastInsertId()]);
    }

    // Create new ticket
    $subject = trim($data['subject'] ?? '');
    $msg = trim($data['message'] ?? '');
    $priority = in_array($data['priority'] ?? '', ['low', 'normal', 'high'])
        ? $data['priority'] : 'normal';
    if (!$subject)
        err('Subject required');
    if (!$msg)
        err('Message required');
    if (!$gymId && !$isSA)
        err('No gym context');

    // Superadmin can specify gym_id; others use their own
    $tGymId = $isSA ? (int) ($data['gym_id'] ?? 0) : $gymId;
    if (!$tGymId)
        err('gym_id required');

    db()->prepare("INSERT INTO support_tickets (gym_id, created_by, subject, priority)
        VALUES (?,?,?,?)")->execute([$tGymId, $userId, $subject, $priority]);
    $newId = (int) db()->lastInsertId();

    db()->prepare("INSERT INTO support_messages (ticket_id, user_id, message)
        VALUES (?,?,?)")->execute([$newId, $userId, $msg]);

    ok(['id' => $newId]);
}

/* ── PUT ─────────────────────────────────────────────────── */
if ($method === 'PUT') {
    $ticketId = (int) ($_GET['id'] ?? 0);
    if (!$ticketId)
        err('id required');
    $data = body();

    $t = db()->prepare("SELECT * FROM support_tickets WHERE id = ?");
    $t->execute([$ticketId]);
    $ticket = $t->fetch();
    if (!$ticket || !canAccessTicket($ticket, $user))
        err('Not found', 404);

    $allowed = $isSA
        ? ['open', 'in_progress', 'resolved', 'closed']
        : ['closed']; // users can only close their own tickets

    $status = $data['status'] ?? '';
    if (!in_array($status, $allowed))
        err('Invalid status');

    db()->prepare("UPDATE support_tickets SET status=? WHERE id=?")
        ->execute([$status, $ticketId]);
    ok();
}

err('Method not allowed', 405);
