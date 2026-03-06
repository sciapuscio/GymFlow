<?php
/**
 * GymFlow — Instructor Clients Page
 * Manage the list of clients that can receive shared sessions.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = (int) $user['gym_id'];

// Load clients with session access count
$clients = db()->prepare("
    SELECT ic.*,
           m.name  AS member_name_linked,
           m.email AS member_email_linked,
           (SELECT COUNT(*) FROM session_access_grants sag
            JOIN gym_sessions gs ON gs.id = sag.session_id
            WHERE sag.client_id = ic.id AND gs.gym_id = ?) AS session_count
    FROM instructor_clients ic
    LEFT JOIN members m ON m.id = ic.client_member_id
    WHERE ic.gym_id = ?
    ORDER BY ic.client_name
");
$clients->execute([$gymId, $gymId]);
$clients = $clients->fetchAll();

// Load own members for the picker
$members = db()->prepare("SELECT id, name, email FROM members WHERE gym_id = ? AND active = 1 ORDER BY name");
$members->execute([$gymId]);
$members = $members->fetchAll();

$svgClients = '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>';

layout_header('Mis Clientes', 'clientes', $user);
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'clientes');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'clientes');
nav_item(BASE_URL . '/pages/instructor/clientes.php', 'Clientes', $svgClients, 'clientes', 'clientes');
nav_item(BASE_URL . '/pages/instructor/compartidas.php', 'Compartidas', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>', 'compartidas', 'clientes');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'clientes');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'clientes');
layout_footer($user);
?>

<div class="page-header">
    <?= $svgClients ?>
    <h1 style="font-size:20px;font-weight:700">Mis Clientes</h1>
    <div style="margin-left:auto;display:flex;gap:8px">
        <button class="btn btn-primary" onclick="openAddModal()">+ Agregar cliente</button>
    </div>
</div>

<div class="page-body">

    <?php if (!empty($members)): ?>
        <div
            style="background:rgba(229,255,61,.05);border:1px solid rgba(229,255,61,.15);border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gf-text-muted)">
            💡 Podés agregar un cliente ingresando su email, o elegirlo de tu lista de alumnos si ya está en el sistema.
        </div>
    <?php endif ?>

    <?php if (empty($clients)): ?>
        <div class="card" style="padding:60px 30px;text-align:center">
            <?= $svgClients ?>
            <h3 style="margin-top:16px;margin-bottom:8px">Sin clientes aún</h3>
            <p style="color:var(--gf-text-muted);font-size:14px;margin-bottom:16px">
                Agregá los alumnos o personas a quienes querés compartirles sesiones.
            </p>
            <button class="btn btn-primary" onclick="openAddModal()">Agregar primer cliente</button>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
            <?php foreach ($clients as $c): ?>
                <div class="card" id="client-<?= $c['id'] ?>" style="padding:20px">
                    <div style="display:flex;align-items:flex-start;gap:12px">
                        <div
                            style="width:40px;height:40px;background:var(--gf-accent-dim);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;color:var(--gf-accent);flex-shrink:0">
                            <?= mb_strtoupper(mb_substr($c['client_name'], 0, 1)) ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div
                                style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?= htmlspecialchars($c['client_name']) ?>
                            </div>
                            <div style="font-size:12px;color:var(--gf-text-muted);margin-top:2px">
                                <?= htmlspecialchars($c['client_email']) ?>
                            </div>
                            <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
                                <?php if ($c['client_member_id']): ?>
                                    <span
                                        style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(16,185,129,.12);color:#10b981">✓
                                        GymFlow</span>
                                <?php else: ?>
                                    <span
                                        style="font-size:10px;padding:2px 8px;border-radius:20px;background:rgba(107,114,128,.1);color:var(--gf-text-dim)">Externo</span>
                                <?php endif ?>
                                <span
                                    style="font-size:10px;padding:2px 8px;border-radius:20px;background:rgba(59,130,246,.1);color:#60a5fa">
                                    <?= $c['session_count'] ?> sesión
                                    <?= $c['session_count'] != 1 ? 'es' : '' ?>
                                </span>
                            </div>
                        </div>
                        <button
                            onclick="removeClient(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['client_name'])) ?>')"
                            style="background:none;border:none;color:var(--gf-text-dim);cursor:pointer;padding:4px;border-radius:6px;flex-shrink:0"
                            title="Eliminar cliente">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<!-- Add Client Modal -->
<div class="modal-overlay" id="add-client-modal">
    <div class="modal" style="max-width:460px">
        <div class="modal-header">
            <h3 class="modal-title">Agregar cliente</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <?php if (!empty($members)): ?>
            <div style="padding:0 24px 8px">
                <div
                    style="font-size:12px;font-weight:700;color:var(--gf-text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em">
                    Elegir de alumnos actuales</div>
                <select id="member-picker" class="form-control" onchange="fillFromMember(this)" style="font-size:13px">
                    <option value="">— Seleccionar alumno —</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>" data-name="<?= htmlspecialchars($m['name']) ?>"
                            data-email="<?= htmlspecialchars($m['email']) ?>">
                            <?= htmlspecialchars($m['name']) ?> —
                            <?= htmlspecialchars($m['email']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                <div style="text-align:center;font-size:12px;color:var(--gf-text-dim);padding:10px 0">— o completá
                    manualmente —</div>
            </div>
        <?php endif ?>

        <form onsubmit="addClient(event)" style="padding:0 24px 24px">
            <div class="form-group" style="margin-bottom:14px">
                <label class="form-label">Nombre</label>
                <input id="c-name" class="form-control" required placeholder="María García">
            </div>
            <div class="form-group" style="margin-bottom:18px">
                <label class="form-label">Email</label>
                <input id="c-email" class="form-control" type="email" required placeholder="maria@ejemplo.com">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Agregar cliente</button>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/api.js"></script>
<script>
    const BASE = window.GF_BASE;

    function openAddModal() {
        document.getElementById('add-client-modal').classList.add('open');
    }

    function fillFromMember(sel) {
        const opt = sel.options[sel.selectedIndex];
        if (!opt.value) return;
        document.getElementById('c-name').value = opt.dataset.name;
        document.getElementById('c-email').value = opt.dataset.email;
    }

    async function addClient(e) {
        e.preventDefault();
        const name = document.getElementById('c-name').value.trim();
        const email = document.getElementById('c-email').value.trim();
        try {
            await GF.post(`${BASE}/api/instructor-clients.php`, { client_name: name, client_email: email });
            showToast('Cliente agregado ✓', 'success');
            setTimeout(() => location.reload(), 700);
        } catch (err) {
            showToast(err.message || 'Error al agregar', 'error');
        }
    }

    async function removeClient(id, name) {
        if (!confirm(`¿Eliminar a "${name}" de tus clientes? También se revocarán sus accesos a sesiones compartidas.`)) return;
        try {
            await GF.delete(`${BASE}/api/instructor-clients.php?id=${id}`);
            const el = document.getElementById(`client-${id}`);
            if (el) { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 320); }
            showToast('Cliente eliminado', 'success');
        } catch (err) {
            showToast('Error al eliminar', 'error');
        }
    }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php layout_end(); ?>