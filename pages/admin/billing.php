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

// History: all audit entries from the subscription log
// (We'll show the current subscription data + any future history table)
// For now we show the current record and its key dates as a "timeline"

layout_header('Facturación — ' . $gym['name'], 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'admin', 'admin');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Instructor', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>', 'instructor', 'admin');
if ($user['role'] === 'superadmin')
    nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Super Admin', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'superadmin', 'admin');
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
    <a href="<?php echo BASE_URL ?>/pages/admin/dashboard.php" class="btn btn-ghost" style="display:inline-flex;align-items:center;gap:6px;margin-right:12px">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
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

        <!-- Right: timeline / history -->
        <div class="billing-card">
            <h2>Historial de ciclos</h2>
            <?php if (!$sub): ?>
                <div style="color:var(--gf-text-muted);font-size:14px">Sin historial.</div>
            <?php else: ?>
                <div class="timeline-item">
                    <div class="timeline-dot active"></div>
                    <div style="flex:1">
                        <div style="font-size:14px;font-weight:600">
                            <?php echo $planLabel[$sub['plan']] ?? $sub['plan'] ?>
                            <span style="font-size:11px;font-weight:400;color:var(--gf-text-muted);margin-left:6px">Ciclo
                                actual</span>
                        </div>
                        <div style="font-size:12px;color:var(--gf-text-muted);margin-top:4px">
                            <?php
                            $start = $sub['current_period_start'] ? (new DateTime($sub['current_period_start']))->format('d/m/Y') : '—';
                            $end = $sub['current_period_end'] ? (new DateTime($sub['current_period_end']))->format('d/m/Y') : '—';
                            echo "$start → $end";
                            ?>
                        </div>
                        <?php if ($daysLeft !== null): ?>
                            <div style="font-size:11px;margin-top:4px;color:<?php echo $statusColor ?>">
                                <?php echo $daysLeft >= 0 ? "{$daysLeft} días restantes" : 'Vencido' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div
                        style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;background:rgba(16,185,129,.1);color:#10b981;border:1px solid rgba(16,185,129,.2);align-self:flex-start">
                        Activo
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot past"></div>
                    <div style="flex:1">
                        <div style="font-size:13px;color:var(--gf-text-muted)">Creación del gym</div>
                        <div style="font-size:12px;color:rgba(255,255,255,0.3);margin-top:4px">
                            <?php echo (new DateTime($sub['created_at']))->format('d/m/Y H:i') ?>
                        </div>
                    </div>
                </div>
                <div
                    style="margin-top:16px;padding:12px;background:rgba(255,255,255,.03);border-radius:10px;font-size:12px;color:rgba(255,255,255,.3);text-align:center">
                    El historial completo de renovaciones estará disponible próximamente.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php layout_end(); ?>