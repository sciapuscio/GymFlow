<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');

// Stats
$gymCount = (int) db()->query("SELECT COUNT(*) FROM gyms")->fetchColumn();
$userCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetchColumn();
$sessionCount = (int) db()->query("SELECT COUNT(*) FROM gym_sessions")->fetchColumn();
$salaCount = (int) db()->query("SELECT COUNT(*) FROM salas")->fetchColumn();
$gymList = db()->query("SELECT g.*, COUNT(DISTINCT u.id) as user_count, COUNT(DISTINCT s.id) as sala_count FROM gyms g LEFT JOIN users u ON u.gym_id = g.id AND u.role != 'superadmin' LEFT JOIN salas s ON s.gym_id = g.id GROUP BY g.id ORDER BY g.name")->fetchAll();

layout_header('Super Admin', 'superadmin', $user);
nav_section('Super Admin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>', 'gyms', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/users.php', 'Usuarios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'users', 'superadmin');
layout_footer($user);
?>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Super Admin</h1>
    <a href="<?php echo BASE_URL ?>/pages/superadmin/gyms.php" class="btn btn-primary ml-auto">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Gimnasio
    </a>
</div>

<div class="page-body">
    <div class="grid grid-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon"><svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $gymCount ?>
                </div>
                <div class="stat-label">Gimnasios</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(255,107,53,.15);color:#ff6b35"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $userCount ?>
                </div>
                <div class="stat-label">Usuarios</div>
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
                <div class="stat-label">Sesiones totales</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $salaCount ?>
                </div>
                <div class="stat-label">Salas</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 style="font-size:16px;font-weight:700">Gimnasios</h2>
            <a href="<?php echo BASE_URL ?>/pages/superadmin/gyms.php" class="btn btn-ghost btn-sm">Gestionar</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Usuarios</th>
                        <th>Salas</th>
                        <th>Spotify</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gymList as $g): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div
                                        style="width:32px;height:32px;border-radius:8px;background:<?php echo htmlspecialchars($g['primary_color']) ?>26;display:flex;align-items:center;justify-content:center;font-weight:700;color:<?php echo htmlspecialchars($g['primary_color']) ?>;font-size:12px">
                                        <?php echo strtoupper(substr($g['name'], 0, 2)) ?>
                                    </div>
                                    <strong>
                                        <?php echo htmlspecialchars($g['name']) ?>
                                    </strong>
                                </div>
                            </td>
                            <td style="color:var(--gf-text-muted);font-size:13px">
                                <?php echo htmlspecialchars($g['slug']) ?>
                            </td>
                            <td>
                                <?php echo $g['user_count'] ?>
                            </td>
                            <td>
                                <?php echo $g['sala_count'] ?>
                            </td>
                            <td><span
                                    class="badge <?php echo $g['spotify_mode'] !== 'disabled' ? 'badge-success' : 'badge-muted' ?>">
                                    <?php echo $g['spotify_mode'] ?>
                                </span></td>
                            <td><span class="badge <?php echo $g['active'] ? 'badge-work' : 'badge-danger' ?>">
                                    <?php echo $g['active'] ? 'Activo' : 'Inactivo' ?>
                                </span></td>
                            <td style="display:flex;gap:6px">
                                <a href="<?php echo BASE_URL ?>/pages/admin/dashboard.php?gym_id=<?php echo $g['id'] ?>"
                                    class="btn btn-ghost btn-sm">Panel</a>
                                <button class="btn btn-danger btn-sm"
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

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    async function toggleGym(id, newActive) {
        await GF.put(`${window.GF_BASE}/api/gyms.php?id=${id}`, { active: newActive });
        location.reload();
    }
</script>
<?php layout_end(); ?>