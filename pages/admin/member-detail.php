<?php
/**
 * GymFlow CRM — Member Detail
 * ?id=X
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin');
$gymId = (int) $user['gym_id'];
$memberId = (int) ($_GET['id'] ?? 0);
if (!$memberId) {
    header('Location: ' . BASE_URL . '/pages/admin/members.php');
    exit;
}

// Fetch member
$mStmt = db()->prepare("SELECT * FROM members WHERE id = ? AND gym_id = ?");
$mStmt->execute([$memberId, $gymId]);
$member = $mStmt->fetch();
if (!$member) {
    echo 'Alumno no encontrado';
    exit;
}

// Active membership
$actStmt = db()->prepare("
    SELECT mm.*, mp.name AS plan_name
    FROM member_memberships mm
    LEFT JOIN membership_plans mp ON mp.id = mm.plan_id
    WHERE mm.member_id = ? AND mm.gym_id = ? AND mm.end_date >= CURDATE()
    ORDER BY mm.end_date DESC LIMIT 1
");
$actStmt->execute([$memberId, $gymId]);
$activeMembership = $actStmt->fetch();

// Plans for assign modal
$plansStmt = db()->prepare("SELECT id, name, price, currency, duration_days, sessions_limit FROM membership_plans WHERE gym_id = ? AND active = 1 ORDER BY price");
$plansStmt->execute([$gymId]);
$plans = $plansStmt->fetchAll();

layout_header('Perfil — ' . $member['name'], 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'admin', 'admin');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'admin', 'admin');
layout_footer($user);
?>

<div class="page-header">
    <div class="flex align-center gap-3">
        <a href="<?php echo BASE_URL ?>/pages/admin/members.php"
            style="color:var(--gf-text-muted);text-decoration:none;font-size:20px">←</a>
        <div>
            <h1 style="font-size:20px;font-weight:700">
                <?php echo htmlspecialchars($member['name']) ?>
            </h1>
            <div style="font-size:12px;color:var(--gf-text-muted)">
                <?php echo htmlspecialchars($member['email'] ?: $member['phone'] ?: 'Sin contacto') ?>
            </div>
        </div>
    </div>
    <div class="flex gap-2 ml-auto">
        <button class="btn btn-secondary" onclick="openEditModal()">✏️ Editar</button>
        <button class="btn btn-primary" onclick="openAssignModal()">+ Asignar membresía</button>
    </div>
</div>

<div class="page-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

        <!-- LEFT: Membership + History -->
        <div class="flex flex-col gap-4">

            <!-- Active Membership -->
            <div class="card" style="padding:20px">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;color:var(--gf-text-muted)">MEMBRESÍA
                    ACTIVA</h3>
                <?php if ($activeMembership): ?>
                    <?php
                    $payColors = ['paid' => '#10b981', 'pending' => '#f59e0b', 'partial' => '#f59e0b', 'overdue' => '#ef4444'];
                    $payLabels = ['paid' => 'Pagado', 'pending' => 'Pendiente', 'partial' => 'Parcial', 'overdue' => 'Deudor'];
                    $pc = $payColors[$activeMembership['payment_status']] ?? '#6b7280';
                    $pl = $payLabels[$activeMembership['payment_status']] ?? $activeMembership['payment_status'];
                    $daysLeft = (int) round((strtotime($activeMembership['end_date']) - time()) / 86400);
                    ?>
                    <div style="font-size:22px;font-weight:800;margin-bottom:4px">
                        <?php echo htmlspecialchars($activeMembership['plan_name'] ?? 'Plan libre') ?>
                    </div>
                    <div style="font-size:13px;color:var(--gf-text-muted);margin-bottom:12px">
                        <?php echo date('d/m/Y', strtotime($activeMembership['start_date'])) ?> →
                        <?php echo date('d/m/Y', strtotime($activeMembership['end_date'])) ?>
                        <span
                            style="margin-left:8px;font-weight:600;color:<?php echo $daysLeft <= 7 ? '#ef4444' : 'var(--gf-text-muted)' ?>">
                            (
                            <?php echo $daysLeft ?> días restantes)
                        </span>
                    </div>
                    <div class="flex gap-3 mb-3">
                        <div>
                            <div style="font-size:11px;color:var(--gf-text-muted)">Clases usadas</div>
                            <div style="font-weight:700">
                                <?php echo $activeMembership['sessions_used'] ?>
                                <?php echo $activeMembership['sessions_limit'] ? '/' . $activeMembership['sessions_limit'] : '' ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--gf-text-muted)">Monto</div>
                            <div style="font-weight:700">$
                                <?php echo number_format($activeMembership['amount_due'], 0, ',', '.') ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--gf-text-muted)">Pagado</div>
                            <div style="font-weight:700">$
                                <?php echo number_format($activeMembership['amount_paid'], 0, ',', '.') ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--gf-text-muted)">Estado pago</div>
                            <div style="font-weight:700;color:<?php echo $pc ?>">
                                <?php echo $pl ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($activeMembership['payment_status'] !== 'paid'): ?>
                        <button class="btn btn-primary" style="font-size:12px"
                            onclick="markPaid(<?php echo $activeMembership['id'] ?>, <?php echo $activeMembership['amount_due'] ?>)">
                            ✅ Registrar pago completo
                        </button>
                    <?php endif ?>
                <?php else: ?>
                    <div style="color:var(--gf-text-muted);text-align:center;padding:24px 0">
                        <div style="font-size:32px;margin-bottom:8px">📋</div>
                        Sin membresía activa
                        <div style="margin-top:12px">
                            <button class="btn btn-primary btn-sm" onclick="openAssignModal()">Asignar membresía</button>
                        </div>
                    </div>
                <?php endif ?>
            </div>

            <!-- Membership History -->
            <div class="card" style="padding:20px">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--gf-text-muted)">HISTORIAL DE
                    MEMBRESÍAS</h3>
                <div id="membership-history">
                    <div style="color:var(--gf-text-muted);font-size:13px">Cargando...</div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Member Info + Attendance -->
        <div class="flex flex-col gap-4">

            <!-- Member Info -->
            <div class="card" style="padding:20px">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--gf-text-muted)">DATOS DEL
                    ALUMNO</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
                    <div><span style="color:var(--gf-text-muted)">Nombre:</span> <strong>
                            <?php echo htmlspecialchars($member['name']) ?>
                        </strong></div>
                    <div><span style="color:var(--gf-text-muted)">Email:</span>
                        <?php echo htmlspecialchars($member['email'] ?: '—') ?>
                    </div>
                    <div><span style="color:var(--gf-text-muted)">Teléfono:</span>
                        <?php echo htmlspecialchars($member['phone'] ?: '—') ?>
                    </div>
                    <div><span style="color:var(--gf-text-muted)">Nacimiento:</span>
                        <?php echo $member['birth_date'] ? date('d/m/Y', strtotime($member['birth_date'])) : '—' ?>
                    </div>
                    <div style="grid-column:1/-1"><span style="color:var(--gf-text-muted)">Notas:</span>
                        <?php echo htmlspecialchars($member['notes'] ?: '—') ?>
                    </div>
                </div>
            </div>

            <!-- Attendance Timeline -->
            <div class="card" style="padding:20px;flex:1">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--gf-text-muted)">ÚLTIMAS
                    ASISTENCIAS</h3>
                <div id="attendance-list">
                    <div style="color:var(--gf-text-muted);font-size:13px">Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Membership Modal -->
<div class="modal-overlay" id="assign-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Asignar membresía</h3>
            <button class="modal-close" onclick="closeModal('assign-modal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="assign-form" onsubmit="saveMembership(event)">
                <div class="form-group">
                    <label class="form-label">Plan</label>
                    <select name="plan_id" class="input" id="plan-select" onchange="fillPlanDefaults()">
                        <option value="">— Sin plan (manual) —</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?php echo $p['id'] ?>" data-price="<?php echo $p['price'] ?>"
                                data-days="<?php echo $p['duration_days'] ?>" data-currency="<?php echo $p['currency'] ?>">
                                <?php echo htmlspecialchars($p['name']) ?> —
                                <?php echo $p['currency'] ?> $
                                <?php echo number_format($p['price'], 0, ',', '.') ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="flex gap-3">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Inicio</label>
                        <input type="date" name="start_date" class="input" value="<?php echo date('Y-m-d') ?>">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Vencimiento</label>
                        <input type="date" name="end_date" class="input" id="end-date-field">
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Monto</label>
                        <input type="number" name="amount_due" class="input" min="0" step="100" id="amount-due-field"
                            placeholder="0">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Pagado</label>
                        <input type="number" name="amount_paid" class="input" min="0" step="100" placeholder="0">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Método de pago</label>
                    <select name="payment_method" class="input">
                        <option value="">—</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="mercadopago">MercadoPago</option>
                        <option value="tarjeta">Tarjeta</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <input type="text" name="notes" class="input" placeholder="Opcional">
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" class="btn btn-secondary flex-1"
                        onclick="closeModal('assign-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary flex-1">Asignar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Editar alumno</h3>
            <button class="modal-close" onclick="closeModal('edit-modal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="edit-form" onsubmit="saveMember(event)">
                <div class="form-group"><label class="form-label">Nombre *</label><input type="text" name="name"
                        class="input" value="<?php echo htmlspecialchars($member['name']) ?>" required></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email"
                        class="input" value="<?php echo htmlspecialchars($member['email'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Teléfono</label><input type="tel" name="phone"
                        class="input" value="<?php echo htmlspecialchars($member['phone'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Nacimiento</label><input type="date" name="birth_date"
                        class="input" value="<?php echo $member['birth_date'] ?? '' ?>"></div>
                <div class="form-group"><label class="form-label">Notas</label><textarea name="notes" class="input"
                        rows="2"><?php echo htmlspecialchars($member['notes'] ?? '') ?></textarea></div>
                <div class="flex gap-2 mt-4">
                    <button type="button" class="btn btn-secondary flex-1"
                        onclick="closeModal('edit-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary flex-1">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?php echo BASE_URL ?>';
    const MEMBER_ID = <?php echo $memberId ?>;

    function openAssignModal() { document.getElementById('assign-modal').classList.add('open'); }
    function openEditModal() { document.getElementById('edit-modal').classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    function fillPlanDefaults() {
        const sel = document.getElementById('plan-select');
        const opt = sel.options[sel.selectedIndex];
        if (!opt.value) return;
        const price = opt.dataset.price;
        const days = parseInt(opt.dataset.days);
        const start = document.querySelector('[name=start_date]').value || '<?php echo date('Y-m-d') ?>';
        const end = new Date(start);
        end.setDate(end.getDate() + days);
        document.getElementById('end-date-field').value = end.toISOString().slice(0, 10);
        document.getElementById('amount-due-field').value = price;
    }

    async function saveMembership(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target).entries());
        data.member_id = MEMBER_ID;
        const res = await fetch(`${BASE_URL}/api/member-memberships.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success || json.id) { location.reload(); }
        else alert(json.error || 'Error');
    }

    async function saveMember(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target).entries());
        const res = await fetch(`${BASE_URL}/api/members.php?id=${MEMBER_ID}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) location.reload();
        else alert(json.error || 'Error');
    }

    async function markPaid(membershipId, amountDue) {
        if (!confirm('¿Marcar como pagado?')) return;
        await fetch(`${BASE_URL}/api/member-memberships.php?id=${membershipId}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount_paid: amountDue, payment_status: 'paid', payment_date: new Date().toISOString().slice(0, 10) })
        });
        location.reload();
    }

    async function loadMembershipHistory() {
        const res = await fetch(`${BASE_URL}/api/member-memberships.php?member_id=${MEMBER_ID}`);
        const list = await res.json();
        const el = document.getElementById('membership-history');
        if (!list.length) { el.innerHTML = '<div style="color:var(--gf-text-muted);font-size:13px">Sin historial</div>'; return; }
        el.innerHTML = list.map(m => {
            const expired = new Date(m.end_date) < new Date();
            return `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--gf-border)">
            <div>
                <div style="font-size:13px;font-weight:600">${escHtml(m.plan_name || 'Sin plan')}</div>
                <div style="font-size:11px;color:var(--gf-text-muted)">${fmtDate(m.start_date)} → ${fmtDate(m.end_date)}</div>
            </div>
            <div style="text-align:right">
                <div style="font-size:12px;font-weight:600">$${Number(m.amount_paid).toLocaleString('es-AR')}</div>
                <div style="font-size:10px;color:${expired ? '#6b7280' : 'var(--gf-text-muted)'}">${expired ? 'Vencido' : 'Vigente'}</div>
            </div>
        </div>`;
        }).join('');
    }

    async function loadAttendances() {
        const res = await fetch(`${BASE_URL}/api/attendances.php?member_id=${MEMBER_ID}&limit=15`);
        const list = await res.json();
        const el = document.getElementById('attendance-list');
        if (!list.length) { el.innerHTML = '<div style="color:var(--gf-text-muted);font-size:13px">Sin asistencias registradas</div>'; return; }
        el.innerHTML = list.map(a => `
        <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid var(--gf-border)">
            <div style="width:8px;height:8px;border-radius:50%;background:#10b981;flex-shrink:0"></div>
            <div style="flex:1">
                <div style="font-size:12px;font-weight:600">${escHtml(a.session_name || 'Clase libre')}</div>
                <div style="font-size:11px;color:var(--gf-text-muted)">${fmtDateTime(a.checked_in_at)}</div>
            </div>
            <div style="font-size:10px;color:var(--gf-text-muted)">${a.method}</div>
        </div>
    `).join('');
    }

    function fmtDate(d) { return d ? new Date(d).toLocaleDateString('es-AR') : '—'; }
    function fmtDateTime(d) { return d ? new Date(d).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'; }
    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    loadMembershipHistory();
    loadAttendances();
</script>