<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin');
$gymId = (int) ($user['gym_id'] ?? $_GET['gym_id'] ?? 0);
if (!$gymId) {
    header('Location: ' . BASE_URL . '/pages/superadmin/dashboard.php');
    exit;
}

$gym = db()->prepare("SELECT * FROM gyms WHERE id = ?");
$gym->execute([$gymId]);
$gym = $gym->fetch();

// Current subscription
$sub = getGymSubscription($gymId);

// Renewal history
$histStmt = db()->prepare("
    SELECT r.*, u.name as created_by_name
    FROM subscription_renewals r
    LEFT JOIN users u ON u.id = r.created_by
    WHERE r.gym_id = ?
    ORDER BY r.created_at DESC
");
$histStmt->execute([$gymId]);
$history = $histStmt->fetchAll();

layout_header('Facturación — ' . $gym['name'], 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'billing');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Instructor', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>', 'instructor', 'billing');
if ($user['role'] === 'superadmin')
    nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Super Admin', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'superadmin', 'billing');
layout_footer($user);
?>

<?php
// Helpers
$planLabel = ['trial' => 'Prueba gratuita', 'monthly' => 'Mensual', 'annual' => 'Anual'];
$statusLabel = ['active' => 'Activo', 'expired' => 'Vencido', 'suspended' => 'Suspendido'];

$today = new DateTime();
$periodEnd = $sub && $sub['current_period_end'] ? new DateTime($sub['current_period_end']) : null;
$daysLeft = $periodEnd ? (int) $today->diff($periodEnd)->days * ($today <= $periodEnd ? 1 : -1) : null;

$statusColor = '#10b981'; // green
if (!$sub || $sub['status'] === 'suspended')
    $statusColor = '#6b7280';
elseif ($daysLeft !== null && $daysLeft < 0)
    $statusColor = '#ef4444';
elseif ($daysLeft !== null && $daysLeft <= 7)
    $statusColor = '#f59e0b';
?>

<style>
    .billing-card {
        background: var(--gf-surface);
        border: 1px solid var(--gf-border);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
    }

    .billing-card h2 {
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 20px;
        color: var(--gf-text-muted);
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--gf-border);
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-size: 13px;
        color: var(--gf-text-muted);
    }

    .info-value {
        font-size: 14px;
        font-weight: 600;
    }

    .big-days {
        font-size: 52px;
        font-weight: 800;
        line-height: 1;
    }

    .timeline-item {
        display: flex;
        gap: 16px;
        padding: 14px 0;
        border-bottom: 1px solid var(--gf-border);
    }

    .timeline-item:last-child {
        border-bottom: none;
    }

    .timeline-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-top: 5px;
        flex-shrink: 0;
    }

    .timeline-dot.active {
        background: #10b981;
        box-shadow: 0 0 8px rgba(16, 185, 129, .4);
    }

    .timeline-dot.past {
        background: rgba(255, 255, 255, .2);
    }
</style>

<div class="page-header">
    <a href="<?php echo BASE_URL ?>/pages/admin/dashboard.php" class="btn btn-ghost"
        style="display:inline-flex;align-items:center;gap:6px;margin-right:12px">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Volver
    </a>
    <div>
        <h1 style="font-size:20px;font-weight:700">Facturación</h1>
        <div style="font-size:12px;color:var(--gf-text-muted)"><?php echo htmlspecialchars($gym['name']) ?></div>
    </div>
</div>

<div class="page-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        <!-- Left: current status card -->
        <div>
            <div class="billing-card">
                <h2>Ciclo actual</h2>
                <?php if (!$sub): ?>
                    <div style="color:var(--gf-text-muted);font-size:14px">Sin suscripción registrada. Contactá al
                        administrador.</div>
                <?php else: ?>
                    <!-- Big countdown -->
                    <?php if ($daysLeft !== null && $daysLeft >= 0): ?>
                        <div style="text-align:center;padding:20px 0 24px">
                            <div class="big-days" style="color:<?php echo $statusColor ?>">
                                <?php echo $daysLeft ?>
                            </div>
                            <div style="font-size:13px;color:var(--gf-text-muted);margin-top:6px">
                                día
                                <?php echo $daysLeft !== 1 ? 's' : '' ?> restante
                                <?php echo $daysLeft !== 1 ? 's' : '' ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <span class="info-label">Plan</span>
                        <span class="info-value">
                            <?php echo $planLabel[$sub['plan']] ?? $sub['plan'] ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Estado</span>
                        <span class="info-value" style="color:<?php echo $statusColor ?>">
                            <?php
                            if ($daysLeft !== null && $daysLeft < 0)
                                echo 'Vencido';
                            elseif ($daysLeft !== null && $daysLeft <= 7)
                                echo 'Por vencer';
                            else
                                echo $statusLabel[$sub['status']] ?? $sub['status'];
                            ?>
                        </span>
                    </div>
                    <?php if ($sub['current_period_start']): ?>
                        <div class="info-row">
                            <span class="info-label">Inicio del ciclo</span>
                            <span class="info-value">
                                <?php echo (new DateTime($sub['current_period_start']))->format('d/m/Y') ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($sub['current_period_end']): ?>
                        <div class="info-row">
                            <span class="info-label">Vencimiento</span>
                            <span class="info-value" style="color:<?php echo $statusColor ?>">
                                <?php echo (new DateTime($sub['current_period_end']))->format('d/m/Y') ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($sub['plan'] === 'trial' && $sub['trial_ends_at']): ?>
                        <div class="info-row">
                            <span class="info-label">Fin del trial</span>
                            <span class="info-value">
                                <?php echo (new DateTime($sub['trial_ends_at']))->format('d/m/Y') ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($sub['notes']): ?>
                        <div
                            style="margin-top:16px;padding:12px;background:rgba(255,255,255,.04);border-radius:10px;font-size:13px;color:var(--gf-text-muted)">
                            <?php echo nl2br(htmlspecialchars($sub['notes'])) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($sub && $daysLeft !== null && $daysLeft <= 14 && $daysLeft >= 0): ?>
                <div
                    style="padding:16px 20px;border-radius:12px;background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);font-size:13px;color:rgba(255,255,255,0.7);line-height:1.6">
                    Para renovar o cambiar tu plan, contactá al administrador de GymFlow.
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: renewal history -->
        <div class="billing-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                <h2 style="margin-bottom:0">Historial de ciclos</h2>
            </div>

            <?php if (!$sub): ?>
                <div style="color:var(--gf-text-muted);font-size:14px">Sin historial.</div>
            <?php elseif (empty($history)): ?>
                <div style="color:var(--gf-text-muted);font-size:13px;text-align:center;padding:24px 0">Sin renovaciones
                    registradas aún.</div>
            <?php else: ?>
                <?php
                $eventMeta = [
                    'activation' => ['icon' => '🟢', 'label' => 'Activación', 'color' => '#10b981'],
                    'renewal' => ['icon' => '🔄', 'label' => 'Renovación', 'color' => '#6366f1'],
                    'plan_change' => ['icon' => '⬆️', 'label' => 'Cambio de plan', 'color' => '#a855f7'],
                    'expiry' => ['icon' => '🔴', 'label' => 'Vencimiento', 'color' => '#ef4444'],
                    'suspension' => ['icon' => '⏸️', 'label' => 'Suspensión', 'color' => '#f59e0b'],
                    'credit' => ['icon' => '🎁', 'label' => 'Crédito / bono', 'color' => '#06b6d4'],
                ];
                $planLabel = ['trial' => 'Trial', 'instructor' => 'Instructor', 'gimnasio' => 'Gimnasio', 'centro' => 'Centro', 'monthly' => 'Mensual', 'annual' => 'Anual'];
                foreach ($history as $idx => $r):
                    $meta = $eventMeta[$r['event_type']] ?? ['icon' => '▪', 'label' => $r['event_type'], 'color' => '#888'];
                    $isFirst = $idx === 0;
                    ?>
                    <div class="timeline-item" style="<?php echo $isFirst ? '' : '' ?>">
                        <!-- dot -->
                        <div style="display:flex;flex-direction:column;align-items:center;gap:0;flex-shrink:0;padding-top:3px">
                            <div
                                style="width:10px;height:10px;border-radius:50%;background:<?php echo $meta['color'] ?>;<?php echo $isFirst ? 'box-shadow:0 0 8px ' . $meta['color'] . '88' : 'opacity:.5' ?>">
                            </div>
                            <?php if ($idx < count($history) - 1): ?>
                                <div style="width:1px;flex:1;background:rgba(255,255,255,.07);margin-top:4px"></div>
                            <?php endif; ?>
                        </div>
                        <!-- content -->
                        <div style="flex:1;padding-bottom:4px">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <span style="font-size:14px;font-weight:700"><?php echo $meta['icon'] ?>
                                    <?php echo $meta['label'] ?></span>
                                <span
                                    style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:2px 7px;border-radius:6px;background:rgba(255,255,255,.06);color:var(--gf-text-muted)"><?php echo $planLabel[$r['plan']] ?? $r['plan'] ?></span>
                                <?php if ($r['extra_salas']): ?>
                                    <span style="font-size:10px;color:var(--gf-text-muted)">+<?php echo $r['extra_salas'] ?>
                                        sala<?php echo $r['extra_salas'] > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- dates + amount -->
                            <div
                                style="font-size:12px;color:var(--gf-text-muted);margin-top:4px;display:flex;gap:12px;flex-wrap:wrap">
                                <?php if ($r['period_start'] && $r['period_end']): ?>
                                    <span>📅 <?php echo (new DateTime($r['period_start']))->format('d/m/Y') ?> →
                                        <?php echo (new DateTime($r['period_end']))->format('d/m/Y') ?></span>
                                <?php endif; ?>
                                <?php if ($r['amount_ars'] !== null && $r['amount_ars'] > 0): ?>
                                    <span style="color:#10b981;font-weight:600">💰
                                        $<?php echo number_format($r['amount_ars'], 0, ',', '.') ?> ARS</span>
                                <?php elseif ($r['amount_ars'] === '0' || $r['amount_ars'] === 0): ?>
                                    <span style="color:var(--gf-text-muted)">💰 Cortesía</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($r['notes']): ?>
                                <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:4px;font-style:italic">
                                    <?php echo htmlspecialchars($r['notes']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($r['invoice_path']): ?>
                                <a href="<?php echo BASE_URL . '/' . htmlspecialchars($r['invoice_path']) ?>" target="_blank"
                                    download
                                    style="display:inline-flex;align-items:center;gap:5px;margin-top:6px;padding:3px 10px;background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.25);border-radius:6px;font-size:11px;font-weight:600;color:#818cf8;text-decoration:none">
                                    📄 Descargar factura
                                </a>
                            <?php endif; ?>
                            <div style="font-size:10px;color:rgba(255,255,255,.25);margin-top:4px">
                                <?php echo (new DateTime($r['created_at']))->format('d/m/Y H:i') ?>
                                <?php if ($r['created_by_name']): ?> ·
                                    <?php echo htmlspecialchars($r['created_by_name']) ?>         <?php endif; ?>
                                <?php if ($user['role'] === 'superadmin'): ?>
                                    · <a href="#" onclick="deleteRenewal(<?php echo $r['id'] ?>)"
                                        style="color:rgba(239,68,68,.5);text-decoration:none">eliminar</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($user['role'] === 'superadmin'): ?>
    <script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
    <script>
        async function deleteRenewal(id) {
            if (!confirm('¿Eliminar este registro?')) return;
            const res = await fetch(window.GF_BASE + '/api/renewals.php?id=' + id, { method: 'DELETE' });
            const data = await res.json();
            if (data.ok) { showToast('Registro eliminado', 'info'); location.reload(); }
            else showToast('Error: ' + (data.error || ''), 'error');
        }
    </script>
<?php endif; ?>

<?php layout_end(); ?>