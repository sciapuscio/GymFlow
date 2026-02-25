<?php
/**
 * GymFlow CRM — Members API
 * GET    /api/members.php          — list (with active membership info)
 * GET    /api/members.php?id=X     — single member detail
 * POST   /api/members.php          — create
 * PUT    /api/members.php?id=X     — update
 * DELETE /api/members.php?id=X     — soft delete
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$user = requireAuth('admin', 'superadmin', 'staff');
$gymId = (int) $user['gym_id'];
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET — list or single ────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        // Single member with active membership + last 5 attendances
        $stmt = db()->prepare("
            SELECT m.*,
                   mm.id         AS membership_id,
                   mm.end_date,
                   mm.payment_status,
                   mm.sessions_used,
                   mm.sessions_limit,
                   mp.name       AS plan_name,
                   mp.price      AS plan_price
            FROM members m
            LEFT JOIN member_memberships mm ON mm.member_id = m.id
                   AND mm.end_date >= CURDATE()
                   AND mm.gym_id = m.gym_id
            LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
            WHERE m.id = ? AND m.gym_id = ?
            ORDER BY mm.end_date DESC
            LIMIT 1
        ");
        $stmt->execute([$id, $gymId]);
        $member = $stmt->fetch();
        if (!$member)
            jsonError('Member not found', 404);

        // Last 10 attendances
        $att = db()->prepare("
            SELECT a.checked_in_at, a.method, gs.name AS session_name
            FROM member_attendances a
            LEFT JOIN gym_sessions gs ON gs.id = a.gym_session_id
            WHERE a.member_id = ? AND a.gym_id = ?
            ORDER BY a.checked_in_at DESC LIMIT 10
        ");
        $att->execute([$id, $gymId]);
        $member['recent_attendances'] = $att->fetchAll();

        jsonResponse($member);
    }

    // List with membership status
    $status = $_GET['status'] ?? 'all'; // all | active | expired | overdue
    $search = trim($_GET['q'] ?? '');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $where = ['m.gym_id = ?'];
    $params = [$gymId];

    if ($search) {
        $where[] = '(m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)';
        $like = "%$search%";
        array_push($params, $like, $like, $like);
    }

    $whereStr = implode(' AND ', $where);

    $sql = "
        SELECT m.id, m.name, m.email, m.phone, m.active, m.created_at,
               mm.end_date, mm.payment_status, mm.sessions_used, mm.sessions_limit,
               mp.name AS plan_name,
               CASE
                   WHEN mm.id IS NULL       THEN 'no_membership'
                   WHEN mm.end_date < CURDATE() THEN 'expired'
                   WHEN mm.payment_status = 'overdue' THEN 'overdue'
                   WHEN mm.payment_status = 'pending' THEN 'pending'
                   ELSE 'active'
               END AS membership_status
        FROM members m
        LEFT JOIN member_memberships mm ON mm.member_id = m.id
               AND mm.end_date >= CURDATE()
               AND mm.gym_id = m.gym_id
        LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
        WHERE $whereStr
        ORDER BY m.name ASC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();

    // Filter by status after query (simpler than complex SQL subqueries)
    if ($status !== 'all') {
        $members = array_values(array_filter($members, fn($m) => $m['membership_status'] === $status));
    }

    // Total count for pagination
    $countStmt = db()->prepare("SELECT COUNT(*) FROM members m WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    jsonResponse(['members' => $members, 'total' => $total, 'page' => $page, 'limit' => $limit]);
}

// ── POST — create member ────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = getBody();
    $name = trim($data['name'] ?? '');
    if (!$name)
        jsonError('name required');

    $stmt = db()->prepare("
        INSERT INTO members (gym_id, name, email, phone, birth_date, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $gymId,
        $name,
        trim($data['email'] ?? '') ?: null,
        trim($data['phone'] ?? '') ?: null,
        $data['birth_date'] ?? null,
        trim($data['notes'] ?? '') ?: null,
    ]);
    $newId = db()->lastInsertId();
    jsonResponse(['success' => true, 'id' => $newId], 201);
}

// ── PUT — update member ─────────────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $data = getBody();
    $allowed = ['name', 'email', 'phone', 'birth_date', 'notes', 'active'];
    $fields = $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = $data[$f] === '' ? null : $data[$f];
        }
    }
    if (!$fields)
        jsonError('No fields');
    $params[] = $id;
    $params[] = $gymId;
    db()->prepare("UPDATE members SET " . implode(', ', $fields) . " WHERE id = ? AND gym_id = ?")
        ->execute($params);
    jsonResponse(['success' => true]);
}

// ── DELETE — soft delete ────────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    db()->prepare("UPDATE members SET active = 0 WHERE id = ? AND gym_id = ?")
        ->execute([$id, $gymId]);
    jsonResponse(['success' => true]);
}

jsonError('Method not allowed', 405);
