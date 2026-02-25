<?php
/**
 * GymFlow — Support: Ticket thread (superadmin view)
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');
$ticketId = (int) ($_GET['id'] ?? 0);
if (!$ticketId) {
    header('Location: ' . BASE_URL . '/pages/superadmin/support.php');
    exit;
}

layout_header('Caso #' . $ticketId . ' — Soporte SA', 'superadmin', $user);
nav_section('Superadmin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'support');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gyms', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>', 'gyms', 'support');
nav_item(BASE_URL . '/pages/superadmin/support.php', 'Soporte', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>', 'support', 'support');
layout_footer($user);
?>

<div class="page-header">
    <a href="<?php echo BASE_URL ?>/pages/superadmin/support.php"
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
            <textarea name="message" class="input" rows="4" placeholder="Respuesta al cliente..."
                style="margin-bottom:10px" required></textarea>
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
         &nbsp;· <strong>${ticket.gym_name}</strong>
         &nbsp;· ${ticket.creator_name}
         &nbsp;· Prioridad ${PRI_LABEL[ticket.priority]}`;

        // Actions (status buttons)
        const actEl = document.getElementById('ticket-actions');
        const actions = [
            { status: 'open', label: 'Reabrir', show: isClosed },
            { status: 'in_progress', label: 'En curso', show: ticket.status !== 'in_progress' && !isClosed },
            { status: 'resolved', label: '✔ Resuelto', show: ticket.status === 'in_progress' },
            { status: 'closed', label: '✖ Cerrar', show: !isClosed },
        ];
        actEl.innerHTML = actions.filter(a => a.show).map(a =>
            `<button class="btn btn-secondary btn-sm" onclick="setStatus('${a.status}')">${a.label}</button>`
        ).join('');

        // Thread
        const thread = document.getElementById('thread');
        thread.innerHTML = ticket.messages.map(m => {
            const isSA = m.author_role === 'superadmin';
            return `<div style="display:flex;flex-direction:column;align-items:${isSA ? 'flex-end' : 'flex-start'}">
          <div style="max-width:85%;background:${isSA ? 'rgba(0,245,212,.08)' : 'var(--gf-surface)'};
            border:1px solid ${isSA ? 'rgba(0,245,212,.2)' : 'var(--gf-border)'};
            border-radius:${isSA ? '14px 4px 14px 14px' : '4px 14px 14px 14px'};padding:12px 16px">
            <div style="font-size:11px;color:var(--gf-text-muted);margin-bottom:6px">
              <strong style="color:${isSA ? 'var(--gf-accent)' : 'var(--gf-accent-2)'}">${escHtml(m.author_name)}</strong>
              ${isSA ? '· GymFlow' : `· ${escHtml(m.author_role)}`}
              &nbsp;·&nbsp; ${fmtDateTime(m.created_at)}
            </div>
            <div style="font-size:14px;white-space:pre-wrap;line-height:1.6">${escHtml(m.message)}</div>
          </div>
        </div>`;
        }).join('');

        if (isClosed) document.getElementById('reply-area').style.display = 'none';
        else document.getElementById('reply-area').style.display = '';
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

    async function setStatus(status) {
        const res = await fetch(`${BASE_URL}/api/support.php?id=${TICKET_ID}`, {
            method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status })
        });
        const json = await res.json();
        if (json.ok) loadTicket();
        else alert(json.error || 'Error');
    }

    function fmtDateTime(d) { return new Date(d).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); }
    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '&#10;'); }

    loadTicket();
</script>