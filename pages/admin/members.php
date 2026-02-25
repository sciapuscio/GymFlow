<?php
/**
 * GymFlow CRM — Members List
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin');
$gymId = (int) $user['gym_id'];

// CRM Stats
$statsQ = db()->prepare("
    SELECT
        (SELECT COUNT(*) FROM members WHERE gym_id = ? AND active = 1) AS total_active,
        (SELECT COUNT(*) FROM member_memberships WHERE gym_id = ? AND end_date >= CURDATE()) AS with_active_membership,
        (SELECT COUNT(*) FROM member_memberships WHERE gym_id = ? AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS expiring_soon,
        (SELECT COUNT(*) FROM member_memberships WHERE gym_id = ? AND payment_status IN ('pending','overdue') AND end_date >= CURDATE()) AS pending_payment
");
$statsQ->execute([$gymId, $gymId, $gymId, $gymId]);
$stats = $statsQ->fetch();

layout_header('Alumnos — CRM', 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'admin', 'admin');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'admin', 'admin');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'admin', 'admin');
layout_footer($user);
?>

<div class="page-header">
    <div>
        <h1 style="font-size:20px;font-weight:700">👥 Alumnos</h1>
        <div style="font-size:12px;color:var(--gf-text-muted)">Gestión de miembros del gimnasio</div>
    </div>
    <div class="flex gap-2 ml-auto">
        <a href="<?php echo BASE_URL ?>/pages/admin/membership-plans.php" class="btn btn-secondary">📋 Planes</a>
        <button class="btn btn-primary" onclick="openNewMemberModal()">+ Nuevo alumno</button>
    </div>
</div>

<div class="page-body">

    <!-- Stats CRM -->
    <div class="grid grid-4 mb-6">
        <div class="stat-card" onclick="filterBy('all')" style="cursor:pointer">
            <div class="stat-icon"><svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg></div>
            <div>
                <div class="stat-value" id="stat-total">
                    <?php echo (int) $stats['total_active'] ?>
                </div>
                <div class="stat-label">Alumnos activos</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterBy('active')" style="cursor:pointer">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg></div>
            <div>
                <div class="stat-value" style="color:#10b981">
                    <?php echo (int) $stats['with_active_membership'] ?>
                </div>
                <div class="stat-label">Con membresía vigente</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterBy('pending')" style="cursor:pointer">
            <div class="stat-icon" style="background:rgba(245,158,11,.15);color:#f59e0b"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg></div>
            <div>
                <div class="stat-value" style="color:#f59e0b">
                    <?php echo (int) $stats['pending_payment'] ?>
                </div>
                <div class="stat-label">Pagos pendientes</div>
            </div>
        </div>
        <div class="stat-card" onclick="filterBy('expired')" style="cursor:pointer">
            <div class="stat-icon" style="background:rgba(239,68,68,.15);color:#ef4444"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg></div>
            <div>
                <div class="stat-value" style="color:#ef4444">
                    <?php echo (int) $stats['expiring_soon'] ?>
                </div>
                <div class="stat-label">Vencen esta semana</div>
            </div>
        </div>
    </div>

    <!-- Search + Filter -->
    <div class="card mb-4" style="padding:16px">
        <div class="flex gap-3 align-center flex-wrap">
            <input type="text" id="search-input" class="input" placeholder="🔍 Buscar por nombre, email o teléfono..."
                style="flex:1;min-width:200px" oninput="debounceSearch()">
            <select id="status-filter" class="input" style="width:auto" onchange="loadMembers()">
                <option value="all">Todos</option>
                <option value="active">Con membresía activa</option>
                <option value="pending">Pago pendiente</option>
                <option value="expired">Vencidos</option>
                <option value="no_membership">Sin membresía</option>
            </select>
        </div>
    </div>

    <!-- Members table -->
    <div class="card" style="overflow:hidden">
        <table class="table" id="members-table">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Plan</th>
                    <th>Vencimiento</th>
                    <th>Estado pago</th>
                    <th>Clases</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody id="members-tbody">
                <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:var(--gf-text-muted)">Cargando...</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<!-- New Member Modal -->
<div class="modal-overlay" id="new-member-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nuevo alumno</h3>
            <button class="modal-close" onclick="closeModal('new-member-modal')">✕</button>
        </div>
        <div class="modal-body">
            <form id="new-member-form" onsubmit="saveMember(event)">
                <div class="form-group">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="name" class="input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="input">
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" name="phone" class="input">
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de nacimiento</label>
                    <input type="date" name="birth_date" class="input">
                </div>
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" class="input" rows="2"></textarea>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" class="btn btn-secondary flex-1"
                        onclick="closeModal('new-member-modal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary flex-1">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?php echo BASE_URL ?>';
    let searchTimer = null;

    const STATUS_LABELS = {
        active: { label: 'Activo', color: '#10b981', bg: 'rgba(16,185,129,.12)' },
        pending: { label: 'Pago pendiente', color: '#f59e0b', bg: 'rgba(245,158,11,.12)' },
        overdue: { label: 'Deudor', color: '#ef4444', bg: 'rgba(239,68,68,.12)' },
        partial: { label: 'Pago parcial', color: '#f59e0b', bg: 'rgba(245,158,11,.12)' },
        expired: { label: 'Vencido', color: '#6b7280', bg: 'rgba(107,114,128,.12)' },
        no_membership: { label: 'Sin membresía', color: '#6b7280', bg: 'rgba(107,114,128,.12)' },
    };

    function filterBy(status) {
        document.getElementById('status-filter').value = status;
        loadMembers();
    }

    function debounceSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadMembers, 350);
    }

    async function loadMembers() {
        const q = document.getElementById('search-input').value;
        const status = document.getElementById('status-filter').value;
        const tbody = document.getElementById('members-tbody');
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--gf-text-muted)">Cargando...</td></tr>';

        const res = await fetch(`${BASE_URL}/api/members.php?q=${encodeURIComponent(q)}&status=${status}`);
        const data = await res.json();
        const list = Array.isArray(data) ? data : (data.members || []);

        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--gf-text-muted)">Sin resultados</td></tr>';
            return;
        }

        tbody.innerHTML = list.map(m => {
            const st = STATUS_LABELS[m.membership_status] || STATUS_LABELS.no_membership;
            const badgeStyle = `background:${st.bg};color:${st.color};border-radius:6px;padding:2px 8px;font-size:11px;font-weight:600;white-space:nowrap`;
            const venc = m.end_date ? new Date(m.end_date).toLocaleDateString('es-AR') : '—';
            const clases = m.sessions_limit ? `${m.sessions_used || 0}/${m.sessions_limit}` : (m.sessions_used ? m.sessions_used : '—');
            return `<tr>
            <td>
                <div style="font-weight:600">${escHtml(m.name)}</div>
                <div style="font-size:11px;color:var(--gf-text-muted)">${escHtml(m.email || m.phone || '')}</div>
            </td>
            <td>${escHtml(m.plan_name || '—')}</td>
            <td>${venc}</td>
            <td><span style="${badgeStyle}">${st.label}</span></td>
            <td>${clases}</td>
            <td>
                <div class="flex gap-1">
                    <a href="${BASE_URL}/pages/admin/member-detail.php?id=${m.id}" class="btn btn-secondary" style="padding:4px 10px;font-size:12px">Ver</a>
                    <button class="btn btn-secondary" style="padding:4px 10px;font-size:12px;color:#ef4444" onclick="deactivateMember(${m.id},'${escHtml(m.name)}')">✕</button>
                </div>
            </td>
        </tr>`;
        }).join('');
    }

    async function saveMember(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form).entries());
        const res = await fetch(`${BASE_URL}/api/members.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const json = await res.json();
        if (json.success) {
            closeModal('new-member-modal');
            form.reset();
            loadMembers();
        } else {
            alert(json.error || 'Error al guardar');
        }
    }

    async function deactivateMember(id, name) {
        if (!confirm(`¿Desactivar a ${name}?`)) return;
        await fetch(`${BASE_URL}/api/members.php?id=${id}`, { method: 'DELETE' });
        loadMembers();
    }

    function openNewMemberModal() { document.getElementById('new-member-modal').classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }
    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    loadMembers();
</script>