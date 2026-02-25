<?php
/**
 * GymFlow CRM — Membership Plans ABM
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin', 'staff');
$gymId = (int) $user['gym_id'];

layout_header('Planes de Membresía', 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'plans');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'members', 'plans');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'plans', 'plans');
layout_footer($user);
?>

<div class="page-header">
    <div>
        <h1 style="font-size:20px;font-weight:700">📋 Planes de Membresía</h1>
        <div style="font-size:12px;color:var(--gf-text-muted)">Configurá los planes que ofrece tu gimnasio</div>
    </div>
    <div class="flex gap-2 ml-auto">
        <a href="<?php echo BASE_URL ?>/pages/admin/members.php" class="btn btn-secondary">← Alumnos</a>
        <button class="btn btn-primary" onclick="openModal()">+ Nuevo plan</button>
    </div>
</div>

<div class="page-body">
    <div id="plans-grid" class="grid grid-3" style="gap:16px"></div>
</div>

<!-- Plan Modal -->
<div class="modal-overlay" id="plan-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="plan-modal-title">Nuevo plan</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="plan-form" onsubmit="savePlan(event)">
                <input type="hidden" name="_id">
                <div class="form-group">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="name" class="input" placeholder="Ej: Mensual, 3×semana..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="input" rows="2"
                        placeholder="Acceso ilimitado, 3 veces por semana..."></textarea>
                </div>
                <div class="flex gap-3">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Precio</label>
                        <input type="number" name="price" class="input" min="0" step="0.01" placeholder="0">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="input">
                            <option value="ARS">ARS</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Duración (días)</label>
                        <input type="number" name="duration_days" class="input" min="1" value="30">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Límite de clases</label>
                        <input type="number" name="sessions_limit" class="input" min="1" placeholder="Ilimitado">
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" class="btn btn-secondary flex-1" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary flex-1">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?php echo BASE_URL ?>';

    async function loadPlans() {
        const res = await fetch(`${BASE_URL}/api/membership-plans.php?all=1`);
        const list = await res.json();
        const grid = document.getElementById('plans-grid');

        if (!list.length) {
            grid.innerHTML = '<p style="color:var(--gf-text-muted);grid-column:1/-1">Todavía no hay planes. Creá el primero.</p>';
            return;
        }

        grid.innerHTML = list.map(p => `
        <div class="card" style="padding:20px;opacity:${p.active ? 1 : .5}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
                <div>
                    <div style="font-weight:700;font-size:16px">${escHtml(p.name)}</div>
                    ${p.description ? `<div style="font-size:12px;color:var(--gf-text-muted);margin-top:2px">${escHtml(p.description)}</div>` : ''}
                </div>
                ${!p.active ? '<span style="font-size:11px;background:rgba(107,114,128,.15);color:#6b7280;padding:2px 7px;border-radius:5px">Inactivo</span>' : ''}
            </div>
            <div style="font-size:28px;font-weight:800;color:var(--gf-accent);margin:8px 0">
                ${p.currency} ${Number(p.price).toLocaleString('es-AR')}
            </div>
            <div style="font-size:12px;color:var(--gf-text-muted);margin-bottom:12px">
                📅 ${p.duration_days} días
                ${p.sessions_limit ? ` &nbsp;·&nbsp; 🏃 ${p.sessions_limit} clases` : ' &nbsp;·&nbsp; 🏃 Clases ilimitadas'}
            </div>
            <div class="flex gap-2">
                <button class="btn btn-secondary" style="flex:1;font-size:12px" onclick="editPlan(${JSON.stringify(p).replace(/"/g, '&quot;')})">Editar</button>
                <button class="btn btn-secondary" style="font-size:12px;color:${p.active ? '#ef4444' : '#10b981'}" onclick="togglePlan(${p.id},${p.active})">
                    ${p.active ? 'Desactivar' : 'Activar'}
                </button>
            </div>
        </div>
    `).join('');
    }

    function openModal(data = null) {
        const form = document.getElementById('plan-form');
        form.reset();
        form._id.value = '';
        document.getElementById('plan-modal-title').textContent = 'Nuevo plan';
        if (data) {
            document.getElementById('plan-modal-title').textContent = 'Editar plan';
            form._id.value = data.id;
            Object.entries(data).forEach(([k, v]) => { if (form[k]) form[k].value = v ?? ''; });
        }
        document.getElementById('plan-modal').classList.add('open');
    }

    function editPlan(data) { openModal(data); }
    function closeModal() { document.getElementById('plan-modal').classList.remove('open'); }

    async function savePlan(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form).entries());
        const id = data._id; delete data._id;
        const url = id ? `${BASE_URL}/api/membership-plans.php?id=${id}` : `${BASE_URL}/api/membership-plans.php`;
        const meth = id ? 'PUT' : 'POST';
        const res = await fetch(url, { method: meth, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
        const json = await res.json();
        if (json.success || json.id) { closeModal(); loadPlans(); }
        else alert(json.error || 'Error');
    }

    async function togglePlan(id, active) {
        await fetch(`${BASE_URL}/api/membership-plans.php?id=${id}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: active ? 0 : 1 })
        });
        loadPlans();
    }

    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    loadPlans();
</script>