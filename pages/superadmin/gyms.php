<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/plans.php';

$user = requireAuth('superadmin');

$gymnList = db()->query(
    "SELECT g.*, 
            COUNT(DISTINCT u.id) as user_count, 
            COUNT(DISTINCT s.id) as sala_count,
            gs.plan, gs.status as sub_status,
            gs.current_period_end,
            gs.trial_ends_at,
            gs.extra_salas,
            gs.price_ars,
            CASE
                WHEN gs.id IS NULL THEN 'no_sub'
                WHEN gs.status = 'suspended' THEN 'suspended'
                WHEN gs.current_period_end < CURDATE() THEN 'expired'
                WHEN gs.current_period_end <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expiring'
                ELSE 'active'
            END as sub_computed
     FROM gyms g
     LEFT JOIN users u ON u.gym_id = g.id AND u.role != 'superadmin'
     LEFT JOIN salas s ON s.gym_id = g.id
     LEFT JOIN gym_subscriptions gs ON gs.gym_id = g.id
     GROUP BY g.id ORDER BY g.name"
)->fetchAll();

layout_header('Gimnasios — SuperAdmin', 'superadmin', $user);
nav_section('Super Admin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>', 'gyms', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/users.php', 'Usuarios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'users', 'superadmin');
layout_footer($user);
?>

<style>
    .sub-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding: 3px 9px;
        border-radius: 999px;
    }

    .sub-active {
        background: rgba(16, 185, 129, .12);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, .25);
    }

    .sub-expiring {
        background: rgba(245, 158, 11, .12);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, .25);
    }

    .sub-expired {
        background: rgba(239, 68, 68, .12);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, .25);
    }

    .sub-suspended,
    .sub-no_sub {
        background: rgba(107, 114, 128, .12);
        color: #6b7280;
        border: 1px solid rgba(107, 114, 128, .25);
    }

    .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .dot-active {
        background: #10b981;
    }

    .dot-expiring {
        background: #f59e0b;
    }

    .dot-expired {
        background: #ef4444;
    }

    .dot-suspended,
    .dot-no_sub {
        background: #6b7280;
    }

    .plan-badge {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        padding: 2px 8px;
        border-radius: 6px;
    }

    .plan-instructor {
        background: rgba(99, 102, 241, .15);
        color: #818cf8;
    }

    .plan-gimnasio {
        background: rgba(16, 185, 129, .15);
        color: #10b981;
    }

    .plan-centro {
        background: rgba(245, 158, 11, .15);
        color: #f59e0b;
    }

    .plan-trial {
        background: rgba(107, 114, 128, .12);
        color: #9ca3af;
    }

    .usage-bar-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .usage-bar {
        flex: 1;
        height: 4px;
        border-radius: 4px;
        background: rgba(255, 255, 255, .08);
        overflow: hidden;
    }

    .usage-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width .3s;
    }

    .usage-ok {
        background: #10b981;
    }

    .usage-warn {
        background: #f59e0b;
    }

    .usage-full {
        background: #ef4444;
    }

    .price-preview {
        font-size: 13px;
        font-weight: 700;
        color: var(--gf-text-muted);
        text-align: right;
        margin-top: 4px;
    }

    .price-preview strong {
        color: var(--gf-accent);
    }

    .addon-note {
        font-size: 11px;
        color: var(--gf-text-muted);
        margin-top: 4px;
    }
</style>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Gimnasios</h1>
    <button class="btn btn-primary ml-auto" onclick="openNewGymModal()">+ Nuevo Gimnasio</button>
</div>

<div class="page-body">
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Plan</th>
                        <th>Salas</th>
                        <th>Usuarios</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gymnList as $g):
                        $sc = $g['sub_computed'] ?? 'no_sub';
                        $plan = $g['plan'] ?? 'trial';
                        $extraSalas = (int) ($g['extra_salas'] ?? 0);
                        $limits = getPlanLimits($plan, $extraSalas);
                        $salaCount = (int) $g['sala_count'];
                        $userCount = (int) $g['user_count'];
                        $salaPct = $limits['salas'] > 0 ? min(100, round($salaCount / $limits['salas'] * 100)) : 0;
                        $salaBarClass = $salaPct >= 100 ? 'usage-full' : ($salaPct >= 80 ? 'usage-warn' : 'usage-ok');

                        $planBadgeClass = match ($plan) {
                            'instructor' => 'plan-instructor',
                            'gimnasio' => 'plan-gimnasio',
                            'centro' => 'plan-centro',
                            default => 'plan-trial',
                        };

                        $labels = [
                            'active' => 'Activo',
                            'expiring' => 'Por vencer',
                            'expired' => 'Vencido',
                            'suspended' => 'Suspendido',
                            'no_sub' => 'Sin suscripción',
                        ];
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div
                                        style="width:32px;height:32px;border-radius:8px;background:<?php echo htmlspecialchars($g['primary_color']) ?>26;display:flex;align-items:center;justify-content:center;font-weight:700;color:<?php echo htmlspecialchars($g['primary_color']) ?>;font-size:12px">
                                        <?php echo strtoupper(substr($g['name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($g['name']) ?></strong>
                                        <div style="font-size:11px;color:var(--gf-text-muted)">
                                            <?php echo htmlspecialchars($g['slug']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="plan-badge <?php echo $planBadgeClass ?>">
                                    <?php echo $limits['label'] ?>
                                </span>
                                <?php if ($extraSalas > 0): ?>
                                    <div style="font-size:10px;color:var(--gf-text-muted);margin-top:2px">
                                        +<?php echo $extraSalas ?> sala<?php echo $extraSalas > 1 ? 's' : '' ?> add-on</div>
                                <?php endif ?>
                            </td>
                            <td style="min-width:90px">
                                <div class="usage-bar-wrap">
                                    <span style="font-size:12px;font-weight:600;white-space:nowrap">
                                        <?php echo $salaCount ?>/<?php echo $limits['salas'] ?>
                                    </span>
                                    <div class="usage-bar">
                                        <div class="usage-bar-fill <?php echo $salaBarClass ?>"
                                            style="width:<?php echo $salaPct ?>%"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $userCount ?></td>
                            <td style="font-size:13px">
                                <?php if ($g['current_period_end']): ?>
                                    <?php
                                    $d = new DateTime($g['current_period_end']);
                                    echo $d->format('d/m/Y');
                                    $daysLeft = (new DateTime())->diff($d)->days * ((new DateTime()) <= $d ? 1 : -1);
                                    if ($daysLeft >= 0 && $daysLeft <= 14):
                                        ?>
                                        <span style="font-size:11px;color:#f59e0b;margin-left:6px">(<?php echo $daysLeft ?>d)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--gf-text-muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="sub-badge sub-<?php echo $sc ?>">
                                    <span class="dot dot-<?php echo $sc ?>"></span>
                                    <?php echo $labels[$sc] ?? $sc ?>
                                </span>
                            </td>
                            <td class="flex gap-2">
                                <a href="<?php echo BASE_URL ?>/pages/admin/dashboard.php?gym_id=<?php echo $g['id'] ?>"
                                    class="btn btn-ghost btn-sm">Panel</a>
                                <button class="btn btn-secondary btn-sm" onclick="openSubModal(<?php echo htmlspecialchars(json_encode([
                                    'id' => $g['id'],
                                    'name' => $g['name'],
                                    'plan' => $g['plan'] ?? 'trial',
                                    'status' => $g['sub_status'] ?? 'active',
                                    'end' => $g['current_period_end'] ?? '',
                                    'extra_salas' => (int) ($g['extra_salas'] ?? 0),
                                    'notes' => '',
                                ])) ?>)">Ciclo</button>
                                <button class="btn btn-secondary btn-sm"
                                    onclick="openPayModal(<?php echo $g['id'] ?>, '<?php echo htmlspecialchars(addslashes($g['name'])) ?>')">💳
                                    Pago</button>
                                <button class="btn <?php echo $g['active'] ? 'btn-danger' : 'btn-primary' ?> btn-sm"
                                    onclick="toggleGym(<?php echo $g['id'] ?>, <?php echo (int) !$g['active'] ?>)">
                                    <?php echo $g['active'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── New Gym Modal ────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="gym-modal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3 class="modal-title">Nuevo Gimnasio</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')"><svg
                    width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <form onsubmit="createGym(event)">
            <div class="param-row">
                <div class="form-group"><label class="form-label">Nombre</label><input class="form-control" id="g-name"
                        required placeholder="CrossFit Palermo"></div>
                <div class="form-group"><label class="form-label">Slug (URL)</label><input class="form-control"
                        id="g-slug" required placeholder="crossfit-palermo" pattern="[a-z0-9\-]+"></div>
            </div>
            <div class="param-row">
                <div class="form-group"><label class="form-label">Color Primario</label><input type="color"
                        class="form-control" id="g-primary" value="#00f5d4"></div>
                <div class="form-group"><label class="form-label">Color Secundario</label><input type="color"
                        class="form-control" id="g-secondary" value="#ff6b35"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Plan inicial</label>
                <select class="form-control" id="g-plan">
                    <option value="instructor">Instructor — Trial 30 días</option>
                    <option value="gimnasio">Gimnasio</option>
                    <option value="centro">Centro</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Crear Gimnasio</button>
        </form>
    </div>
</div>

<!-- ── Subscription / Cycle Modal ─────────────────────────────────────────── -->
<div class="modal-overlay" id="sub-modal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h3 class="modal-title">Ciclo de facturación — <span id="sub-gym-name"></span></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')"><svg
                    width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <div style="display:flex;flex-direction:column;gap:14px">

            <!-- Plan selector -->
            <div class="param-row">
                <div class="form-group">
                    <label class="form-label">Plan</label>
                    <select class="form-control" id="sub-plan" onchange="updatePricePreview()">
                        <option value="trial">Trial (gratis)</option>
                        <option value="instructor">Instructor — $12.000/mes</option>
                        <option value="gimnasio">Gimnasio — $29.000/mes</option>
                        <option value="centro">Centro — $55.000/mes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select class="form-control" id="sub-status">
                        <option value="active">Activo</option>
                        <option value="suspended">Suspendido</option>
                        <option value="expired">Expirado</option>
                    </select>
                </div>
            </div>

            <!-- Extra salas add-on -->
            <div class="form-group">
                <label class="form-label">Add-on: Salas extra <span
                        style="font-weight:400;color:var(--gf-text-muted)">(+$9.000 ARS c/u)</span></label>
                <input type="number" class="form-control" id="sub-extra-salas" min="0" max="20" value="0"
                    oninput="updatePricePreview()">
                <div class="addon-note" id="sub-sala-info">Salas totales: —</div>
            </div>

            <!-- Price preview -->
            <div class="price-preview" id="sub-price-preview">
                Total mensual: <strong>—</strong>
            </div>

            <!-- Date -->
            <div class="form-group">
                <label class="form-label">Vencimiento del ciclo</label>
                <input type="date" class="form-control" id="sub-end">
            </div>
            <div style="display:flex;gap:8px">
                <button class="btn btn-secondary" style="flex:1" onclick="extendDays(30)">+ 30 días</button>
                <button class="btn btn-secondary" style="flex:1" onclick="extendDays(90)">+ 3 meses</button>
                <button class="btn btn-secondary" style="flex:1" onclick="extendDays(365)">+ 1 año</button>
            </div>

            <!-- Notes -->
            <div class="form-group">
                <label class="form-label">Notas internas</label>
                <textarea class="form-control" id="sub-notes" rows="2"
                    placeholder="Pagado por transferencia el 15/02..."></textarea>
            </div>
            <button class="btn btn-primary" onclick="saveSub()">Guardar cambios</button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    let _subGymId = null;

    const PLAN_PRICES = { trial: 0, instructor: 12000, gimnasio: 29000, centro: 55000 };
    const PLAN_SALAS = { trial: 1, instructor: 1, gimnasio: 3, centro: 8 };
    const ADDON_PRICE = 9000;

    function openNewGymModal() {
        document.getElementById('gym-modal').classList.add('open');
    }

    async function toggleGym(id, newActive) {
        await GF.put(`${window.GF_BASE}/api/gyms.php?id=${id}`, { active: newActive });
        location.reload();
    }

    async function createGym(e) {
        e.preventDefault();
        const plan = document.getElementById('g-plan').value;
        const data = {
            name: document.getElementById('g-name').value,
            slug: document.getElementById('g-slug').value,
            primary_color: document.getElementById('g-primary').value,
            secondary_color: document.getElementById('g-secondary').value,
        };
        const res = await GF.post(window.GF_BASE + '/api/gyms.php', data);
        if (res && res.id) {
            // Set plan to the selected value (trial is default from register but we override)
            await GF.put(`${window.GF_BASE}/api/subscriptions.php?gym_id=${res.id}`, {
                plan: plan,
                status: 'active',
            });
        }
        location.reload();
    }

    document.getElementById('g-name').addEventListener('input', function () {
        document.getElementById('g-slug').value = this.value.toLowerCase()
            .replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
    });

    function openSubModal(gym) {
        _subGymId = gym.id;
        document.getElementById('sub-gym-name').textContent = gym.name;
        document.getElementById('sub-plan').value = gym.plan || 'instructor';
        document.getElementById('sub-status').value = gym.status || 'active';
        document.getElementById('sub-end').value = gym.end || '';
        document.getElementById('sub-extra-salas').value = gym.extra_salas ?? 0;
        document.getElementById('sub-notes').value = gym.notes || '';
        updatePricePreview();
        document.getElementById('sub-modal').classList.add('open');
    }

    function updatePricePreview() {
        const plan = document.getElementById('sub-plan').value;
        const extras = Math.max(0, parseInt(document.getElementById('sub-extra-salas').value) || 0);
        const base = PLAN_PRICES[plan] ?? 0;
        const addon = extras * ADDON_PRICE;
        const total = base + addon;
        const salas = (PLAN_SALAS[plan] ?? 1) + extras;

        const fmt = n => n.toLocaleString('es-AR') + ' ARS';
        document.getElementById('sub-price-preview').innerHTML =
            `Total mensual: <strong>${fmt(total)}</strong>` +
            (addon > 0 ? ` <span style="font-size:11px;color:var(--gf-text-muted)">(base ${fmt(base)} + add-on ${fmt(addon)})</span>` : '');

        document.getElementById('sub-sala-info').textContent =
            `Salas totales permitidas: ${salas} (${PLAN_SALAS[plan] ?? 1} base${extras > 0 ? ' + ' + extras + ' extra' : ''})`;
    }

    function extendDays(days) {
        const cur = document.getElementById('sub-end').value;
        const base = cur && cur >= new Date().toISOString().slice(0, 10) ? new Date(cur) : new Date();
        base.setDate(base.getDate() + days);
        document.getElementById('sub-end').value = base.toISOString().slice(0, 10);
        document.getElementById('sub-status').value = 'active';
    }

    async function saveSub() {
        if (!_subGymId) return;
        const plan = document.getElementById('sub-plan').value;
        const status = document.getElementById('sub-status').value;
        const periodEnd = document.getElementById('sub-end').value;
        const extras = parseInt(document.getElementById('sub-extra-salas').value) || 0;
        const notes = document.getElementById('sub-notes').value;
        const price = (PLAN_PRICES[plan] ?? 0) + extras * ADDON_PRICE;

        await GF.put(`${window.GF_BASE}/api/subscriptions.php?gym_id=${_subGymId}`, {
            plan, status, current_period_end: periodEnd, extra_salas: extras, notes,
        });

        // Auto-log a renewal record so it appears in billing history
        if (status === 'active' && periodEnd) {
            const today = new Date().toISOString().slice(0, 10);
            const fd = new FormData();
            fd.append('gym_id', _subGymId);
            fd.append('event_type', 'renewal');
            fd.append('plan', plan);
            fd.append('period_start', today);
            fd.append('period_end', periodEnd);
            fd.append('amount_ars', price);
            fd.append('extra_salas', extras);
            fd.append('notes', notes || 'Registrado desde panel Superadmin');
            await fetch(`${window.GF_BASE}/api/renewals.php`, { method: 'POST', body: fd });
        }

        document.getElementById('sub-modal').classList.remove('open');
        location.reload();
    }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });

    // Init price preview on load
    updatePricePreview();
</script>

<!-- ── Registrar pago modal ────────────────────────────────────────────────── -->
<div class="modal-overlay" id="pay-modal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3 class="modal-title">Registrar pago — <span id="pay-gym-name"></span></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form id="pay-form" onsubmit="submitPayment(event)" enctype="multipart/form-data"
            style="display:flex;flex-direction:column;gap:14px">
            <input type="hidden" id="pay-gym-id" name="gym_id" value="">

            <div class="param-row">
                <div class="form-group">
                    <label class="form-label">Tipo de evento</label>
                    <select class="form-control" name="event_type" id="pay-event-type">
                        <option value="renewal">🔄 Renovación</option>
                        <option value="activation">🟢 Activación</option>
                        <option value="plan_change">⬆️ Cambio de plan</option>
                        <option value="credit">🎁 Crédito / bono</option>
                        <option value="expiry">🔴 Vencimiento</option>
                        <option value="suspension">⏸️ Suspensión</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Plan</label>
                    <select class="form-control" name="plan" id="pay-plan">
                        <option value="instructor">Instructor</option>
                        <option value="gimnasio">Gimnasio</option>
                        <option value="centro">Centro</option>
                        <option value="trial">Trial</option>
                    </select>
                </div>
            </div>

            <div class="param-row">
                <div class="form-group">
                    <label class="form-label">Inicio del ciclo</label>
                    <input type="date" class="form-control" name="period_start" id="pay-start">
                </div>
                <div class="form-group">
                    <label class="form-label">Fin del ciclo</label>
                    <input type="date" class="form-control" name="period_end" id="pay-end">
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-secondary btn-sm" style="flex:1" onclick="payQuickDate(30)">+ 30
                    días</button>
                <button type="button" class="btn btn-secondary btn-sm" style="flex:1" onclick="payQuickDate(90)">+ 3
                    meses</button>
                <button type="button" class="btn btn-secondary btn-sm" style="flex:1" onclick="payQuickDate(365)">+ 1
                    año</button>
            </div>

            <div class="param-row">
                <div class="form-group">
                    <label class="form-label">Monto cobrado (ARS)</label>
                    <input type="number" class="form-control" name="amount_ars" id="pay-amount" min="0"
                        placeholder="29000">
                </div>
                <div class="form-group">
                    <label class="form-label">Salas extra add-on</label>
                    <input type="number" class="form-control" name="extra_salas" min="0" max="20" value="0">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Notas internas</label>
                <textarea class="form-control" name="notes" rows="2"
                    placeholder="Pagado por transferencia el 15/03..."></textarea>
            </div>

            <!-- Invoice upload -->
            <div class="form-group">
                <label class="form-label">📄 Factura / comprobante <span
                        style="font-weight:400;color:var(--gf-text-muted)">(PDF, JPG, PNG — máx. 10 MB)</span></label>
                <label id="pay-invoice-drop"
                    style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;border:2px dashed rgba(255,255,255,.12);border-radius:10px;padding:20px;cursor:pointer;transition:.2s;text-align:center"
                    onclick="document.getElementById('pay-invoice-file').click()">
                    <span style="font-size:24px">📎</span>
                    <span id="pay-invoice-label" style="font-size:12px;color:var(--gf-text-muted)">Hacé click o arrastrá
                        el archivo aquí</span>
                    <input type="file" id="pay-invoice-file" name="invoice" accept=".pdf,.jpg,.jpeg,.png,.webp"
                        style="display:none" onchange="onPayFileSelect(this)">
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%" id="pay-submit-btn">Guardar
                registro</button>
        </form>
    </div>
</div>

<script>
    let _payGymId = null;

    function openPayModal(gymId, gymName) {
        _payGymId = gymId;
        document.getElementById('pay-gym-id').value = gymId;
        document.getElementById('pay-gym-name').textContent = gymName;
        // Pre-fill plan from current row
        document.getElementById('pay-form').reset();
        document.getElementById('pay-gym-id').value = gymId;
        // Dates: start = today, end = today + 30d
        const today = new Date(); const end = new Date();
        end.setDate(end.getDate() + 30);
        const fmt = d => d.toISOString().slice(0, 10);
        document.getElementById('pay-start').value = fmt(today);
        document.getElementById('pay-end').value = fmt(end);
        document.getElementById('pay-invoice-label').textContent = 'Hacé click o arrastrá el archivo aquí';
        document.getElementById('pay-modal').classList.add('open');
    }

    function payQuickDate(days) {
        const cur = document.getElementById('pay-end').value;
        const base = cur ? new Date(cur) : new Date();
        base.setDate(base.getDate() + days);
        document.getElementById('pay-end').value = base.toISOString().slice(0, 10);
    }

    function onPayFileSelect(input) {
        const f = input.files[0];
        document.getElementById('pay-invoice-label').textContent = f ? f.name : 'Hacé click o arrastrá el archivo aquí';
    }

    // Drag & drop
    const payDrop = document.getElementById('pay-invoice-drop');
    payDrop.addEventListener('dragover', e => { e.preventDefault(); payDrop.style.borderColor = 'rgba(99,102,241,.5)'; });
    payDrop.addEventListener('dragleave', () => { payDrop.style.borderColor = 'rgba(255,255,255,.12)'; });
    payDrop.addEventListener('drop', e => {
        e.preventDefault(); payDrop.style.borderColor = 'rgba(255,255,255,.12)';
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer(); dt.items.add(file);
            document.getElementById('pay-invoice-file').files = dt.files;
            onPayFileSelect(document.getElementById('pay-invoice-file'));
        }
    });

    async function submitPayment(e) {
        e.preventDefault();
        const btn = document.getElementById('pay-submit-btn');
        btn.disabled = true; btn.textContent = 'Guardando...';
        try {
            const fd = new FormData(document.getElementById('pay-form'));
            const res = await fetch(window.GF_BASE + '/api/renewals.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                showToast('✅ Pago registrado con éxito', 'success');
                document.getElementById('pay-modal').classList.remove('open');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('Error: ' + (data.error || 'desconocido'), 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        } finally {
            btn.disabled = false; btn.textContent = 'Guardar registro';
        }
    }
</script>

<?php layout_end(); ?>