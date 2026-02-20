<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');

$gymnList = db()->query("SELECT g.*, COUNT(DISTINCT u.id) as user_count, COUNT(DISTINCT s.id) as sala_count FROM gyms g LEFT JOIN users u ON u.gym_id = g.id AND u.role != 'superadmin' LEFT JOIN salas s ON s.gym_id = g.id GROUP BY g.id ORDER BY g.name")->fetchAll();

layout_header('Gimnasios — SuperAdmin', 'superadmin', $user);
nav_section('Super Admin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>', 'gyms', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/users.php', 'Usuarios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'users', 'superadmin');
layout_footer($user);
?>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Gimnasios</h1>
    <button class="btn btn-primary ml-auto" onclick="document.getElementById('gym-modal').classList.add('open')">+ Nuevo
        Gimnasio</button>
</div>

<div class="page-body">
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Usuarios</th>
                        <th>Salas</th>
                        <th>Color</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gymnList as $g): ?>
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
                            <td style="color:var(--gf-text-muted); font-size:13px">
                                <?php echo htmlspecialchars($g['slug']) ?>
                            </td>
                            <td>
                                <?php echo $g['user_count'] ?>
                            </td>
                            <td>
                                <?php echo $g['sala_count'] ?>
                            </td>
                            <td><span
                                    style="display:inline-block;width:16px;height:16px;border-radius:4px;background:<?php echo htmlspecialchars($g['primary_color']) ?>"></span>
                                <?php echo htmlspecialchars($g['primary_color']) ?>
                            </td>
                            <td><span class="badge <?php echo $g['active'] ? 'badge-work' : 'badge-danger' ?>">
                                    <?php echo $g['active'] ? 'Activo' : 'Inactivo' ?>
                                </span></td>
                            <td class="flex gap-2">
                                <a href="<?php echo BASE_URL ?>/pages/admin/dashboard.php?gym_id=<?php echo $g['id'] ?>"
                                    class="btn btn-ghost btn-sm">Panel</a>
                                <button class="btn btn-<?php echo $g['active'] ? 'danger' : 'primary' ?> btn-sm"
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

<!-- New Gym Modal -->
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
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Crear Gimnasio</button>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    async function toggleGym(id, newActive) {
        await GF.put(`${window.GF_BASE}/api/gyms.php?id=${id}`, { active: newActive });
        location.reload();
    }

    async function createGym(e) {
        e.preventDefault();
        const name = document.getElementById('g-name').value;
        const data = { name, slug: document.getElementById('g-slug').value, primary_color: document.getElementById('g-primary').value, secondary_color: document.getElementById('g-secondary').value };
        await GF.post(window.GF_BASE + '/api/gyms.php', data);
        showToast('Gimnasio creado', 'success');
        location.reload();
    }

    document.getElementById('g-name').addEventListener('input', function () {
        document.getElementById('g-slug').value = this.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
    });

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php layout_end(); ?>