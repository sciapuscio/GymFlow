<?php
/**
 * GymFlow CRM — Member Memberships API
 * GET  ?member_id=X       — historial de un alumno
 * GET  ?status=overdue    — lista de deudores del gym
 * POST                    — asignar membresía
 * PUT  ?id=X              — actualizar pago/renovar
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

if ($method === 'GET') {

    // Deudores / vencidos
    if (isset($_GET['status'])) {
        $statusFilter = $_GET['status']; // overdue | pending | expired
        if ($statusFilter === 'expired') {
            $stmt = db()->prepare("
                SELECT mm.*, m.name AS member_name, m.phone, m.email, mp.name AS plan_name
                FROM member_memberships mm
                JOIN members m  ON m.id  = mm.member_id
                LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
                WHERE mm.gym_id = ? AND mm.end_date < CURDATE()
                ORDER BY mm.end_date DESC
                LIMIT 200
            ");
            $stmt->execute([$gymId]);
        } else {
            $stmt = db()->prepare("
                SELECT mm.*, m.name AS member_name, m.phone, m.email, mp.name AS plan_name
                FROM member_memberships mm
                JOIN members m  ON m.id  = mm.member_id
                LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
                WHERE mm.gym_id = ? AND mm.payment_status = ? AND mm.end_date >= CURDATE()
                ORDER BY mm.end_date ASC
                LIMIT 200
            ");
            $stmt->execute([$gymId, $statusFilter]);
        }
        jsonResponse($stmt->fetchAll());
    }

    // Single membership by ID (for edit modal)
    if ($id) {
        $stmt = db()->prepare("
            SELECT mm.*, mp.name AS plan_name
            FROM member_memberships mm
            LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
            WHERE mm.id = ? AND mm.gym_id = ?
        ");
        $stmt->execute([$id, $gymId]);
        $mem = $stmt->fetch();
        if (!$mem)
            jsonError('Not found', 404);
        $pivStmt = db()->prepare("SELECT sede_id FROM member_membership_sedes WHERE membership_id = ?");
        $pivStmt->execute([$id]);
        $mem['sede_ids'] = $pivStmt->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse($mem);
    }

    // Historial de un alumno
    if (isset($_GET['member_id'])) {
        $memberId = (int) $_GET['member_id'];
        $stmt = db()->prepare("
            SELECT mm.*, mp.name AS plan_name
            FROM member_memberships mm
            LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
            WHERE mm.member_id = ? AND mm.gym_id = ?
            ORDER BY mm.start_date DESC
        ");
        $stmt->execute([$memberId, $gymId]);
        jsonResponse($stmt->fetchAll());
    }

    jsonError('Missing parameter', 400);
}

// ── POST — assign membership ────────────────────────────────────────────────
if ($method === 'POST') {
    $data = getBody();
    $memberId = (int) ($data['member_id'] ?? 0);
    if (!$memberId)
        jsonError('member_id required');

    // Verify member belongs to this gym
    $check = db()->prepare("SELECT id FROM members WHERE id = ? AND gym_id = ?");
    $check->execute([$memberId, $gymId]);
    if (!$check->fetch())
        jsonError('Member not found', 404);

    $planId = !empty($data['plan_id']) ? (int) $data['plan_id'] : null;
    $startDate = $data['start_date'] ?? date('Y-m-d');
    $endDate = $data['end_date'] ?? null;
    $amountDue = (float) ($data['amount_due'] ?? 0);
    $amountPaid = (float) ($data['amount_paid'] ?? 0);
    $sessLimit = null;

    // If plan_id provided, pull price + duration from plan
    if ($planId) {
        $plan = db()->prepare("SELECT * FROM membership_plans WHERE id = ? AND gym_id = ?");
        $plan->execute([$planId, $gymId]);
        $planRow = $plan->fetch();
        if ($planRow) {
            $endDate = $endDate ?? date('Y-m-d', strtotime($startDate . ' +' . $planRow['duration_days'] . ' days'));
            $amountDue = $amountDue ?: (float) $planRow['price'];
            $sessLimit = $planRow['sessions_limit'];
        }
    }

    if (!$endDate)
        jsonError('end_date required when no plan_id');

    $payStatus = $amountPaid >= $amountDue && $amountDue > 0 ? 'paid'
        : ($amountPaid > 0 ? 'partial' : 'pending');

    $stmt = db()->prepare("
        INSERT INTO member_memberships
            (gym_id, member_id, plan_id, start_date, end_date, sessions_limit,
             amount_due, amount_paid, payment_status, payment_date, payment_method, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $gymId,
        $memberId,
        $planId,
        $startDate,
        $endDate,
        $sessLimit,
        $amountDue,
        $amountPaid,
        $payStatus,
        $amountPaid > 0 ? date('Y-m-d') : null,
        trim($data['payment_method'] ?? '') ?: null,
        trim($data['notes'] ?? '') ?: null,
    ]);
    $newMembershipId = db()->lastInsertId();

    // Save all_sedes flag
    $allSedes = (int) ($data['all_sedes'] ?? 1);
    db()->prepare("UPDATE member_memberships SET all_sedes = ? WHERE id = ?")
        ->execute([$allSedes, $newMembershipId]);

    // Save sede pivot if not all_sedes
    if (!$allSedes && !empty($data['sede_ids']) && is_array($data['sede_ids'])) {
        $sedeInsert = db()->prepare("INSERT IGNORE INTO member_membership_sedes (membership_id, sede_id) VALUES (?, ?)");
        foreach ($data['sede_ids'] as $sedeId) {
            $sedeId = (int) $sedeId;
            if ($sedeId > 0)
                $sedeInsert->execute([$newMembershipId, $sedeId]);
        }
    }

    jsonResponse(['success' => true, 'id' => $newMembershipId], 201);
}

// ── PUT — update payment / renew ────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $data = getBody();
    $allowed = [
        'start_date',
        'end_date',
        'amount_due',
        'amount_paid',
        'payment_status',
        'payment_date',
        'payment_method',
        'notes',
        'sessions_used'
    ];
    $fields = $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "$f = ?";
            $params[] = $data[$f] === '' ? null : $data[$f];
        }
    }
    // Auto-compute payment_status if amounts changed
    if (isset($data['amount_paid']) || isset($data['amount_due'])) {
        // fetch current amounts
        $cur = db()->prepare("SELECT amount_due, amount_paid FROM member_memberships WHERE id = ? AND gym_id = ?");
        $cur->execute([$id, $gymId]);
        $row = $cur->fetch();
        if ($row) {
            $due = isset($data['amount_due']) ? (float) $data['amount_due'] : (float) $row['amount_due'];
            $paid = isset($data['amount_paid']) ? (float) $data['amount_paid'] : (float) $row['amount_paid'];
            $newStatus = $paid >= $due && $due > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending');
            if (!isset($data['payment_status'])) {
                $fields[] = 'payment_status = ?';
                $params[] = $newStatus;
            }
        }
    }
    if (!$fields)
        jsonError('No fields');
    $params[] = $id;
    $params[] = $gymId;
    db()->prepare("UPDATE member_memberships SET " . implode(', ', $fields) . " WHERE id = ? AND gym_id = ?")
        ->execute($params);

    // Update all_sedes + sede pivot if provided
    if (isset($data['all_sedes'])) {
        $allSedes = (int) $data['all_sedes'];
        db()->prepare("UPDATE member_memberships SET all_sedes = ? WHERE id = ? AND gym_id = ?")
            ->execute([$allSedes, $id, $gymId]);
        db()->prepare("DELETE FROM member_membership_sedes WHERE membership_id = ?")->execute([$id]);
        if (!$allSedes && !empty($data['sede_ids']) && is_array($data['sede_ids'])) {
            $sedeInsert = db()->prepare("INSERT IGNORE INTO member_membership_sedes (membership_id, sede_id) VALUES (?, ?)");
            foreach ($data['sede_ids'] as $sedeId) {
                $sedeId = (int) $sedeId;
                if ($sedeId > 0)
                    $sedeInsert->execute([$id, $sedeId]);
            }
        }
    }

    jsonResponse(['success' => true]);
}

jsonError('Method not allowed', 405);
