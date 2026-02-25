<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/plans.php';

$user = requireAuth('admin', 'superadmin');
// Allow superadmin to view any gym via GET param
$gymId = (int) ($user['gym_id'] ?? $_GET['gym_id'] ?? 0);
if (!$gymId) {
    header('Location: ' . BASE_URL . '/pages/superadmin/dashboard.php');
    exit;
}

$gym = db()->prepare("SELECT * FROM gyms WHERE id = ?");
$gym->execute([$gymId]);
$gym = $gym->fetch();
if (!$gym) {
    echo 'Gym no encontrado';
    exit;
}

$salas = db()->prepare("SELECT s.*, (SELECT COUNT(*) FROM gym_sessions gs WHERE gs.sala_id = s.id) as session_count FROM salas s WHERE s.gym_id = ? ORDER BY s.name");
$salas->execute([$gymId]);
$salas = $salas->fetchAll();

$users = db()->prepare("SELECT * FROM users WHERE gym_id = ? AND role != 'superadmin' ORDER BY role, name");
$users->execute([$gymId]);
$users = $users->fetchAll();

$sessionCount = (int) db()->prepare("SELECT COUNT(*) FROM gym_sessions WHERE gym_id = ?")->execute([$gymId]) ? db()->prepare("SELECT COUNT(*) FROM gym_sessions WHERE gym_id = ?") : 0;
$sc = db()->prepare("SELECT COUNT(*) FROM gym_sessions WHERE gym_id = ?");
$sc->execute([$gymId]);
$sessionCount = (int) $sc->fetchColumn();

// ── CRM quick stats ─────────────────────────────────────────────────────────
$crmQ = db()->prepare("
    SELECT
        (SELECT COUNT(*) FROM members WHERE gym_id = ? AND active = 1) AS total_members,
        (SELECT COUNT(*) FROM member_memberships WHERE gym_id = ? AND end_date >= CURDATE()) AS active_memberships,
        (SELECT COUNT(*) FROM member_memberships WHERE gym_id = ? AND payment_status IN ('pending','overdue') AND end_date >= CURDATE()) AS pending_payments,
        (SELECT COUNT(*) FROM member_memberships WHERE gym_id = ? AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS expiring_week
");
$crmQ->execute([$gymId, $gymId, $gymId, $gymId]);
$crmStats = $crmQ->fetch();

$sub = getGymSubscription($gymId);
$planInfo = $sub ? getGymPlanInfo($gymId) : null;

// Compute subscription display state
$subBanner = null;
if ($sub && $user['role'] !== 'superadmin') {
    $today = new DateTime();
    $periodEnd = $sub['current_period_end'] ? new DateTime($sub['current_period_end']) : null;
    $daysLeft = $periodEnd ? (int) $today->diff($periodEnd)->days * ($today <= $periodEnd ? 1 : -1) : null;

    if ($sub['plan'] === 'trial' && $daysLeft !== null && $daysLeft >= 0) {
        $subBanner = ['type' => 'trial', 'days' => $daysLeft, 'end' => $sub['current_period_end']];
    } elseif ($daysLeft !== null && $daysLeft <= 7 && $daysLeft >= 0) {
        $subBanner = ['type' => 'expiring', 'days' => $daysLeft, 'end' => $sub['current_period_end']];
    }
}

layout_header('Admin Panel — ' . $gym['name'], 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'admin', 'admin');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Instructor', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>', 'instructor', 'admin');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'members', 'admin');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'plans', 'admin');
nav_item(BASE_URL . '/pages/admin/gym-qr.php', 'QR Check-in', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>', 'gym-qr', 'admin');
if ($user['role'] === 'superadmin')
    nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Super Admin', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'superadmin', 'admin');
layout_footer($user);
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <div
            style="width:40px;height:40px;border-radius:10px;background:<?php echo htmlspecialchars($gym['primary_color']) ?>26;display:flex;align-items:center;justify-content:center;font-weight:700;color:<?php echo htmlspecialchars($gym['primary_color']) ?>">
            <?php echo strtoupper(substr($gym['name'], 0, 2)) ?>
        </div>
        <div>
            <h1 style="font-size:20px;font-weight:700">
                <?php echo htmlspecialchars($gym['name']) ?>
            </h1>
            <div style="font-size:12px;color:var(--gf-text-muted)">Panel de Administración</div>
        </div>
    </div>
    <div class="flex gap-2 ml-auto">
        <button class="btn btn-secondary" onclick="document.getElementById('branding-modal').classList.add('open')">🎨
            Branding</button>
        <button class="btn btn-primary" onclick="document.getElementById('sala-modal').classList.add('open')">+ Nueva
            Sala</button>
    </div>
</div>

<div class="page-body">

    <!-- Stats row -->
    <div class="grid grid-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon"><svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo count($users) ?>
                </div>
                <div class="stat-label">Instructores</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(255,107,53,.15);color:#ff6b35"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo count($salas) ?>
                </div>
                <div class="stat-label">Salas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(124,58,237,.15);color:#7c3aed"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $sessionCount ?>
                </div>
                <div class="stat-label">Sesiones</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(99,102,241,.15);color:#818cf8">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
            </div>
            <div style="flex:1;min-width:0">
                <?php
                $planLabel = $planInfo ? $planInfo['limits']['label'] : 'Sin plan';
                $salaUsed = $planInfo ? $planInfo['usage']['salas'] : count($salas);
                $salaLimit = $planInfo ? $planInfo['limits']['salas'] : '?';
                $salaAtLimit = $planInfo && !$planInfo['can_add_sala'];
                ?>
                <div class="stat-value" style="font-size:16px;color:#818cf8"><?php echo htmlspecialchars($planLabel) ?>
                </div>
                <div class="stat-label">
                    Salas <?php echo $salaUsed ?>/<?php echo $salaLimit ?>
                    <?php if ($salaAtLimit): ?>
                        <span style="color:#f59e0b;font-weight:700"> · límite</span>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($subBanner):
        $isTrial = $subBanner['type'] === 'trial';
        $isExpiring = $subBanner['type'] === 'expiring';
        $days = $subBanner['days'];
        $endFmt = (new DateTime($subBanner['end']))->format('d/m/Y');
        $color = $isTrial ? '#f59e0b' : '#ef4444';
        $bg = $isTrial ? 'rgba(245,158,11,0.08)' : 'rgba(239,68,68,0.08)';
        $border = $isTrial ? 'rgba(245,158,11,0.2)' : 'rgba(239,68,68,0.22)';
        $icon = $isTrial ? '⏳' : '🔔';
        $bannerId = 'sub-banner-' . $gymId;
        if ($isTrial) {
            $msg = $days === 0
                ? 'Tu período de prueba <strong>vence hoy</strong>.'
                : "Tu período de prueba vence en <strong>{$days} día" . ($days !== 1 ? 's' : '') . "</strong> (el {$endFmt}).";
        } else {
            $msg = "Tu suscripción vence en <strong>{$days} día" . ($days !== 1 ? 's' : '') . "</strong> (el {$endFmt}). Renovar antes de esa fecha para no perder acceso.";
        }
        ?>
        <div id="sub-banner" style="
        margin-bottom:20px;
        padding:14px 18px;
        border-radius:12px;
        background:<?php echo $bg ?>;
        border:1px solid <?php echo $border ?>;
        display:flex;align-items:center;gap:14px;
        font-size:14px;
    ">
            <span style="font-size:20px;flex-shrink:0"><?php echo $icon ?></span>
            <div style="flex:1;line-height:1.5">
                <?php echo $msg ?>
            </div>
            <a href="<?php echo BASE_URL ?>/pages/admin/billing.php" style="flex-shrink:0;font-size:13px;font-weight:600;color:<?php echo $color ?>;text-decoration:none;border:1px solid <?php echo $border ?>;
                  padding:6px 14px;border-radius:8px;white-space:nowrap;transition:background .2s"
                onmouseover="this.style.background='<?php echo $bg ?>'" onmouseout="this.style.background='transparent'">
                Ver ciclos →
            </a>
            <button
                onclick="document.getElementById('sub-banner').style.display='none';sessionStorage.setItem('<?php echo $bannerId ?>','1')"
                style="background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.3);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0"
                title="Cerrar">×</button>
        </div>
        <script>
            if (sessionStorage.getItem('<?php echo $bannerId ?>')) {
                document.getElementById('sub-banner').style.display = 'none';
            }
        </script>
    <?php endif; ?>

    <?php
    // ── Limit-reached banner ────────────────────────────────────────────────
    $showSalaLimitBanner = $planInfo && !$planInfo['can_add_sala'] && $user['role'] !== 'superadmin';
    $showInstrLimitBanner = $planInfo && !$planInfo['can_add_instructor'] && $user['role'] !== 'superadmin';
    if ($showSalaLimitBanner || $showInstrLimitBanner):
        ?>
        <div
            style="margin-bottom:20px;padding:14px 18px;border-radius:12px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);display:flex;align-items:center;gap:14px;font-size:14px">
            <span style="font-size:20px;flex-shrink:0">📦</span>
            <div style="flex:1;line-height:1.5">
                <?php if ($showSalaLimitBanner): ?>
                    <strong>Límite de salas alcanzado.</strong> Tu plan
                    <em><?php echo htmlspecialchars($planInfo['limits']['label']) ?></em> permite
                    <?php echo $planInfo['limits']['salas'] ?> sala<?php echo $planInfo['limits']['salas'] !== 1 ? 's' : '' ?>.
                <?php endif ?>
                <?php if ($showInstrLimitBanner): ?>
                    <?php if ($showSalaLimitBanner): ?> &nbsp;·&nbsp; <?php endif ?>
                    <strong>Límite de instructores alcanzado.</strong> Tu plan permite 1 instructor.
                <?php endif ?>
                <span style="color:var(--gf-text-muted)"> Contactá a GymFlow para ampliar tu plan o agregar salas
                    extra.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- CRM Quick Summary -->
    <?php if ($crmStats['total_members'] > 0 || true): ?>
        <div class="card mb-6" style="padding:16px 20px">
            <div class="flex items-center justify-between mb-3">
                <h2 style="font-size:15px;font-weight:700">👥 Alumnos & CRM</h2>
                <a href="<?php echo BASE_URL ?>/pages/admin/members.php" class="btn btn-secondary btn-sm">Ver todos →</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
                <a href="<?php echo BASE_URL ?>/pages/admin/members.php" style="text-decoration:none">
                    <div style="background:var(--gf-surface-2);border-radius:10px;padding:12px;border:1px solid var(--gf-border);transition:border-color .2s"
                        onmouseover="this.style.borderColor='var(--gf-accent)'"
                        onmouseout="this.style.borderColor='var(--gf-border)'">
                        <div style="font-size:22px;font-weight:800"><?php echo (int) $crmStats['total_members'] ?></div>
                        <div style="font-size:11px;color:var(--gf-text-muted)">Alumnos activos</div>
                    </div>
                </a>
                <a href="<?php echo BASE_URL ?>/pages/admin/members.php?status=active" style="text-decoration:none">
                    <div style="background:var(--gf-surface-2);border-radius:10px;padding:12px;border:1px solid var(--gf-border);transition:border-color .2s"
                        onmouseover="this.style.borderColor='#10b981'"
                        onmouseout="this.style.borderColor='var(--gf-border)'">
                        <div style="font-size:22px;font-weight:800;color:#10b981">
                            <?php echo (int) $crmStats['active_memberships'] ?></div>
                        <div style="font-size:11px;color:var(--gf-text-muted)">Membresías vigentes</div>
                    </div>
                </a>
                <a href="<?php echo BASE_URL ?>/pages/admin/members.php?status=pending" style="text-decoration:none">
                    <div
                        style="background:var(--gf-surface-2);border-radius:10px;padding:12px;border:1px solid <?php echo $crmStats['pending_payments'] > 0 ? 'rgba(245,158,11,.4)' : 'var(--gf-border)' ?>;transition:border-color .2s">
                        <div
                            style="font-size:22px;font-weight:800;color:<?php echo $crmStats['pending_payments'] > 0 ? '#f59e0b' : 'var(--gf-text)' ?>">
                            <?php echo (int) $crmStats['pending_payments'] ?></div>
                        <div style="font-size:11px;color:var(--gf-text-muted)">Pagos pendientes</div>
                    </div>
                </a>
                <a href="<?php echo BASE_URL ?>/pages/admin/members.php?status=expired" style="text-decoration:none">
                    <div
                        style="background:var(--gf-surface-2);border-radius:10px;padding:12px;border:1px solid <?php echo $crmStats['expiring_week'] > 0 ? 'rgba(239,68,68,.35)' : 'var(--gf-border)' ?>;transition:border-color .2s">
                        <div
                            style="font-size:22px;font-weight:800;color:<?php echo $crmStats['expiring_week'] > 0 ? '#ef4444' : 'var(--gf-text)' ?>">
                            <?php echo (int) $crmStats['expiring_week'] ?></div>
                        <div style="font-size:11px;color:var(--gf-text-muted)">Vencen esta semana</div>
                    </div>
                </a>
            </div>
        </div>
    <?php endif ?>

    <div class="grid grid-2">

        <!-- Salas -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 style="font-size:16px;font-weight:700">Salas</h2>
                <button class="btn btn-primary btn-sm"
                    onclick="document.getElementById('sala-modal').classList.add('open')">+ Sala</button>
            </div>
            <?php if (empty($salas)): ?>
                <div class="empty-state" style="padding:20px">Sin salas. Creá la primera.</div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($salas as $sala): ?>
                        <div
                            style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--gf-surface-2);border-radius:10px;border:1px solid var(--gf-border)">
                            <div class="dot <?php echo $sala['current_session_id'] ? 'dot-live' : 'dot-idle' ?>"></div>
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:14px">
                                    <?php echo htmlspecialchars($sala['name']) ?>
                                </div>
                                <code
                                    style="font-size:11px;color:var(--gf-text-dim);background:rgba(255,255,255,.05);padding:2px 6px;border-radius:4px"><?php echo htmlspecialchars($sala['display_code']) ?></code>
                            </div>
                            <div style="font-size:12px;color:var(--gf-text-muted)">
                                <?php echo $sala['session_count'] ?> sesiones
                            </div>
                            <a href="<?php echo BASE_URL ?>/pages/display/sala.php?code=<?php echo urlencode($sala['display_code']) ?>"
                                target="_blank" class="btn btn-ghost btn-sm">Display</a>
                            <button class="btn btn-secondary btn-sm"
                                onclick="openRenameSala(<?php echo $sala['id'] ?>, <?php echo htmlspecialchars(json_encode($sala['name']), ENT_QUOTES) ?>)"
                                title="Renombrar sala">✏️</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteSala(<?php echo $sala['id'] ?>)">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Users / Instructors -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 style="font-size:16px;font-weight:700">Usuarios</h2>
                <button class="btn btn-primary btn-sm"
                    onclick="document.getElementById('user-modal').classList.add('open')">+ Usuario</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($u['name']) ?>
                                </td>
                                <td style="font-size:12px;color:var(--gf-text-muted)">
                                    <?php echo htmlspecialchars($u['email']) ?>
                                </td>
                                <td><span
                                        class="badge <?php echo $u['role'] === 'admin' ? 'badge-orange' : 'badge-accent' ?>">
                                        <?php echo $u['role'] ?>
                                    </span></td>
                                <td><button class="btn btn-danger btn-sm"
                                        onclick="deleteUser(<?php echo $u['id'] ?>)">Eliminar</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- New Sala Modal -->
<div class="modal-overlay" id="sala-modal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">Nueva Sala</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')"><svg
                    width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <form id="sala-form" onsubmit="createSala(event)">
            <div class="form-group"><label class="form-label">Nombre</label><input class="form-control" name="name"
                    required placeholder="Sala Principal"></div>
            <div class="form-group"><label class="form-label">Capacidad</label><input type="number" class="form-control"
                    name="capacity" value="20" min="1"></div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Crear Sala</button>
        </form>
    </div>
</div>

<!-- Rename Sala Modal -->
<div class="modal-overlay" id="rename-sala-modal">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <h3 class="modal-title">Renombrar Sala</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')"><svg
                    width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <form onsubmit="submitRenameSala(event)">
            <input type="hidden" id="rename-sala-id">
            <div class="form-group">
                <label class="form-label">Nuevo nombre</label>
                <input class="form-control" id="rename-sala-name" required placeholder="Sala Crossfit">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Guardar nombre</button>
        </form>
    </div>
</div>

<!-- New User Modal -->
<div class="modal-overlay" id="user-modal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">Nuevo Usuario</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')"><svg
                    width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <form onsubmit="createUser(event)">
            <div class="form-group"><label class="form-label">Nombre</label><input class="form-control" id="u-name"
                    required placeholder="María García"></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-control"
                    id="u-email" required></div>
            <div class="form-group"><label class="form-label">Contraseña</label><input type="password"
                    class="form-control" id="u-pass" required minlength="8"></div>
            <div class="form-group"><label class="form-label">Rol</label>
                <select class="form-control" id="u-role">
                    <option value="instructor">Instructor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Crear Usuario</button>
        </form>
    </div>
</div>

<!-- Branding Modal -->
<div class="modal-overlay" id="branding-modal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title">Branding del Gimnasio</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')"><svg
                    width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <form onsubmit="saveBranding(event)">
            <div class="param-row">
                <div class="form-group"><label class="form-label">Color Primario</label><input type="color"
                        class="form-control" id="b-primary"
                        value="<?php echo htmlspecialchars($gym['primary_color']) ?>">
                </div>
                <div class="form-group"><label class="form-label">Color Secundario</label><input type="color"
                        class="form-control" id="b-secondary"
                        value="<?php echo htmlspecialchars($gym['secondary_color']) ?>">
                </div>
            </div>
            <div class="form-group"><label class="form-label">Nombre del Gimnasio</label><input class="form-control"
                    id="b-name" value="<?php echo htmlspecialchars($gym['name']) ?>"></div>

            <!-- ── Logo uploader ── -->
            <div class="form-group">
                <label class="form-label">Logo del Gimnasio</label>
                <div id="logo-drop-zone" onclick="document.getElementById('logo-file-input').click()"
                    ondragover="event.preventDefault();this.style.borderColor='var(--gf-accent)'"
                    ondragleave="this.style.borderColor='rgba(255,255,255,0.15)'" ondrop="handleLogoDrop(event)"
                    style="border:2px dashed rgba(255,255,255,0.15);border-radius:12px;padding:24px;cursor:pointer;text-align:center;transition:border-color .2s;background:rgba(255,255,255,0.02)">
                    <?php if (!empty($gym['logo_path'])): ?>
                        <img id="logo-preview" src="<?php echo BASE_URL . htmlspecialchars($gym['logo_path']) ?>" alt="Logo"
                            style="max-height:80px;max-width:220px;object-fit:contain;display:block;margin:0 auto 12px">
                        <div id="logo-drop-hint" style="font-size:12px;color:rgba(255,255,255,0.35)">Clic o arrastrá para
                            reemplazar</div>
                    <?php else: ?>
                        <img id="logo-preview" src="" alt=""
                            style="max-height:80px;max-width:220px;object-fit:contain;display:none;margin:0 auto 12px">
                        <div id="logo-drop-hint" style="font-size:13px;color:rgba(255,255,255,0.4)">
                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                style="margin:0 auto 8px;display:block;opacity:.4">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Clic o arrastrá tu logo aquí<br>
                            <span style="font-size:11px;color:rgba(255,255,255,0.25)">PNG, JPG, SVG — máx. 2 MB</span>
                        </div>
                    <?php endif; ?>
                    <input id="logo-file-input" type="file" accept="image/*" style="display:none"
                        onchange="previewAndUploadLogo(this.files[0])">
                </div>
                <!-- Status row -->
                <div id="logo-actions"
                    style="margin-top:8px;display:<?php echo !empty($gym['logo_path']) ? 'flex' : 'none' ?>;gap:8px;align-items:center">
                    <span id="logo-filename"
                        style="font-size:12px;color:rgba(255,255,255,0.4);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        <?php echo htmlspecialchars($gym['logo_path'] ?? '') ?>
                    </span>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeLogo()">✕ Eliminar</button>
                </div>
                <div id="logo-upload-status" style="font-size:12px;margin-top:6px;display:none;color:var(--gf-accent)">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Guardar Branding</button>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    const GYM_ID = <?php echo $gymId ?>;
    let currentLogoPath = <?php echo json_encode($gym['logo_path'] ?? null) ?>;

    async function createSala(e) {
        e.preventDefault();
        const form = e.target;
        const data = { name: form.name.value, capacity: +form.capacity.value, gym_id: GYM_ID };
        const res = await GF.post(window.GF_BASE + '/api/salas.php', data);
        if (res && res.code === 'SALA_LIMIT') {
            showToast(`Límite de salas alcanzado (${res.current}/${res.limit}). Contactá a GymFlow para ampliar tu plan.`, 'error');
            return;
        }
        showToast('Sala creada', 'success');
        location.reload();
    }

    async function deleteSala(id) {
        if (!confirm('¿Eliminar sala?')) return;
        await GF.delete(`${window.GF_BASE}/api/salas.php?id=${id}`);
        location.reload();
    }

    function openRenameSala(id, currentName) {
        document.getElementById('rename-sala-id').value = id;
        document.getElementById('rename-sala-name').value = currentName;
        document.getElementById('rename-sala-modal').classList.add('open');
        setTimeout(() => document.getElementById('rename-sala-name').select(), 80);
    }

    async function submitRenameSala(e) {
        e.preventDefault();
        const id = document.getElementById('rename-sala-id').value;
        const name = document.getElementById('rename-sala-name').value.trim();
        if (!name) return;
        await GF.put(`${window.GF_BASE}/api/salas.php?id=${id}`, { name });
        showToast('Nombre actualizado', 'success');
        document.getElementById('rename-sala-modal').classList.remove('open');
        location.reload();
    }

    async function createUser(e) {
        e.preventDefault();
        const data = { name: document.getElementById('u-name').value, email: document.getElementById('u-email').value, password: document.getElementById('u-pass').value, role: document.getElementById('u-role').value, gym_id: GYM_ID };
        const res = await GF.post(window.GF_BASE + '/api/users.php', data);
        if (res && res.code === 'INSTRUCTOR_LIMIT') {
            showToast(`Límite de instructores alcanzado. Contactá a GymFlow para mejorar tu plan.`, 'error');
            return;
        }
        showToast('Usuario creado', 'success');
        location.reload();
    }

    async function deleteUser(id) {
        if (!confirm('¿Eliminar usuario?')) return;
        await GF.delete(`${window.GF_BASE}/api/users.php?id=${id}`);
        location.reload();
    }

    async function saveBranding(e) {
        e.preventDefault();
        const data = {
            primary_color: document.getElementById('b-primary').value,
            secondary_color: document.getElementById('b-secondary').value,
            logo_path: currentLogoPath, // Use the dynamically updated logo path
            name: document.getElementById('b-name').value
        };
        await GF.put(`${window.GF_BASE}/api/gyms.php?id=${GYM_ID}`, data);
        showToast('Branding guardado', 'success');
        // Close modal first so the tour's MutationObserver can advance to the next step
        // before the page reloads (otherwise the tour loops back to step 1).
        document.getElementById('branding-modal').classList.remove('open');
        setTimeout(() => location.reload(), 800);
    }

    // Logo Uploader Functions
    const logoPreview = document.getElementById('logo-preview');
    const logoDropHint = document.getElementById('logo-drop-hint');
    const logoFileInput = document.getElementById('logo-file-input');
    const logoActions = document.getElementById('logo-actions');
    const logoFilename = document.getElementById('logo-filename');
    const logoUploadStatus = document.getElementById('logo-upload-status');

    function updateLogoDisplay(path, filename = '') {
        if (path) {
            logoPreview.src = path.startsWith('http') ? path : `${window.GF_BASE}${path}`;
            logoPreview.style.display = 'block';
            logoDropHint.innerHTML = 'Clic o arrastrá para reemplazar';
            logoDropHint.style.fontSize = '12px';
            logoDropHint.style.color = 'rgba(255,255,255,0.35)';
            logoActions.style.display = 'flex';
            logoFilename.textContent = filename || path.split('/').pop();
        } else {
            logoPreview.src = '';
            logoPreview.style.display = 'none';
            logoDropHint.innerHTML = '<svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"'
                + ' style="margin:0 auto 8px;display:block;opacity:.4">'
                + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"'
                + ' d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>'
                + '<' + '/svg>'
                + ' Clic o arrastrá tu logo aquí<br>'
                + '<span style="font-size:11px;color:rgba(255,255,255,0.25)">PNG, JPG, SVG — máx. 2 MB<' + '/span>';
            logoDropHint.style.fontSize = '13px';
            logoDropHint.style.color = 'rgba(255,255,255,0.4)';
            logoActions.style.display = 'none';
            logoFilename.textContent = '';
        }
    }

    async function uploadLogo(file) {
        if (!file) return;

        logoUploadStatus.style.display = 'block';
        logoUploadStatus.textContent = 'Subiendo...';
        logoUploadStatus.style.color = 'var(--gf-accent)';

        const formData = new FormData();
        formData.append('logo', file);

        try {
            const response = await fetch(`${window.GF_BASE}/api/gyms.php?id=${GYM_ID}&logo=1`, {
                method: 'POST',
                credentials: 'include',
                body: formData
            });
            const result = await response.json();

            if (response.ok && result.path) {
                currentLogoPath = result.path;
                updateLogoDisplay(currentLogoPath, file.name);
                logoUploadStatus.textContent = '✓ Logo subido con éxito';
                logoUploadStatus.style.color = '#10b981';
            } else {
                throw new Error(result.error || 'Error al subir el logo');
            }
        } catch (error) {
            console.error('[Logo] Upload error:', error);
            logoUploadStatus.textContent = `✕ ${error.message}`;
            logoUploadStatus.style.color = '#ef4444';
        } finally {
            setTimeout(() => { logoUploadStatus.style.display = 'none'; }, 3000);
        }
    }

    function previewAndUploadLogo(file) {
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                logoPreview.src = e.target.result;
                logoPreview.style.display = 'block';
                logoDropHint.innerHTML = 'Clic o arrastrá para reemplazar';
                logoDropHint.style.fontSize = '12px';
                logoDropHint.style.color = 'rgba(255,255,255,0.35)';
                logoActions.style.display = 'flex';
                logoFilename.textContent = file.name;
            };
            reader.readAsDataURL(file);
            uploadLogo(file);
        }
    }

    function handleLogoDrop(event) {
        event.preventDefault();
        document.getElementById('logo-drop-zone').style.borderColor = 'rgba(255,255,255,0.15)';
        const files = event.dataTransfer.files;
        if (files.length > 0) {
            previewAndUploadLogo(files[0]);
        }
    }

    function removeLogo(clearPath = true) {
        logoFileInput.value = ''; // Clear the file input
        updateLogoDisplay(null);
        if (clearPath) {
            currentLogoPath = null;
        }
        logoUploadStatus.style.display = 'none';
    }

    // Initialize logo display on page load
    document.addEventListener('DOMContentLoaded', () => {
        if (currentLogoPath) {
            updateLogoDisplay(currentLogoPath);
        }
    });

    // Close modal on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php layout_end(); ?>