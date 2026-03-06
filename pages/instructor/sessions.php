<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where = ['gs.instructor_id = ?'];
$params = [$user['id']];

if ($statusFilter !== 'all') {
    $where[] = 'gs.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[] = 'gs.name LIKE ?';
    $params[] = '%' . $search . '%';
}

$stmt = db()->prepare(
    "SELECT gs.id, gs.name, gs.status, gs.total_duration, gs.created_at, gs.started_at,
            gs.shared, gs.share_description,
            s.name as sala_name, gs.sala_id
     FROM gym_sessions gs
     LEFT JOIN salas s ON gs.sala_id = s.id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY gs.created_at DESC LIMIT 100"
);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// Count by status for tabs
$stmtCounts = db()->prepare(
    "SELECT status, COUNT(*) as n FROM gym_sessions WHERE instructor_id = ? GROUP BY status"
);
$stmtCounts->execute([$user['id']]);
$counts = ['all' => 0, 'idle' => 0, 'playing' => 0, 'paused' => 0, 'finished' => 0];
foreach ($stmtCounts->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['n'];
    $counts['all'] += (int) $row['n'];
}

$svgSessions = '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>';

layout_header('Mis Sesiones', 'sessions', $user);
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'sessions');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'sessions');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', $svgSessions, 'sessions', 'sessions');
nav_item(BASE_URL . '/pages/instructor/library.php', 'Biblioteca', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>', 'library', 'sessions');
nav_item(BASE_URL . '/pages/instructor/profile.php', 'Mi Perfil', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'profile', 'sessions');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'sessions');
nav_section('Clientes');
nav_item(BASE_URL . '/pages/instructor/clientes.php', 'Clientes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'clientes', 'sessions');
nav_item(BASE_URL . '/pages/instructor/compartidas.php', 'Compartidas', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>', 'compartidas', 'sessions');
if (in_array($user['role'], ['admin', 'superadmin'])) {
    nav_section('Administración');
    $adminBase = $user['role'] === 'superadmin' ? BASE_URL . '/pages/superadmin' : BASE_URL . '/pages/admin';
    nav_item("$adminBase/dashboard.php", 'Admin Panel', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'admin', 'sessions');
}
layout_footer($user);

?>

<!-- Page Header -->
<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Mis Sesiones</h1>
    <div class="ml-auto flex gap-2">
        <a href="<?php echo BASE_URL ?>/pages/instructor/builder.php" class="btn btn-primary">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Nueva Sesión
        </a>
    </div>
</div>

<div class="page-body">

    <!-- Search + Filters -->
    <div class="card mb-4">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <div class="search-box" style="flex:1;min-width:200px">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" name="q" class="form-control" placeholder="Buscar sesiones..."
                    value="<?php echo htmlspecialchars($search) ?>" style="padding-left:36px">
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
                <?php
                $tabs = [
                    'all' => ['Todas', ''],
                    'idle' => ['Preparadas', '#6b7280'],
                    'playing' => ['En Vivo', '#00f5d4'],
                    'paused' => ['Pausadas', '#ff9800'],
                    'finished' => ['Terminadas', '#10b981'],
                ];
                foreach ($tabs as $val => [$lbl, $color]):
                    $active = $statusFilter === $val;
                    $cnt = $counts[$val] ?? 0;
                    ?>
                    <a href="?status=<?php echo $val ?><?php echo $search ? '&q=' . urlencode($search) : '' ?>"
                        class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-ghost' ?>"
                        style="<?php echo ($active && $color) ? "background:{$color}22;color:{$color};border-color:{$color}44" : '' ?>">
                        <?php echo $lbl ?> <span style="opacity:.6;font-size:11px">
                            <?php echo $cnt ?>
                        </span>
                    </a>
                <?php endforeach ?>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
        </form>
    </div>

    <!-- Sessions Table -->
    <?php if (empty($sessions)): ?>
        <div class="card">
            <div class="empty-state" style="padding:60px 30px">
                <?php echo $svgSessions ?>
                <h3 style="margin-top:16px">No hay sesiones
                    <?php echo $search ? " para \"$search\"" : '' ?>
                </h3>
                <p>Creá tu primera sesión con el Builder</p>
                <a href="<?php echo BASE_URL ?>/pages/instructor/builder.php" class="btn btn-primary btn-sm">Crear
                    Sesión</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Sala</th>
                            <th>Duración</th>
                            <th>Creada</th>
                            <th style="text-align:center">Compartir</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $badges = [
                            'idle' => ['badge-muted', 'Preparada'],
                            'playing' => ['badge-work', '🔴 En Vivo'],
                            'paused' => ['badge-orange', 'Pausada'],
                            'finished' => ['badge-success', 'Terminada'],
                        ];
                        foreach ($sessions as $s):
                            [$badgeCls, $badgeLbl] = $badges[$s['status']] ?? ['badge-muted', $s['status']];
                            $createdAt = (new DateTime($s['created_at']))->format('d/m/Y H:i');
                            ?>
                            <tr id="row-<?php echo $s['id'] ?>">
                                <td style="font-weight:600;max-width:240px">
                                    <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        <?php echo htmlspecialchars($s['name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeCls ?>">
                                        <?php echo $badgeLbl ?>
                                    </span>
                                </td>
                                <td style="color:var(--gf-text-muted);font-size:13px">
                                    <?php echo htmlspecialchars($s['sala_name'] ?? '—') ?>
                                </td>
                                <td style="font-size:13px;color:var(--gf-text-muted);white-space:nowrap">
                                    <?php echo $s['total_duration'] ? formatDuration((int) $s['total_duration']) : '—' ?>
                                </td>
                                <td style="font-size:12px;color:var(--gf-text-dim);white-space:nowrap">
                                    <?php echo $createdAt ?>
                                </td>
                                <td style="text-align:center">
                                    <button class="btn btn-ghost btn-sm <?= $s['shared'] ? 'text-accent' : '' ?>"
                                        style="<?= $s['shared'] ? 'color:var(--gf-accent)' : '' ?>"
                                        onclick="openShareModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')"
                                        title="<?= $s['shared'] ? 'Compartiendo' : 'No compartido' ?>">
                                        <?= $s['shared'] ? '🔗' : '○' ?>
                                    </button>
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    <div style="display:flex;gap:6px;justify-content:flex-end">
                                        <?php if (in_array($s['status'], ['idle', 'paused'])): ?>
                                            <a href="<?php echo BASE_URL ?>/pages/instructor/live.php?id=<?php echo $s['id'] ?>"
                                                class="btn btn-primary btn-sm">▶ Iniciar</a>
                                        <?php elseif ($s['status'] === 'playing'): ?>
                                            <a href="<?php echo BASE_URL ?>/pages/instructor/live.php?id=<?php echo $s['id'] ?>"
                                                class="btn btn-sm" style="background:rgba(0,245,212,0.15);color:var(--gf-accent)">🔴
                                                Control</a>
                                        <?php else: ?>
                                            <button class="btn btn-ghost btn-sm"
                                                onclick="duplicateSession(<?php echo $s['id'] ?>)">⧉ Duplicar</button>
                                        <?php endif ?>
                                        <a href="<?php echo BASE_URL ?>/pages/instructor/builder.php?id=<?php echo $s['id'] ?>"
                                            class="btn btn-ghost btn-sm" title="Editar en Builder">
                                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Editar
                                        </a>
                                        <?php if ($s['status'] !== 'playing'): ?>
                                            <button class="btn btn-ghost btn-sm" style="color:#ef4444"
                                                onclick="deleteSession(<?php echo $s['id'] ?>, '<?php echo htmlspecialchars(addslashes($s['name'])) ?>')">
                                                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        <?php endif ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:12px 20px;border-top:1px solid var(--gf-border);font-size:12px;color:var(--gf-text-dim)">
                <?php echo count($sessions) ?> sesion
                <?php echo count($sessions) !== 1 ? 'es' : '' ?> encontrada
                <?php echo count($sessions) !== 1 ? 's' : '' ?>
            </div>
        </div>
    <?php endif ?>

</div><!-- /.page-body -->

<!-- Share Modal -->
<div class="modal-overlay" id="share-modal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3 class="modal-title">🔗 Compartir sesión: <span id="share-modal-name" style="color:var(--gf-accent)"></span></h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div style="padding:0 24px 24px">
            <!-- Toggle compartir -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding:12px 14px;background:var(--gf-surface);border-radius:10px;border:1px solid var(--gf-border)">
                <span style="font-weight:600;font-size:14px">Activar compartir</span>
                <label style="cursor:pointer;display:flex;align-items:center;gap:8px">
                    <input type="checkbox" id="share-toggle" style="width:16px;height:16px;cursor:pointer" onchange="toggleShared(this)">
                    <span id="share-status-lbl" style="font-size:12px;color:var(--gf-text-muted)">Desactivado</span>
                </label>
            </div>

            <!-- Description -->
            <div class="form-group" style="margin-bottom:16px">
                <label class="form-label" style="font-size:12px">Descripción (opcional)</label>
                <textarea id="share-desc" class="form-control" rows="2" placeholder="Describe qué incluye esta sesión..." style="resize:vertical;font-size:13px"></textarea>
            </div>

            <!-- Clients list -->
            <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gf-text-muted);margin-bottom:10px">Clientes con acceso</div>
            <div id="share-clients-list" style="max-height:220px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;margin-bottom:16px">
                <div style="color:var(--gf-text-dim);font-size:13px;text-align:center;padding:20px">Cargando…</div>
            </div>

            <div style="display:flex;gap:8px">
                <button class="btn btn-primary" style="flex:1" onclick="saveSharing()">Guardar</button>
                <a href="<?= BASE_URL ?>/pages/instructor/clientes.php" class="btn btn-ghost btn-sm" style="align-self:center;font-size:12px">+ Agregar cliente</a>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/api.js"></script>
<script>
    let _shareSessionId  = null;
    let _shareClients    = [];
    let _checkedClients  = new Set();

    async function openShareModal(id, name) {
        _shareSessionId = id;
        document.getElementById('share-modal-name').textContent = name;
        document.getElementById('share-clients-list').innerHTML = '<div style="color:var(--gf-text-dim);font-size:13px;text-align:center;padding:20px">Cargando…</div>';
        document.getElementById('share-modal').classList.add('open');

        try {
            const data = await GF.get(`${window.GF_BASE}/api/session-sharing.php?session_id=${id}`);
            const sess = data.session;
            _shareClients   = data.clients || [];
            _checkedClients = new Set(_shareClients.filter(c => c.has_access == 1).map(c => c.id));

            document.getElementById('share-toggle').checked = sess.shared == 1;
            document.getElementById('share-status-lbl').textContent = sess.shared == 1 ? 'Activo' : 'Desactivado';
            document.getElementById('share-desc').value  = sess.share_description || '';

            renderClients();
        } catch(e) {
            document.getElementById('share-clients-list').innerHTML = '<div style="color:#ef4444;font-size:13px;text-align:center;padding:20px">Error al cargar</div>';
        }
    }

    function renderClients() {
        const el = document.getElementById('share-clients-list');
        if (!_shareClients.length) {
            el.innerHTML = '<div style="text-align:center;padding:20px;font-size:13px;color:var(--gf-text-dim)">Sin clientes. <a href="<?= BASE_URL ?>/pages/instructor/clientes.php" style="color:var(--gf-accent)">Agregar →</a></div>';
            return;
        }
        el.innerHTML = _shareClients.map(c => `
            <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--gf-surface);border:1px solid var(--gf-border);border-radius:8px;cursor:pointer">
                <input type="checkbox" ${_checkedClients.has(c.id) ? 'checked' : ''} onchange="toggleClient(${c.id}, this.checked)" style="width:15px;height:15px;flex-shrink:0">
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:13px">${escHtml(c.client_name)}</div>
                    <div style="font-size:11px;color:var(--gf-text-dim)">${escHtml(c.client_email)}</div>
                </div>
                ${c.client_member_id ? '<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;background:rgba(16,185,129,.12);color:#10b981">GymFlow</span>' : ''}
            </label>
        `).join('');
    }

    function toggleClient(id, checked) {
        if (checked) _checkedClients.add(id); else _checkedClients.delete(id);
    }

    function toggleShared(cb) {
        document.getElementById('share-status-lbl').textContent = cb.checked ? 'Activo' : 'Desactivado';
    }

    async function saveSharing() {
        const shared = document.getElementById('share-toggle').checked ? 1 : 0;
        const desc   = document.getElementById('share-desc').value.trim();
        try {
            await GF.post(`${window.GF_BASE}/api/session-sharing.php`, {
                session_id: _shareSessionId,
                shared,
                share_description: desc,
                client_ids: [..._checkedClients],
            });
            showToast(shared ? '🔗 Sesión compartida' : 'Compartir desactivado', 'success');
            document.getElementById('share-modal').classList.remove('open');
            // Update button icon in table
            const btn = document.querySelector(`[onclick="openShareModal(${_shareSessionId}, '${document.getElementById('share-modal-name').textContent.replace(/'/g,"\\'")}')"]`);
            if (btn) { btn.textContent = shared ? '🔗' : '○'; btn.style.color = shared ? 'var(--gf-accent)' : ''; }
        } catch(e) {
            showToast(e.message || 'Error al guardar', 'error');
        }
    }

    function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });

    async function duplicateSession(id) {
        if (!confirm('¿Duplicar esta sesión?')) return;
        try {
            const s = await GF.get(`${window.GF_BASE}/api/sessions.php?id=${id}`);
            await GF.post(window.GF_BASE + '/api/sessions.php', {
                name: s.name + ' (copia)',
                blocks_json: s.blocks_json,
                sala_id: s.sala_id ?? null,
            });
            showToast('Sesión duplicada', 'success');
            setTimeout(() => location.reload(), 700);
        } catch (e) {
            showToast('Error al duplicar', 'error');
        }
    }

    async function deleteSession(id, name) {
        if (!confirm(`¿Eliminar la sesión "${name}"? Esta acción no se puede deshacer.`)) return;
        try {
            await GF.delete(`${window.GF_BASE}/api/sessions.php?id=${id}`);
            const row = document.getElementById(`row-${id}`);
            if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .3s'; setTimeout(() => row.remove(), 320); }
            showToast('Sesión eliminada', 'success');
        } catch (e) {
            showToast('Error al eliminar', 'error');
        }
    }
</script>

<?php layout_end(); ?>