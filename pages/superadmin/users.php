<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');
$users = db()->query("SELECT u.*, g.name as gym_name FROM users u LEFT JOIN gyms g ON u.gym_id = g.id WHERE u.role != 'superadmin' ORDER BY u.role, u.name")->fetchAll();
$gyms = db()->query("SELECT id, name FROM gyms ORDER BY name")->fetchAll();

layout_header('Usuarios — SuperAdmin', 'superadmin', $user);
nav_section('Super Admin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>', 'gyms', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/users.php', 'Usuarios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'users', 'superadmin');
layout_footer($user);
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
    /* ── Toolbar de filtros ── */
    .dt-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 16px;
    }

    .dt-toolbar .form-control {
        height: 36px;
        font-size: 13px;
        min-width: 160px;
        flex: 1;
        max-width: 240px;
    }

    .dt-toolbar .dt-search {
        flex: 2;
        max-width: 320px;
    }

    .dt-count {
        margin-left: auto;
        font-size: 12px;
        color: var(--gf-text-muted);
        white-space: nowrap;
        align-self: center;
    }

    /* ── Overrides DataTables para que encaje con el diseño ── */
    #usersTable_wrapper .dataTables_filter,
    #usersTable_wrapper .dataTables_length,
    #usersTable_wrapper .dataTables_info {
        display: none;
    }

    #usersTable_wrapper .dataTables_paginate {
        margin-top: 12px;
        display: flex;
        justify-content: flex-end;
    }

    #usersTable_wrapper .dataTables_paginate .paginate_button {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        color: var(--gf-text-muted) !important;
    }

    #usersTable_wrapper .dataTables_paginate .paginate_button.current,
    #usersTable_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--gf-accent) !important;
        color: #000 !important;
        border: none !important;
    }

    #usersTable_wrapper .dataTables_paginate .paginate_button.disabled {
        opacity: 0.35;
        cursor: default;
    }
</style>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Todos los Usuarios</h1>
    <button class="btn btn-primary ml-auto" onclick="document.getElementById('user-modal').classList.add('open')">+
        Nuevo Usuario</button>
</div>

<div class="page-body">
    <div class="card">

        <!-- Barra de filtros -->
        <div class="dt-toolbar">
            <input type="text" id="dt-search" class="form-control dt-search" placeholder="🔍  Buscar nombre o email…">

            <select id="dt-gym" class="form-control">
                <option value="">Todos los gimnasios</option>
                <?php foreach ($gyms as $g): ?>
                    <option value="<?php echo htmlspecialchars($g['name']) ?>">
                        <?php echo htmlspecialchars($g['name']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="—">Sin gimnasio</option>
            </select>

            <select id="dt-role" class="form-control">
                <option value="">Todos los roles</option>
                <option value="admin">Admin</option>
                <option value="instructor">Instructor</option>
            </select>

            <select id="dt-status" class="form-control">
                <option value="">Todos los estados</option>
                <option value="Activo">Activo</option>
                <option value="Inactivo">Inactivo</option>
            </select>

            <span class="dt-count" id="dt-count"></span>
        </div>

        <div class="table-wrap">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Gimnasio</th>
                        <th>Rol</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($u['name']) ?></strong></td>
                            <td style="font-size:12px;color:var(--gf-text-muted)">
                                <?php echo htmlspecialchars($u['email']) ?></td>
                            <td><?php echo htmlspecialchars($u['gym_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?php echo $u['role'] === 'admin' ? 'badge-orange' : 'badge-accent' ?>">
                                    <?php echo $u['role'] ?>
                                </span>
                            </td>
                            <td style="font-size:12px;color:var(--gf-text-muted)">
                                <?php echo $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Nunca' ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $u['active'] ? 'badge-work' : 'badge-danger' ?>">
                                    <?php echo $u['active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-danger btn-sm"
                                    onclick="deleteUser(<?php echo $u['id'] ?>)">Eliminar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- Modal nuevo usuario -->
<div class="modal-overlay" id="user-modal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title">Nuevo Usuario</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form onsubmit="createUser(event)">
            <div class="form-group"><label class="form-label">Nombre</label><input class="form-control" id="u-name"
                    required></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" class="form-control"
                    id="u-email" required></div>
            <div class="form-group"><label class="form-label">Contraseña</label><input type="password"
                    class="form-control" id="u-pass" required minlength="8"></div>
            <div class="form-group"><label class="form-label">Gimnasio</label>
                <select class="form-control" id="u-gym">
                    <option value="">Sin gimnasio</option>
                    <?php foreach ($gyms as $g): ?>
                        <option value="<?php echo $g['id'] ?>"><?php echo htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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

<!-- jQuery + DataTables -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    // ── DATATABLES INIT ───────────────────────────────────────────────────────
    const table = $('#usersTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        language: { paginate: { previous: '‹', next: '›' } },
        columnDefs: [
            { orderable: false, targets: 6 }  // columna acciones sin ordenar
        ]
    });

    // Actualizar contador
    function updateCount() {
        const info = table.page.info();
        const el = document.getElementById('dt-count');
        el.textContent = info.recordsDisplay === info.recordsTotal
            ? `${info.recordsTotal} usuarios`
            : `${info.recordsDisplay} de ${info.recordsTotal} usuarios`;
    }
    table.on('draw', updateCount);
    updateCount();

    // ── FILTROS CUSTOM ────────────────────────────────────────────────────────
    // Búsqueda de texto (nombre + email — columnas 0 y 1)
    $('#dt-search').on('input', function () {
        table.search(this.value).draw();
    });

    // Filtro gimnasio (columna 2) — búsqueda exacta
    $('#dt-gym').on('change', function () {
        table.column(2).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });

    // Filtro rol (columna 3)
    $('#dt-role').on('change', function () {
        table.column(3).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });

    // Filtro estado (columna 5)
    $('#dt-status').on('change', function () {
        table.column(5).search(this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '', true, false).draw();
    });

    // ── ACCIONES ─────────────────────────────────────────────────────────────
    async function createUser(e) {
        e.preventDefault();
        const data = {
            name: document.getElementById('u-name').value,
            email: document.getElementById('u-email').value,
            password: document.getElementById('u-pass').value,
            gym_id: +document.getElementById('u-gym').value || null,
            role: document.getElementById('u-role').value
        };
        await GF.post(window.GF_BASE + '/api/users.php', data);
        showToast('Usuario creado', 'success');
        location.reload();
    }

    async function deleteUser(id) {
        if (!confirm('¿Eliminar usuario?')) return;
        await GF.delete(`${window.GF_BASE}/api/users.php?id=${id}`);
        location.reload();
    }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php layout_end(); ?>