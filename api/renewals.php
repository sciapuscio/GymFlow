<?php
/**
 * api/renewals.php — Subscription renewal history + invoice upload
 * GET    ?gym_id=X          → list renewals
 * POST   multipart/form-data → insert renewal with optional invoice file
 * DELETE ?id=X              → delete renewal (superadmin only)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
$user = requireAuth('admin', 'superadmin');
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $gymId = (int) ($_GET['gym_id'] ?? $user['gym_id'] ?? 0);
    if (!$gymId) {
        http_response_code(400);
        echo json_encode(['error' => 'gym_id required']);
        exit;
    }
    if ($user['role'] === 'admin' && (int) $user['gym_id'] !== $gymId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as created_by_name
        FROM subscription_renewals r
        LEFT JOIN users u ON u.id = r.created_by
        WHERE r.gym_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$gymId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ── POST (superadmin only, multipart/form-data) ───────────────────────────────
if ($method === 'POST') {
    if ($user['role'] !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    // Fields come as $_POST when multipart/form-data
    $gymId = (int) ($_POST['gym_id'] ?? 0);
    $eventType = $_POST['event_type'] ?? 'renewal';
    $plan = $_POST['plan'] ?? 'instructor';
    $periodStart = $_POST['period_start'] ?? null;
    $periodEnd = $_POST['period_end'] ?? null;
    $amountArs = isset($_POST['amount_ars']) && $_POST['amount_ars'] !== '' ? (int) $_POST['amount_ars'] : null;
    $extraSalas = (int) ($_POST['extra_salas'] ?? 0);
    $notes = $_POST['notes'] ?? null;

    if (!$gymId) {
        http_response_code(400);
        echo json_encode(['error' => 'gym_id required']);
        exit;
    }

    // Handle optional invoice file upload
    $invoicePath = null;
    if (!empty($_FILES['invoice']['tmp_name'])) {
        $file = $_FILES['invoice'];
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $maxBytes = 10 * 1024 * 1024; // 10 MB

        if (!in_array($file['type'], $allowed)) {
            http_response_code(422);
            echo json_encode(['error' => 'Tipo de archivo no permitido. Solo PDF, JPEG, PNG.']);
            exit;
        }
        if ($file['size'] > $maxBytes) {
            http_response_code(422);
            echo json_encode(['error' => 'El archivo supera los 10 MB.']);
            exit;
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'invoice_gym' . $gymId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../storage/invoices/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0755, true);
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al guardar el archivo.']);
            exit;
        }
        $invoicePath = 'storage/invoices/' . $filename;
    }

    // Insert renewal record
    $ins = $pdo->prepare("INSERT INTO subscription_renewals
        (gym_id, event_type, plan, period_start, period_end, amount_ars, extra_salas, notes, invoice_path, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([
        $gymId,
        $eventType,
        $plan,
        $periodStart ?: null,
        $periodEnd ?: null,
        $amountArs,
        $extraSalas,
        $notes,
        $invoicePath,
        $user['id']
    ]);
    $renewalId = $pdo->lastInsertId();

    // Cascade update gym_subscriptions for billing events
    if (in_array($eventType, ['activation', 'renewal', 'plan_change'])) {
        $pdo->prepare("UPDATE gym_subscriptions
            SET plan=?, status='active', current_period_start=?, current_period_end=?,
                extra_salas=?, price_ars=?, notes=?, updated_at=NOW()
            WHERE gym_id=?")->execute([$plan, $periodStart, $periodEnd, $extraSalas, $amountArs, $notes, $gymId]);
    } elseif ($eventType === 'expiry') {
        $pdo->prepare("UPDATE gym_subscriptions SET status='expired', updated_at=NOW() WHERE gym_id=?")->execute([$gymId]);
    } elseif ($eventType === 'suspension') {
        $pdo->prepare("UPDATE gym_subscriptions SET status='suspended', updated_at=NOW() WHERE gym_id=?")->execute([$gymId]);
    }

    echo json_encode(['ok' => true, 'id' => $renewalId]);
    exit;
}

// ── DELETE (superadmin only) ──────────────────────────────────────────────────
if ($method === 'DELETE') {
    if ($user['role'] !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'id required']);
        exit;
    }

    // Remove file too if present
    $row = $pdo->prepare("SELECT invoice_path FROM subscription_renewals WHERE id=?");
    $row->execute([$id]);
    $r = $row->fetch();
    if ($r && $r['invoice_path']) {
        $fullPath = __DIR__ . '/../' . $r['invoice_path'];
        if (file_exists($fullPath))
            unlink($fullPath);
    }

    $pdo->prepare("DELETE FROM subscription_renewals WHERE id=?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
