<?php
/**
 * GymFlow — Support: Global ticket list (superadmin)
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');

// Count open tickets for badge
$openCount = (int) db()->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress')")->fetchColumn();

layout_header('Soporte — Superadmin', 'superadmin', $user);
nav_section('Superadmin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'support');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gyms', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>', 'gyms', 'support');
nav_item(BASE_URL . '/pages/superadmin/support.php', 'Soporte', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>', 'support', 'support');
layout_footer($user);
?>

<div class="page-header">
    <div>
        <h1 style="font-size:20px;font-weight:700">🎧 Helpdesk
            <?php if ($openCount): ?>
                <span
                    style="font-size:12px;background:#ef4444;color:#fff;padding:2px 8px;border-radius:20px;vertical-align:middle">
                    <?php echo $openCount ?>
                </span>
            <?php endif ?>
        </h1>
        <div style="font-size:12px;color:var(--gf-text-muted)">Todos los casos de soporte</div>
    </div>
    <div class="ml-auto flex gap-2">
        <select id="filter-status" class="input" style="width:auto" onchange="loadTickets()">
            <option value="">Todos</option>
            <option value="open">Abiertos</option>
            <option value="in_progress">En curso</option>
            <option value="resolved">Resueltos</option>
            <option value="closed">Cerrados</option>
        </select>
    </div>
</div>

<div class="page-body">
    <div id="tickets-list">
        <div style="color:var(--gf-text-muted);font-size:13px">Cargando...</div>
    </div>
</div>

<script>
    const BASE_URL = '<?php echo BASE_URL ?>';
    const STATUS_LABEL = { open: 'Abierto', in_progress: 'En curso', resolved: 'Resuelto', closed: 'Cerrado' };
    const STATUS_COLOR = { open: '#f59e0b', in_progress: '#6366f1', resolved: '#10b981', closed: '#6b7280' };
    const PRI_COLOR = { low: '#6b7280', normal: 'var(--gf-text-muted)', high: '#ef4444' };
    const PRI_LABEL = { low: 'Baja', normal: 'Normal', high: 'Alta' };

    async function loadTickets() {
        const status = document.getElementById('filter-status').value;
        const url = `${BASE_URL}/api/support.php` + (status ? `?status=${status}` : '');
        const res = await fetch(url);
        const list = await res.json();
        const el = document.getElementById('tickets-list');

        if (!list.length) {
            el.innerHTML = `<div style="text-align:center;padding:60px 0;color:var(--gf-text-muted)">
            <div style="font-size:40px;margin-bottom:12px">✅</div>
            <div>Sin casos ${status ? 'con ese filtro' : 'registrados'}</div>
        </div>`;
            return;
        }

        el.innerHTML = `<div style="display:flex;flex-direction:column;gap:10px">` +
            list.map(t => `
        <a href="${BASE_URL}/pages/superadmin/support-ticket.php?id=${t.id}"
           style="display:block;text-decoration:none;color:inherit">
          <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:16px;cursor:pointer;transition:border-color .2s"
               onmouseover="this.style.borderColor='var(--gf-accent)'"
               onmouseout="this.style.borderColor=''">
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:14px;margin-bottom:4px">
                #${t.id} — ${escHtml(t.subject)}
              </div>
              <div style="font-size:12px;color:var(--gf-text-muted)">
                <strong>${escHtml(t.gym_name)}</strong>
                · ${escHtml(t.creator_name)}
                · ${fmtDate(t.updated_at)}
                · ${t.msg_count} mensaje${t.msg_count != 1 ? 's' : ''}
                · <span style="color:${PRI_COLOR[t.priority]}">${PRI_LABEL[t.priority]}</span>
              </div>
            </div>
            <span style="flex-shrink:0;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;
              background:${STATUS_COLOR[t.status]}22;color:${STATUS_COLOR[t.status]}">
              ${STATUS_LABEL[t.status]}
            </span>
          </div>
        </a>`).join('') + `</div>`;
    }

    function fmtDate(d) { return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }); }
    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    loadTickets();
</script>