<?php
/**
 * GymFlow — Support: Ticket list (admin / instructor / staff)
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin', 'instructor', 'staff');
$gymId = (int) $user['gym_id'];

layout_header('Soporte', 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'support');
if ($user['role'] === 'staff')
    nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Agenda', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'support');
else
    nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Instructor', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>', 'instructor', 'support');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'members', 'support');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'plans', 'support');
nav_item(BASE_URL . '/pages/admin/gym-qr.php', 'QR Check-in', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>', 'gym-qr', 'support');
nav_item(BASE_URL . '/pages/admin/support.php', 'Soporte', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>', 'support', 'support');
layout_footer($user);
?>

<div class="page-header">
    <div>
        <h1 style="font-size:20px;font-weight:700">🎧 Soporte</h1>
        <div style="font-size:12px;color:var(--gf-text-muted)">Contactá al equipo de GymFlow</div>
    </div>
    <button class="btn btn-primary ml-auto" onclick="openNewModal()">+ Nuevo caso</button>
</div>

<div class="page-body">
    <div id="tickets-list">
        <div style="color:var(--gf-text-muted);font-size:13px">Cargando...</div>
    </div>
</div>

<!-- New Ticket Modal -->
<div class="modal-overlay" id="new-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nuevo caso de soporte</h3>
            <button class="modal-close" onclick="closeNewModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="new-form" onsubmit="createTicket(event)">
                <div class="form-group">
                    <label class="form-label">Asunto *</label>
                    <input type="text" name="subject" class="input" placeholder="Ej: No puedo crear usuarios" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Prioridad</label>
                    <select name="priority" class="input">
                        <option value="low">Baja</option>
                        <option value="normal" selected>Normal</option>
                        <option value="high">Alta 🔴</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción *</label>
                    <textarea name="message" class="input" rows="4" placeholder="Describí el problema con detalle..."
                        required></textarea>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="button" class="btn btn-secondary flex-1" onclick="closeNewModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary flex-1">Enviar caso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?php echo BASE_URL ?>';

    const STATUS_LABEL = { open: 'Abierto', in_progress: 'En curso', resolved: 'Resuelto', closed: 'Cerrado' };
    const STATUS_COLOR = { open: '#f59e0b', in_progress: '#6366f1', resolved: '#10b981', closed: '#6b7280' };
    const PRI_LABEL = { low: 'Baja', normal: 'Normal', high: 'Alta' };
    const PRI_COLOR = { low: '#6b7280', normal: 'var(--gf-text-muted)', high: '#ef4444' };

    async function loadTickets() {
        const res = await fetch(`${BASE_URL}/api/support.php`);
        const list = await res.json();
        const el = document.getElementById('tickets-list');

        if (!list.length) {
            el.innerHTML = `<div style="text-align:center;padding:60px 0;color:var(--gf-text-muted)">
            <div style="font-size:40px;margin-bottom:12px">🎧</div>
            <div style="font-weight:600;margin-bottom:6px">Sin casos abiertos</div>
            <div style="font-size:13px">Abrí un caso si necesitás ayuda del equipo GymFlow.</div>
        </div>`;
            return;
        }

        el.innerHTML = `<div style="display:flex;flex-direction:column;gap:10px">` +
            list.map(t => `
        <a href="${BASE_URL}/pages/admin/support-ticket.php?id=${t.id}"
           style="display:block;text-decoration:none;color:inherit">
          <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:16px;cursor:pointer;transition:border-color .2s"
               onmouseover="this.style.borderColor='var(--gf-accent)'"
               onmouseout="this.style.borderColor=''">
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:14px;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                #${t.id} — ${escHtml(t.subject)}
              </div>
              <div style="font-size:12px;color:var(--gf-text-muted)">
                ${fmtDate(t.created_at)} · ${t.msg_count} mensaje${t.msg_count != 1 ? 's' : ''}
                · Prioridad: <span style="color:${PRI_COLOR[t.priority]}">${PRI_LABEL[t.priority]}</span>
              </div>
            </div>
            <div style="flex-shrink:0">
              <span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;
                background:${STATUS_COLOR[t.status]}22;color:${STATUS_COLOR[t.status]}">
                ${STATUS_LABEL[t.status]}
              </span>
            </div>
          </div>
        </a>`).join('') + `</div>`;
    }

    function openNewModal() { document.getElementById('new-modal').classList.add('open'); }
    function closeNewModal() { document.getElementById('new-modal').classList.remove('open'); }

    async function createTicket(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target).entries());
        const res = await fetch(`${BASE_URL}/api/support.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.id) {
            closeNewModal();
            window.location.href = `${BASE_URL}/pages/admin/support-ticket.php?id=${json.id}`;
        } else {
            alert(json.error || 'Error');
        }
    }

    function fmtDate(d) { return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' }); }
    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    loadTickets();
</script>