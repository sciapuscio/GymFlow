<?php
/**
 * GymFlow — Asistencias por clase
 * Muestra el roster de presencias/ausencias para cada clase de un día seleccionado.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'instructor', 'superadmin', 'staff');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];

$today = date('Y-m-d');
$selDate = $_GET['date'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selDate))
    $selDate = $today;
$dayOfWeek = (int) date('N', strtotime($selDate)) - 1;

// Load sedes and sede filter
$sedesStmt = db()->prepare("SELECT id, name FROM sedes WHERE gym_id = ? AND active = 1 ORDER BY name");
$sedesStmt->execute([$gymId]);
$sedes = $sedesStmt->fetchAll();
$sedeId = isset($_GET['sede_id']) && (int) $_GET['sede_id'] > 0 ? (int) $_GET['sede_id'] : null;
// Auto-select if only one sede
if (!$sedeId && count($sedes) === 1)
    $sedeId = (int) $sedes[0]['id'];

// Fetch slots for this day
$slotsStmt = db()->prepare("
    SELECT ss.id, ss.label AS class_name, ss.start_time, ss.end_time, ss.capacity,
           sal.name AS sala_name
    FROM schedule_slots ss
    LEFT JOIN salas sal ON sal.id = ss.sala_id
    WHERE ss.gym_id = ? AND ss.day_of_week = ?
    AND (? IS NULL OR ss.sede_id = ?)
    ORDER BY ss.start_time
");
$slotsStmt->execute([$gymId, $dayOfWeek, $sedeId, $sedeId]);
$slots = $slotsStmt->fetchAll();

// For each slot, fetch reservations for the selected date
$rosterBySlot = [];
if ($slots) {
    $slotIds = array_column($slots, 'id');
    $placeholders = implode(',', array_fill(0, count($slotIds), '?'));
    $rStmt = db()->prepare("
        SELECT mr.id, mr.schedule_slot_id, mr.status,
               m.id AS member_id, m.name AS member_name, m.phone
        FROM member_reservations mr
        JOIN members m ON m.id = mr.member_id
        WHERE mr.schedule_slot_id IN ($placeholders) AND mr.class_date = ?
        ORDER BY mr.status, m.name
    ");
    $rStmt->execute([...$slotIds, $selDate]);
    $allRows = $rStmt->fetchAll();
    foreach ($allRows as $row) {
        $rosterBySlot[$row['schedule_slot_id']][] = $row;
    }
}

// ── Nav ──────────────────────────────────────────────────────────────────────
layout_header('Asistencias — ' . date('d/m/Y', strtotime($selDate)), 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'asistencias');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Agenda', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'asistencias');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'members', 'asistencias');
nav_item(BASE_URL . '/pages/admin/asistencias.php', 'Asistencias', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>', 'asistencias', 'asistencias');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'plans', 'asistencias');
layout_footer($user);

// Status config
$statusCfg = [
    'attended' => ['label' => 'Presente', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,.1)', 'icon' => '✅'],
    'absent' => ['label' => 'Ausente', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.1)', 'icon' => '❌'],
    'reserved' => ['label' => 'Reservado', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,.1)', 'icon' => '📋'],
    'cancelled' => ['label' => 'Cancelado', 'color' => '#4b5563', 'bg' => 'rgba(75,85,99,.1)', 'icon' => '🚫'],
];
$dayNames = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
?>

<div class="page-header">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
    </svg>
    <h1 style="font-size:18px;font-weight:700">Asistencias</h1>

    <!-- Date navigator -->
    <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
        <a href="?date=<?= date('Y-m-d', strtotime($selDate . ' -1 day')) ?><?= $sedeId ? '&sede_id=' . $sedeId : '' ?>"
            class="btn btn-sm" style="padding:6px 10px">←</a>
        <form method="GET" style="display:flex;align-items:center;gap:6px">
            <input type="date" name="date" class="input" value="<?= $selDate ?>"
                style="font-size:13px;padding:6px 10px;width:160px" onchange="this.form.submit()">
            <?php if ($sedeId): ?>
                <input type="hidden" name="sede_id" value="<?= $sedeId ?>">
            <?php endif ?>
            <?php if ($user['role'] === 'superadmin' && $gymId): ?>
                <input type="hidden" name="gym_id" value="<?= $gymId ?>">
            <?php endif ?>
        </form>
        <a href="?date=<?= date('Y-m-d', strtotime($selDate . ' +1 day')) ?><?= $sedeId ? '&sede_id=' . $sedeId : '' ?>"
            class="btn btn-sm" style="padding:6px 10px">→</a>
        <?php if ($selDate !== $today): ?>
            <a href="?date=<?= $today ?><?= $sedeId ? '&sede_id=' . $sedeId : '' ?>" class="btn btn-sm btn-primary"
                style="font-size:12px">Hoy</a>
        <?php endif ?>
        <?php if (count($sedes) > 1): ?>
            <select onchange="location.href=this.value" class="input"
                style="font-size:13px;padding:6px 10px;max-width:160px">
                <option value="?date=<?= $selDate ?>" <?= !$sedeId ? ' selected' : '' ?>>Todas las sedes</option>
                <?php foreach ($sedes as $s): ?>
                    <option value="?date=<?= $selDate ?>&sede_id=<?= $s['id'] ?>" <?= $sedeId === (int) $s['id'] ? ' selected' : '' ?>>
                        <?= htmlspecialchars($s['name']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        <?php endif ?>
    </div>
</div>

<!-- Day label -->
<div style="padding:0 28px 12px;font-size:13px;color:var(--gf-text-muted)">
    <?= $dayNames[$dayOfWeek] ?>, <?= date('d/m/Y', strtotime($selDate)) ?>
    <?php if ($selDate === $today): ?>
        <span
            style="margin-left:6px;background:var(--gf-accent);color:#080810;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px">HOY</span>
    <?php endif ?>
</div>

<div class="page-body">
    <?php if (empty($slots)): ?>
        <div class="card" style="text-align:center;padding:48px 24px;color:var(--gf-text-muted)">
            <div style="font-size:36px;margin-bottom:12px">📅</div>
            <div style="font-size:15px;font-weight:600;margin-bottom:6px">Sin clases programadas</div>
            <div style="font-size:13px">No hay clases agendadas para el <?= $dayNames[$dayOfWeek] ?>.</div>
            <a href="<?= BASE_URL ?>/pages/instructor/scheduler.php" class="btn btn-primary btn-sm"
                style="margin-top:16px">Ir a la Agenda</a>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px">
            <?php foreach ($slots as $slot): ?>
                <?php
                $roster = $rosterBySlot[$slot['id']] ?? [];
                $total = count($roster);
                $attending = count(array_filter($roster, fn($r) => $r['status'] === 'attended'));
                $reserved = count(array_filter($roster, fn($r) => $r['status'] === 'reserved'));
                $absent = count(array_filter($roster, fn($r) => $r['status'] === 'absent'));
                $cap = $slot['capacity'];
                ?>
                <div class="card" style="overflow:hidden">
                    <!-- Slot header -->
                    <div
                        style="display:flex;align-items:center;gap:16px;padding:16px 20px;border-bottom:1px solid var(--gf-border)">
                        <div
                            style="font-size:22px;font-weight:800;color:var(--gf-accent);font-family:var(--font-display);min-width:60px">
                            <?= substr($slot['start_time'], 0, 5) ?>
                        </div>
                        <div style="flex:1">
                            <div style="font-size:15px;font-weight:700"><?= htmlspecialchars($slot['class_name'] ?? 'Clase') ?>
                            </div>
                            <div style="font-size:12px;color:var(--gf-text-muted)">
                                <?= substr($slot['start_time'], 0, 5) ?>–<?= substr($slot['end_time'], 0, 5) ?>
                                <?php if ($slot['sala_name']): ?>
                                    · <?= htmlspecialchars($slot['sala_name']) ?>
                                <?php endif ?>
                            </div>
                        </div>
                        <!-- Stats badges -->
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <span
                                style="font-size:12px;font-weight:600;background:rgba(16,185,129,.12);color:#10b981;padding:4px 10px;border-radius:20px">
                                ✅ <?= $attending ?> presentes
                            </span>
                            <?php if ($reserved > 0): ?>
                                <span
                                    style="font-size:12px;font-weight:600;background:rgba(107,114,128,.12);color:var(--gf-text-muted);padding:4px 10px;border-radius:20px">
                                    📋 <?= $reserved ?> por confirmar
                                </span>
                            <?php endif ?>
                            <?php if ($absent > 0): ?>
                                <span
                                    style="font-size:12px;font-weight:600;background:rgba(239,68,68,.12);color:#ef4444;padding:4px 10px;border-radius:20px">
                                    ❌ <?= $absent ?> ausentes
                                </span>
                            <?php endif ?>
                            <?php if ($cap): ?>
                                <span style="font-size:12px;color:var(--gf-text-muted);padding:4px 10px">
                                    👥 <?= $attending + $reserved ?>/<?= $cap ?>
                                </span>
                            <?php endif ?>
                        </div>
                    </div>

                    <!-- Roster -->
                    <?php if (empty($roster)): ?>
                        <div style="padding:20px 24px;font-size:13px;color:var(--gf-text-dim);text-align:center">
                            Sin reservas registradas para esta clase.
                        </div>
                    <?php else: ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0">
                            <?php foreach ($roster as $r): ?>
                                <?php $sc = $statusCfg[$r['status']] ?? $statusCfg['reserved']; ?>
                                <div
                                    style="display:flex;align-items:center;gap:10px;padding:10px 20px;border-bottom:1px solid var(--gf-border)">
                                    <div
                                        style="width:32px;height:32px;border-radius:50%;background:<?= $sc['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">
                                        <?= $sc['icon'] ?>
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <div
                                            style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                            <a href="<?= BASE_URL ?>/pages/admin/member-detail.php?id=<?= $r['member_id'] ?>"
                                                style="color:inherit;text-decoration:none"
                                                onmouseover="this.style.color='var(--gf-accent)'"
                                                onmouseout="this.style.color='inherit'">
                                                <?= htmlspecialchars($r['member_name']) ?>
                                            </a>
                                        </div>
                                        <div style="font-size:11px;color:<?= $sc['color'] ?>;font-weight:500"><?= $sc['label'] ?></div>
                                    </div>
                                    <?php if ($r['status'] === 'reserved'): ?>
                                        <!-- Quick Mark Present -->
                                        <button onclick="markPresent(<?= $r['id'] ?>, this)" title="Marcar presente"
                                            style="width:28px;height:28px;border-radius:50%;border:1px solid rgba(16,185,129,.4);background:rgba(16,185,129,.08);color:#10b981;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s"
                                            onmouseover="this.style.background='rgba(16,185,129,.2)'"
                                            onmouseout="this.style.background='rgba(16,185,129,.08)'">✓</button>
                                    <?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';

    function markPresent(reservationId, btn) {
        if (!btn.dataset.confirming) {
            // First click: ask for confirmation
            btn.dataset.confirming = '1';
            btn.textContent = '¿OK?';
            btn.style.background = 'rgba(245,158,11,.25)';
            btn.style.borderColor = 'rgba(245,158,11,.6)';
            btn.style.color = '#f59e0b';
            // Auto-reset after 3s if no second click
            btn._resetTimer = setTimeout(() => {
                delete btn.dataset.confirming;
                btn.textContent = '✓';
                btn.style.background = 'rgba(16,185,129,.08)';
                btn.style.borderColor = 'rgba(16,185,129,.4)';
                btn.style.color = '#10b981';
            }, 3000);
            return;
        }
        // Second click: confirmed
        clearTimeout(btn._resetTimer);
        delete btn.dataset.confirming;
        btn.disabled = true;
        btn.textContent = '…';
        fetch(`${BASE_URL}/api/reservations.php?id=${reservationId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: 'attended' })
        }).then(r => r.json()).then(json => {
            if (json.success) location.reload();
            else { alert(json.error || 'Error'); btn.disabled = false; btn.textContent = '✓'; }
        }).catch(() => { alert('Error de conexión'); btn.disabled = false; btn.textContent = '✓'; });
    }
</script>