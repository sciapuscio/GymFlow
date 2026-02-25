<?php
/**
 * GymFlow — Staff Dashboard
 * Acceso: solo rol 'staff'
 * Funciones: agenda + CRM (alumnos, membresías, pagos)
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('staff');
$gymId = (int) $user['gym_id'];

// ── Stats CRM ────────────────────────────────────────────────────────────────
$totalMembers = (int) db()->prepare("SELECT COUNT(*) FROM members WHERE gym_id=? AND active=1")->execute([$gymId]) ? db()->prepare("SELECT COUNT(*) FROM members WHERE gym_id=? AND active=1")->execute([$gymId]) : 0;

$stTotal = db()->prepare("SELECT COUNT(*) FROM members WHERE gym_id=? AND active=1");
$stTotal->execute([$gymId]);
$totalMembers = (int) $stTotal->fetchColumn();

$stActive = db()->prepare("SELECT COUNT(DISTINCT member_id) FROM member_memberships WHERE gym_id=? AND end_date>=CURDATE() AND payment_status='paid'");
$stActive->execute([$gymId]);
$activeMembers = (int) $stActive->fetchColumn();

$stPending = db()->prepare("SELECT COUNT(*) FROM member_memberships WHERE gym_id=? AND payment_status='pending'");
$stPending->execute([$gymId]);
$pendingPayments = (int) $stPending->fetchColumn();

$stExpiring = db()->prepare("SELECT COUNT(*) FROM member_memberships WHERE gym_id=? AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
$stExpiring->execute([$gymId]);
$expiringSoon = (int) $stExpiring->fetchColumn();

// ── Today's schedule ─────────────────────────────────────────────────────────
$today = strtolower(date('D')); // mon, tue ...
$dow = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6][substr(strtolower(date('D')), 0, 3)] ?? 1;
$stSched = db()->prepare("
    SELECT ss.*, s.name AS sala_name
    FROM schedule_slots ss
    LEFT JOIN salas s ON s.id = ss.sala_id
    WHERE ss.gym_id = ? AND ss.day_of_week = ?
    ORDER BY ss.start_time
");
$stSched->execute([$gymId, $dow]);
$todaySlots = $stSched->fetchAll();

// ── Nav ───────────────────────────────────────────────────────────────────────
layout_header('Panel Staff — ' . ($user['gym_name'] ?? 'GymFlow'), 'admin', $user);
nav_section('Staff');
nav_item(BASE_URL . '/pages/admin/staff-dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'staff-dashboard', 'staff-dashboard');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Agenda', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'staff-dashboard');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'members', 'staff-dashboard');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'plans', 'staff-dashboard');
nav_item(BASE_URL . '/pages/admin/gym-qr.php', 'QR Check-in', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>', 'gym-qr', 'staff-dashboard');
nav_item(BASE_URL . '/pages/admin/support.php', 'Soporte', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>', 'support', 'staff-dashboard');
layout_footer($user);
?>

<div class="page-header">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
    </svg>
    <h1 style="font-size:18px;font-weight:700">Panel Administrativo</h1>
    <span
        style="margin-left:8px;font-size:12px;color:var(--gf-text-dim);background:var(--gf-surface-2);padding:3px 10px;border-radius:20px">
        <?php echo htmlspecialchars($user['name']) ?>
    </span>
</div>

<div class="page-body">

    <!-- ── CRM Stats ──────────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px">
        <div class="card" style="text-align:center">
            <div style="font-size:28px;font-weight:800;color:var(--gf-accent)">
                <?php echo $totalMembers ?>
            </div>
            <div style="font-size:12px;color:var(--gf-text-muted);margin-top:4px">Alumnos activos</div>
        </div>
        <div class="card" style="text-align:center">
            <div style="font-size:28px;font-weight:800;color:#10b981">
                <?php echo $activeMembers ?>
            </div>
            <div style="font-size:12px;color:var(--gf-text-muted);margin-top:4px">Membresías vigentes</div>
        </div>
        <div class="card"
            style="text-align:center;<?php echo $pendingPayments > 0 ? 'border-color:rgba(245,158,11,.3)' : '' ?>">
            <div
                style="font-size:28px;font-weight:800;color:<?php echo $pendingPayments > 0 ? '#f59e0b' : 'var(--gf-text-muted)' ?>">
                <?php echo $pendingPayments ?>
            </div>
            <div style="font-size:12px;color:var(--gf-text-muted);margin-top:4px">Pagos pendientes</div>
        </div>
        <div class="card"
            style="text-align:center;<?php echo $expiringSoon > 0 ? 'border-color:rgba(239,68,68,.3)' : '' ?>">
            <div
                style="font-size:28px;font-weight:800;color:<?php echo $expiringSoon > 0 ? '#ef4444' : 'var(--gf-text-muted)' ?>">
                <?php echo $expiringSoon ?>
            </div>
            <div style="font-size:12px;color:var(--gf-text-muted);margin-top:4px">Vencen esta semana</div>
        </div>
    </div>

    <!-- ── Quick actions ─────────────────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

        <!-- Today's classes -->
        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
                <h3 style="font-size:15px;font-weight:700">Clases de hoy</h3>
                <a href="<?php echo BASE_URL ?>/pages/instructor/scheduler.php" class="btn btn-ghost btn-sm">Ver agenda
                    completa →</a>
            </div>
            <?php if (empty($todaySlots)): ?>
                <p style="font-size:13px;color:var(--gf-text-dim);text-align:center;padding:20px 0">No hay clases
                    programadas para hoy.</p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($todaySlots as $slot): ?>
                        <div
                            style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--gf-surface-2);border-radius:8px">
                            <div style="font-weight:700;font-size:14px;min-width:44px;color:var(--gf-accent)">
                                <?php echo substr($slot['start_time'], 0, 5) ?>
                            </div>
                            <div style="flex:1">
                                <div style="font-size:13px;font-weight:600">
                                    <?php echo htmlspecialchars($slot['template_name'] ?? 'Clase') ?>
                                </div>
                                <div style="font-size:11px;color:var(--gf-text-dim)">
                                    <?php echo htmlspecialchars($slot['sala_name'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <!-- Quick CRM links -->
        <div class="card">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:16px">Acciones rápidas</h3>
            <div style="display:flex;flex-direction:column;gap:10px">
                <a href="<?php echo BASE_URL ?>/pages/admin/members.php" class="btn btn-ghost"
                    style="justify-content:flex-start;gap:10px">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Ver todos los alumnos
                </a>
                <a href="<?php echo BASE_URL ?>/pages/admin/members.php?filter=overdue" class="btn btn-ghost"
                    style="justify-content:flex-start;gap:10px;<?php echo $pendingPayments > 0 ? 'color:#f59e0b' : '' ?>">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Cobros pendientes
                    <?php if ($pendingPayments > 0): ?>
                        <span
                            style="margin-left:auto;background:rgba(245,158,11,.15);color:#f59e0b;border-radius:20px;padding:2px 8px;font-size:11px">
                            <?php echo $pendingPayments ?>
                        </span>
                    <?php endif ?>
                </a>
                <a href="<?php echo BASE_URL ?>/pages/admin/membership-plans.php" class="btn btn-ghost"
                    style="justify-content:flex-start;gap:10px">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
                    </svg>
                    Gestionar planes
                </a>
                <a href="<?php echo BASE_URL ?>/pages/instructor/scheduler.php" class="btn btn-ghost"
                    style="justify-content:flex-start;gap:10px">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Editar agenda semanal
                </a>
                <a href="<?php echo BASE_URL ?>/pages/admin/gym-qr.php" class="btn btn-ghost"
                    style="justify-content:flex-start;gap:10px">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4" />
                    </svg>
                    Imprimir QR de check-in
                </a>
            </div>
        </div>

    </div>
</div>