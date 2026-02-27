<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');

// Stats
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];
$stmtSessions = db()->prepare("SELECT COUNT(*) FROM gym_sessions WHERE instructor_id = ? AND DATE(created_at) = CURDATE()");
$stmtSessions->execute([$user['id']]);
$todaySessions = (int) $stmtSessions->fetchColumn();

$stmtTotal = db()->prepare("SELECT COUNT(*) FROM gym_sessions WHERE instructor_id = ?");
$stmtTotal->execute([$user['id']]);
$totalSessions = (int) $stmtTotal->fetchColumn();

$stmtTemplates = db()->prepare("SELECT COUNT(*) FROM templates WHERE created_by = ?");
$stmtTemplates->execute([$user['id']]);
$totalTemplates = (int) $stmtTemplates->fetchColumn();

$stmtActive = db()->prepare("SELECT gs.*, s.name as sala_name FROM gym_sessions gs LEFT JOIN salas s ON gs.sala_id = s.id WHERE gs.instructor_id = ? AND gs.status IN ('playing','paused') LIMIT 1");
$stmtActive->execute([$user['id']]);
$activeSession = $stmtActive->fetch();

$stmtRecent = db()->prepare("SELECT gs.*, s.name as sala_name FROM gym_sessions gs LEFT JOIN salas s ON gs.sala_id = s.id WHERE gs.instructor_id = ? ORDER BY gs.created_at DESC LIMIT 8");
$stmtRecent->execute([$user['id']]);
$recentSessions = $stmtRecent->fetchAll();

$stmtSalas = db()->prepare("SELECT * FROM salas WHERE gym_id = ? AND active = 1 ORDER BY name");
$stmtSalas->execute([$gymId]);
$salas = $stmtSalas->fetchAll();

ob_start();
layout_header('Dashboard', 'dashboard', $user);
?>

<!-- Sidebar nav items -->
<?php
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'dashboard');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'dashboard');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'dashboard');
nav_item(BASE_URL . '/pages/instructor/library.php', 'Biblioteca', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>', 'library', 'dashboard');
nav_item(BASE_URL . '/pages/instructor/profile.php', 'Mi Perfil', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'profile', 'dashboard');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'dashboard');
if (in_array($user['role'], ['admin', 'superadmin'])) {
    nav_section('Administración');
    $adminBase = $user['role'] === 'superadmin' ? BASE_URL . '/pages/superadmin' : BASE_URL . '/pages/admin';
    nav_item("$adminBase/dashboard.php", 'Admin Panel', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'admin', 'dashboard');
}

layout_footer($user);
?>

<!-- Page Header -->
<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Dashboard</h1>
    <div class="ml-auto flex gap-2">
        <?php if ($activeSession): ?>
            <a href="<?php echo BASE_URL ?>/pages/instructor/live.php?id=<?php echo $activeSession['id'] ?>"
                class="btn btn-primary btn-sm">
                <div class="dot dot-live"></div> Sesión Activa:
                <?php echo htmlspecialchars($activeSession['name']) ?>
            </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL ?>/pages/instructor/builder.php" class="btn btn-primary">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva Sesión
        </a>
    </div>
</div>

<!-- Body -->
<div class="page-body">

    <!-- Stats -->
    <div class="grid grid-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div>
                <div class="stat-value">
                    <?php echo $todaySessions ?>
                </div>
                <div class="stat-label">Sesiones hoy</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(255,107,53,0.15);color:#ff6b35">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div>
                <div class="stat-value">
                    <?php echo $totalSessions ?>
                </div>
                <div class="stat-label">Total sesiones</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(124,58,237,0.15);color:#7c3aed">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <div>
                <div class="stat-value">
                    <?php echo $totalTemplates ?>
                </div>
                <div class="stat-label">Plantillas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:#10b981">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
            <div>
                <div class="stat-value">
                    <?php echo count($salas) ?>
                </div>
                <div class="stat-label">Salas</div>
            </div>
        </div>
    </div>

    <div class="grid grid-2">
        <!-- Recent Sessions -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 style="font-size:16px;font-weight:700">Sesiones Recientes</h2>
                <a href="<?php echo BASE_URL ?>/pages/instructor/sessions.php" class="btn btn-ghost btn-sm">Ver todo</a>
            </div>

            <?php if (empty($recentSessions)): ?>
                <div class="empty-state" style="padding:30px">
                    <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <h3>Sin sesiones aún</h3>
                    <p>Creá tu primera sesión con el Builder</p>
                    <a href="<?php echo BASE_URL ?>/pages/instructor/builder.php" class="btn btn-primary btn-sm">Crear
                        Sesión</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Sala</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSessions as $s): ?>
                                <tr>
                                    <td class="truncate" style="max-width:180px">
                                        <?php echo htmlspecialchars($s['name']) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badges = ['idle' => 'badge-muted', 'playing' => 'badge-work', 'paused' => 'badge-orange', 'finished' => 'badge-success'];
                                        $labels = ['idle' => 'Preparada', 'playing' => 'En Vivo', 'paused' => 'Pausada', 'finished' => 'Terminada'];
                                        $cls = $badges[$s['status']] ?? 'badge-muted';
                                        $lbl = $labels[$s['status']] ?? $s['status'];
                                        ?>
                                        <span class="badge <?php echo $cls ?>">
                                            <?php echo $lbl ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($s['sala_name'] ?? '—') ?>
                                    </td>
                                    <td>
                                        <?php if (in_array($s['status'], ['idle', 'paused'])): ?>
                                            <a href="<?php echo BASE_URL ?>/pages/instructor/live.php?id=<?php echo $s['id'] ?>"
                                                class="btn btn-primary btn-sm">▶ Iniciar</a>
                                        <?php elseif ($s['status'] === 'playing'): ?>
                                            <a href="<?php echo BASE_URL ?>/pages/instructor/live.php?id=<?php echo $s['id'] ?>"
                                                class="btn btn-sm" style="background:rgba(0,245,212,0.2);color:var(--gf-accent)">🔴
                                                Control</a>
                                        <?php else: ?>
                                            <button class="btn btn-ghost btn-sm"
                                                onclick="duplicateSession(<?php echo $s['id'] ?>)">Duplicar</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Salas Status -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 style="font-size:16px;font-weight:700">Estado de Salas</h2>
            </div>
            <?php if (empty($salas)): ?>
                <div class="empty-state" style="padding:30px">
                    <p style="color:var(--gf-text-dim)">No hay salas configuradas</p>
                </div>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($salas as $sala): ?>
                        <div
                            style="display:flex;align-items:center;gap:12px;padding:14px;background:var(--gf-surface-2);border-radius:10px;border:1px solid var(--gf-border)">
                            <div class="dot <?php echo $sala['current_session_id'] ? 'dot-live' : 'dot-idle' ?>"></div>
                            <div style="flex:1">
                                <div style="font-weight:600;font-size:14px">
                                    <?php echo htmlspecialchars($sala['name']) ?>
                                </div>
                                <div style="font-size:12px;color:var(--gf-text-muted)">
                                    <?php echo htmlspecialchars($sala['display_code']) ?>
                                </div>
                            </div>
                            <a href="<?php echo BASE_URL ?>/pages/display/sala.php?code=<?php echo urlencode($sala['display_code']) ?>"
                                target="_blank" class="btn btn-ghost btn-sm">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                                Display
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    const GYM_ID = <?php echo $gymId ?>;
    async function duplicateSession(id) {
        if (!confirm('¿Duplicar esta sesión?')) return;
        const r = await fetch(`${window.GF_BASE}/api/sessions.php?id=${id}`);
        const s = await r.json();
        const body = { name: s.name + ' (copia)', blocks_json: s.blocks_json, sala_id: s.sala_id, gym_id: GYM_ID };
        const r2 = await fetch(window.GF_BASE + '/api/sessions.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(body) });
        if (r2.ok) { showToast('Sesión duplicada', 'success'); location.reload(); }
    }
</script>

<?php layout_end(); ?>