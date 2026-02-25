<?php
/**
 * GymFlow — Support: Ticket thread (admin / instructor / staff) — Real-Time
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

// Verify ticket belongs to this gym
$t = db()->prepare("SELECT t.*, u.name AS creator_name FROM support_tickets t JOIN users u ON u.id = t.created_by WHERE t.id = ?");
$t->execute([$ticketId]);
$ticket = $t->fetch();
if (!$ticket || ((int) $ticket['gym_id'] !== (int) $user['gym_id'] && $user['role'] !== 'superadmin')) {
    header('Location: ' . BASE_URL . '/pages/admin/support.php');
    exit;
}

// Load existing messages
$msgs = db()->prepare("SELECT m.*, u.name AS author_name, u.role AS author_role FROM support_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? AND m.is_internal = 0 ORDER BY m.created_at ASC");
$msgs->execute([$ticketId]);
$messages = $msgs->fetchAll();

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

$STATUS_LABEL = ['open' => 'Abierto', 'in_progress' => 'En curso', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
$STATUS_COLOR = ['open' => '#f59e0b', 'in_progress' => '#6366f1', 'resolved' => '#10b981', 'closed' => '#6b7280'];
$PRI_LABEL = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta'];
?>

<div class="page-header">
    <a href="<?php echo BASE_URL ?>/pages/admin/support.php"
        style="color:var(--gf-text-muted);text-decoration:none;font-size:20px" title="Volver">←</a>
    <div style="margin-left:12px;flex:1;min-width:0">
        <h1 style="font-size:17px;font-weight:700;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            #<?php echo $ticket['id'] ?> — <?php echo htmlspecialchars($ticket['subject']) ?>
        </h1>
        <div style="font-size:12px;margin-top:2px">
            <span id="status-badge" style="font-weight:700;color:<?php echo $STATUS_COLOR[$ticket['status']] ?>">
                <?php echo $STATUS_LABEL[$ticket['status']] ?>
            </span>
            &nbsp;·&nbsp; Prioridad: <?php echo $PRI_LABEL[$ticket['priority']] ?>
        </div>
    </div>
    <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
        <button class="btn btn-secondary btn-sm" id="close-btn" onclick="closeTicket()">✖ Cerrar caso</button>
    <?php endif ?>
</div>

<!-- Real-time connection indicator -->
<div id="rt-indicator"
    style="text-align:center;font-size:11px;color:var(--gf-text-muted);padding:4px 0;margin-bottom:4px">
    🔄 Conectando...
</div>

<div class="page-body" style="max-width:760px;display:flex;flex-direction:column;height:calc(100vh - 140px)">

    <!-- Thread -->
    <div id="thread" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:12px;padding-bottom:16px">
        <?php foreach ($messages as $m):
            $isSA = $m['author_role'] === 'superadmin';
            ?>
            <div style="display:flex;flex-direction:column;align-items:<?php echo $isSA ? 'flex-start' : 'flex-end' ?>">
                <div style="max-width:85%;background:<?php echo $isSA ? 'var(--gf-surface)' : 'rgba(0,245,212,.08)' ?>;
            border:1px solid <?php echo $isSA ? 'var(--gf-border)' : 'rgba(0,245,212,.2)' ?>;
            border-radius:<?php echo $isSA ? '4px 14px 14px 14px' : '14px 4px 14px 14px' ?>;padding:12px 16px">
                    <div style="font-size:11px;color:var(--gf-text-muted);margin-bottom:6px">
                        <strong
                            style="color:<?php echo $isSA ? 'var(--gf-accent-2)' : 'var(--gf-accent)' ?>"><?php echo htmlspecialchars($m['author_name']) ?></strong>
                        <?php if ($isSA)
                            echo '· GymFlow'; ?>
                        &nbsp;·&nbsp; <?php echo date('d/m/y H:i', strtotime($m['created_at'])) ?>
                    </div>
                    <div style="font-size:14px;white-space:pre-wrap;line-height:1.6">
                        <?php echo htmlspecialchars($m['message']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <!-- Typing indicator -->
    <div id="typing-indicator"
        style="font-size:12px;color:var(--gf-text-muted);min-height:20px;margin-bottom:6px;padding-left:4px"></div>

    <!-- Resolution banner (shown on load if already resolved/closed) -->
    <?php if ($ticket['status'] === 'resolved' || $ticket['status'] === 'closed'): ?>
    <div id="resolved-banner" style="text-align:center;padding:12px 16px;margin-bottom:12px;
        background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);
        border-radius:10px;font-size:13px;color:#10b981;font-weight:600">
        <?php echo $ticket['status'] === 'resolved'
            ? '✅ Tu caso fue marcado como <strong>Resuelto</strong> por el equipo de GymFlow. Si necesitás más ayuda, abrí un nuevo caso.'
            : '🔒 Este caso fue <strong>Cerrado</strong>.' ?>
    </div>
    <?php endif ?>

    <!-- Reply form -->
    <div id="reply-area" class="card"
        style="padding:16px;flex-shrink:0;<?php echo in_array($ticket['status'], ['closed','resolved']) ? 'display:none' : '' ?>">
        <form id="reply-form" onsubmit="sendMessage(event)">
            <textarea id="msg-input" name="message" class="input" rows="3"
                placeholder="Escribí tu mensaje... (Enter para enviar, Shift+Enter para nueva línea)"
                style="margin-bottom:10px;resize:none" required></textarea>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary" id="send-btn">Enviar</button>
                <span style="font-size:12px;color:var(--gf-text-muted);align-self:center">
                    <span id="conn-dot"
                        style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#6b7280;margin-right:4px;vertical-align:middle"></span>
                    <span id="conn-label">desconectado</span>
                </span>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script>
    const SOCKET_URL = '<?php echo SOCKET_URL ?>';
    const TICKET_ID = <?php echo $ticketId ?>;
    const USER_ID = <?php echo (int) $user['id'] ?>;
    const USER_NAME = <?php echo json_encode($user['name']) ?>;
    const USER_ROLE = <?php echo json_encode($user['role']) ?>;

    const STATUS_LABEL = { open: 'Abierto', in_progress: 'En curso', resolved: 'Resuelto', closed: 'Cerrado' };
    const STATUS_COLOR = { open: '#f59e0b', in_progress: '#6366f1', resolved: '#10b981', closed: '#6b7280' };

    // ── Socket connection ──────────────────────────────────────────────────────
    const socket = io(SOCKET_URL, { transports: ['websocket', 'polling'] });

    socket.on('connect', () => {
        setConnState(true);
        socket.emit('support:join', { ticket_id: TICKET_ID, role: USER_ROLE });
    });
    socket.on('disconnect', () => setConnState(false));
    socket.on('connect_error', () => setConnState(false));

    function setConnState(online) {
        document.getElementById('conn-dot').style.background = online ? '#10b981' : '#6b7280';
        document.getElementById('conn-label').textContent = online ? 'en línea' : 'desconectado';
        document.getElementById('rt-indicator').textContent = online ? '🟢 Tiempo real activo' : '🔴 Sin conexión en tiempo real';
        document.getElementById('rt-indicator').style.color = online ? '#10b981' : '#ef4444';
    }

    // ── Incoming message ───────────────────────────────────────────────────────
    socket.on('support:new_message', (m) => {
        appendBubble(m);
        clearTyping();
    });

    // ── Typing indicator ───────────────────────────────────────────────────────
    let typingTimer;
    socket.on('support:typing', ({ name }) => {
        document.getElementById('typing-indicator').textContent = `✍️ ${name} está escribiendo...`;
        clearTimeout(typingTimer);
        typingTimer = setTimeout(clearTyping, 3000);
    });
    function clearTyping() { document.getElementById('typing-indicator').textContent = ''; }

    // ── Status change ──────────────────────────────────────────────────────────
    socket.on('support:status_changed', ({ status }) => {
        const badge = document.getElementById('status-badge');
        badge.textContent = STATUS_LABEL[status] || status;
        badge.style.color = STATUS_COLOR[status] || '';

        const inactive = status === 'closed' || status === 'resolved';
        if (inactive) {
            document.getElementById('reply-area').style.display = 'none';
            document.getElementById('close-btn')?.remove();

            // Show resolution banner above the thread
            let banner = document.getElementById('resolved-banner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'resolved-banner';
                banner.style.cssText = `text-align:center;padding:12px 16px;margin-bottom:12px;
                    background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);
                    border-radius:10px;font-size:13px;color:#10b981;font-weight:600`;
                document.getElementById('thread').parentNode.insertBefore(
                    banner, document.getElementById('thread')
                );
            }
            banner.innerHTML = status === 'resolved'
                ? '✅ Tu caso fue marcado como <strong>Resuelto</strong> por el equipo de GymFlow. Si necesitás más ayuda, abrí un nuevo caso.'
                : '🔒 Este caso fue <strong>Cerrado</strong>.';
        }
    });


    // ── Send message ───────────────────────────────────────────────────────────
    let emitting = false;
    function sendMessage(e) {
        e.preventDefault();
        const msg = document.getElementById('msg-input').value.trim();
        if (!msg || emitting) return;
        emitting = true;
        document.getElementById('send-btn').disabled = true;

        socket.emit('support:message', {
            ticket_id: TICKET_ID,
            user_id: USER_ID,
            role: USER_ROLE,
            name: USER_NAME,
            message: msg,
        });
        document.getElementById('msg-input').value = '';
        emitting = false;
        document.getElementById('send-btn').disabled = false;
    }

    // Enter to send, Shift+Enter for newline
    document.getElementById('msg-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(e); }
    });

    // Typing event (debounced 1.5s)
    let typDebounce;
    document.getElementById('msg-input')?.addEventListener('input', () => {
        clearTimeout(typDebounce);
        typDebounce = setTimeout(() => {
            socket.emit('support:typing', { ticket_id: TICKET_ID, name: USER_NAME });
        }, 800);
    });

    // ── Close ticket ───────────────────────────────────────────────────────────
    function closeTicket() {
        if (!confirm('¿Cerrar este caso?')) return;
        socket.emit('support:status_change', { ticket_id: TICKET_ID, status: 'closed', user_id: USER_ID, role: USER_ROLE });
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    function appendBubble(m) {
        const isSA = m.author_role === 'superadmin';
        const align = isSA ? 'flex-start' : 'flex-end';
        const bg = isSA ? 'var(--gf-surface)' : 'rgba(0,245,212,.08)';
        const bdr = isSA ? 'var(--gf-border)' : 'rgba(0,245,212,.2)';
        const br = isSA ? '4px 14px 14px 14px' : '14px 4px 14px 14px';
        const nameColor = isSA ? 'var(--gf-accent-2)' : 'var(--gf-accent)';
        const ts = new Date(m.created_at).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

        const wrap = document.createElement('div');
        wrap.style.cssText = `display:flex;flex-direction:column;align-items:${align}`;
        wrap.innerHTML = `
      <div style="max-width:85%;background:${bg};border:1px solid ${bdr};border-radius:${br};padding:12px 16px">
        <div style="font-size:11px;color:var(--gf-text-muted);margin-bottom:6px">
          <strong style="color:${nameColor}">${escHtml(m.author_name)}</strong>
          ${isSA ? '· GymFlow' : ''} &nbsp;·&nbsp; ${ts}
        </div>
        <div style="font-size:14px;white-space:pre-wrap;line-height:1.6">${escHtml(m.message)}</div>
      </div>`;
        document.getElementById('thread').appendChild(wrap);
        wrap.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }

    function escHtml(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    // Scroll to bottom on load
    window.addEventListener('load', () => {
        const t = document.getElementById('thread');
        t.scrollTop = t.scrollHeight;
    });
</script>