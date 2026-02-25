<?php
/**
 * GymFlow — Support: Ticket thread (admin / instructor / staff)
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin', 'instructor', 'staff');
$ticketId = (int) ($_GET['id'] ?? 0);
if (!$ticketId) {
    header('Location: ' . BASE_URL . '/pages/admin/support.php');
    exit;
}

layout_header('Caso #' . $ticketId . ' — Soporte', 'admin', $user);
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
    <a href="<?php echo BASE_URL ?>/pages/admin/support.php"
        style="color:var(--gf-text-muted);text-decoration:none;font-size:20px">←</a>
    <div style="margin-left:12px">
        <h1 id="ticket-title" style="font-size:18px;font-weight:700">Cargando...</h1>
        <div id="ticket-meta" style="font-size:12px;color:var(--gf-text-muted)"></div>
    </div>
    <div id="ticket-actions" class="ml-auto flex gap-2"></div>
</div>

<div class="page-body" style="max-width:760px">
    <div id="thread" style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px"></div>

    <div id="reply-area" class="card" style="padding:20px">
        <h4 style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--gf-text-muted)">RESPONDER</h4>
        <form id="reply-form" onsubmit="sendReply(event)">
            <textarea name="message" class="input" rows="4" placeholder="Escribí tu mensaje..."
                style="margin-bottom:12px" required></textarea>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Enviar respuesta</button>
            </div>
        </form>
    </div>
</div>

<script>
    const BASE_URL = '<?php echo BASE_URL ?>';
    const TICKET_ID = <?php echo $ticketId ?>;
    const STATUS_LABEL = { open: 'Abierto', in_progress: 'En curso', resolved: 'Resuelto', closed: 'Cerrado' };
    const STATUS_COLOR = { open: '#f59e0b', in_progress: '#6366f1', resolved: '#10b981', closed: '#6b7280' };
    const PRI_LABEL = { low: 'Baja', normal: 'Normal', high: 'Alta' };

    async function loadTicket() {
        const res = await fetch(`${BASE_URL}/api/support.php?id=${TICKET_ID}`);
        const ticket = await res.json();
        if (ticket.error) { document.getElementById('ticket-title').textContent = ticket.error; return; }

        const isClosed = ticket.status === 'closed';

        document.getElementById('ticket-title').textContent = `#${ticket.id} — ${ticket.subject}`;
        document.getElementById('ticket-meta').innerHTML =
            `<span style="color:${STATUS_COLOR[ticket.status]};font-weight:700">${STATUS_LABEL[ticket.status]}</span>
         &nbsp;·&nbsp; Prioridad: ${PRI_LABEL[ticket.priority]}
         &nbsp;·&nbsp; Abierto ${fmtDate(ticket.created_at)}`;

        // Actions
        const actEl = document.getElementById('ticket-actions');
        if (!isClosed) {
            actEl.innerHTML = `<button class="btn btn-secondary btn-sm" onclick="closeTicket()">✖ Cerrar caso</button>`;
        }

        // Thread
        const thread = document.getElementById('thread');
        thread.innerHTML = ticket.messages.map(m => {
            const isSA = m.author_role === 'superadmin';
            return `<div style="display:flex;flex-direction:column;align-items:${isSA ? 'flex-start' : 'flex-end'}">
          <div style="max-width:85%;background:${isSA ? 'var(--gf-surface)' : 'rgba(0,245,212,.08)'};
            border:1px solid ${isSA ? 'var(--gf-border)' : 'rgba(0,245,212,.2)'};
            border-radius:${isSA ? '4px 14px 14px 14px' : '14px 4px 14px 14px'};padding:12px 16px">
            <div style="font-size:11px;color:var(--gf-text-muted);margin-bottom:6px">
              <strong style="color:${isSA ? 'var(--gf-accent-2)' : 'var(--gf-accent)'}">${escHtml(m.author_name)}</strong>
              ${isSA ? '· GymFlow' : ''} &nbsp;·&nbsp; ${fmtDateTime(m.created_at)}
            </div>
            <div style="font-size:14px;white-space:pre-wrap;line-height:1.6">${escHtml(m.message)}</div>
          </div>
        </div>`;
        }).join('');

        // Hide reply area if closed
        if (isClosed) document.getElementById('reply-area').style.display = 'none';
    }

    async function sendReply(e) {
        e.preventDefault();
        const msg = e.target.message.value.trim();
        if (!msg) return;
        const res = await fetch(`${BASE_URL}/api/support.php?ticket_id=${TICKET_ID}`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message: msg })
        });
        const json = await res.json();
        if (json.id) { e.target.message.value = ''; loadTicket(); }
        else alert(json.error || 'Error');
    }

    async function closeTicket() {
        const res = await fetch(`${BASE_URL}/api/support.php?id=${TICKET_ID}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status: 'closed' })
        });
        const json = await res.json();
        if (json.ok) loadTicket();
        else alert(json.error || 'Error');
    }

    function fmtDate(d) { return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' }); }
    function fmtDateTime(d) { return new Date(d).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); }
    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '&#10;'); }

    loadTicket();
</script>