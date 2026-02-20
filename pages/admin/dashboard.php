<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

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

layout_header('Admin Panel — ' . $gym['name'], 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'admin', 'admin');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Instructor', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>', 'instructor', 'admin');
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
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="2" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" />
                </svg>
            </div>
            <div>
                <div class="stat-value" style="font-size:16px;color:<?php echo htmlspecialchars($gym['primary_color']) ?>">
                    <?php echo htmlspecialchars($gym['primary_color']) ?>
                </div>
                <div class="stat-label">Color Primario</div>
            </div>
        </div>
    </div>

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
                                <td><span class="badge <?php echo $u['role'] === 'admin' ? 'badge-orange' : 'badge-accent' ?>">
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
                        class="form-control" id="b-primary" value="<?php echo htmlspecialchars($gym['primary_color']) ?>">
                </div>
                <div class="form-group"><label class="form-label">Color Secundario</label><input type="color"
                        class="form-control" id="b-secondary" value="<?php echo htmlspecialchars($gym['secondary_color']) ?>">
                </div>
            </div>
            <div class="form-group"><label class="form-label">Logo (URL o subir)</label><input class="form-control"
                    id="b-logo" placeholder="https://... o subir"
                    value="<?php echo htmlspecialchars($gym['logo_path'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Nombre del Gimnasio</label><input class="form-control"
                    id="b-name" value="<?php echo htmlspecialchars($gym['name']) ?>"></div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Guardar Branding</button>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    const GYM_ID = <?php echo $gymId ?>;

    async function createSala(e) {
        e.preventDefault();
        const form = e.target;
        const data = { name: form.name.value, capacity: +form.capacity.value, gym_id: GYM_ID };
        await GF.post(window.GF_BASE + '/api/salas.php', data);
        showToast('Sala creada', 'success');
        location.reload();
    }

    async function deleteSala(id) {
        if (!confirm('¿Eliminar sala?')) return;
        await GF.delete(`${window.GF_BASE}/api/salas.php?id=${id}`);
        location.reload();
    }

    async function createUser(e) {
        e.preventDefault();
        const data = { name: document.getElementById('u-name').value, email: document.getElementById('u-email').value, password: document.getElementById('u-pass').value, role: document.getElementById('u-role').value, gym_id: GYM_ID };
        await GF.post(window.GF_BASE + '/api/users.php', data);
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
        const data = { primary_color: document.getElementById('b-primary').value, secondary_color: document.getElementById('b-secondary').value, logo_path: document.getElementById('b-logo').value, name: document.getElementById('b-name').value };
        await GF.put(`${window.GF_BASE}/api/gyms.php?id=${GYM_ID}`, data);
        showToast('Branding guardado', 'success');
        setTimeout(() => location.reload(), 800);
    }

    // Close modal on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php layout_end(); ?>