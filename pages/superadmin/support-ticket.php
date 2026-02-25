<?php
/**
 * GymFlow — Support: Ticket thread (superadmin view) — Real-Time
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

$t = db()->prepare("SELECT t.*, u.name AS creator_name, g.name AS gym_name FROM support_tickets t JOIN users u ON u.id = t.created_by JOIN gyms g ON g.id = t.gym_id WHERE t.id = ?");
$t->execute([$ticketId]);
$ticket = $t->fetch();
if (!$ticket) {
    header('Location: ' . BASE_URL . '/pages/superadmin/support.php');
    exit;
}

$msgs = db()->prepare("SELECT m.*, u.name AS author_name, u.role AS author_role FROM support_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.created_at ASC");
$msgs->execute([$ticketId]);
$messages = $msgs->fetchAll();

layout_header('Caso #' . $ticketId . ' — Soporte SA', 'superadmin', $user);
nav_section('Superadmin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'support');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>', 'gyms', 'support');
nav_item(BASE_URL . '/pages/superadmin/support.php', 'Soporte', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>', 'support', 'support');
layout_footer($user);

$STATUS_LABEL = ['open' => 'Abierto', 'in_progress' => 'En curso', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'];
$STATUS_COLOR = ['open' => '#f59e0b', 'in_progress' => '#6366f1', 'resolved' => '#10b981', 'closed' => '#6b7280'];
$PRI_LABEL = ['low' => 'Baja', 'normal' => 'Normal', 'high' => 'Alta'];
$isClosed = $ticket['status'] === 'closed';
?>

<div class="page-header">
    <a href="<?php echo BASE_URL ?>/pages/superadmin/support.php"
        style="color:var(--gf-text-muted);text-decoration:none;font-size:20px" title="Volver">←</a>
    <div style="margin-left:12px;flex:1;min-width:0">
        <h1 style="font-size:17px;font-weight:700;margin:0">
            #<?php echo $ticket['id'] ?> — <?php echo htmlspecialchars($ticket['subject']) ?>
        </h1>
        <div style="font-size:12px;margin-top:2px">
            <span id="status-badge" style="font-weight:700;color:<?php echo $STATUS_COLOR[$ticket['status']] ?>">
                <?php echo $STATUS_LABEL[$ticket['status']] ?>
            </span>
            &nbsp;·&nbsp; <strong><?php echo htmlspecialchars($ticket['gym_name']) ?></strong>
            &nbsp;·&nbsp; <?php echo htmlspecialchars($ticket['creator_name']) ?>
            &nbsp;·&nbsp; Prioridad <?php echo $PRI_LABEL[$ticket['priority']] ?>
        </div>
    </div>
    <!-- Status action buttons -->
    <div id="status-actions" class="flex gap-2">
        <?php foreach ([
            ['open', 'Reabrir', $isClosed],
            ['in_progress', 'En curso', !$isClosed && $ticket['status'] !== 'in_progress'],
            ['resolved', '✔ Resuelto', !$isClosed && $ticket['status'] === 'in_progress'],
            ['closed', '✖ Cerrar', !$isClosed],
        ] as [$st, $lbl, $show]):
            if (!$show)
                continue;
            ?>
            <button class="btn btn-secondary btn-sm" onclick="setStatus('<?php echo $st ?>')"><?php echo $lbl ?></button>
        <?php endforeach ?>
    </div>
</div>

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
            <div style="display:flex;flex-direction:column;align-items:<?php echo $isSA ? 'flex-end' : 'flex-start' ?>">
                <div style="max-width:85%;background:<?php echo $isSA ? 'rgba(0,245,212,.08)' : 'var(--gf-surface)' ?>;
            border:1px solid <?php echo $isSA ? 'rgba(0,245,212,.2)' : 'var(--gf-border)' ?>;
            border-radius:<?php echo $isSA ? '14px 4px 14px 14px' : '4px 14px 14px 14px' ?>;padding:12px 16px">
                    <div style="font-size:11px;color:var(--gf-text-muted);margin-bottom:6px">
                        <strong
                            style="color:<?php echo $isSA ? 'var(--gf-accent)' : 'var(--gf-accent-2)' ?>"><?php echo htmlspecialchars($m['author_name']) ?></strong>
                        <?php if ($isSA)
                            echo '· GymFlow'; ?>
                        &nbsp;·&nbsp; <?php echo date('d/m/y H:i', strtotime($m['created_at'])) ?>
                    </div>
                    <div style="font-size:14px;white-space:pre-wrap;line-height:1.6">
                        <?php echo htmlspecialchars($m['message']) ?></div>
                </div>
            </div>
        <?php endforeach ?>
    </div>

    <div id="typing-indicator"
        style="font-size:12px;color:var(--gf-text-muted);min-height:20px;margin-bottom:6px;padding-left:4px"></div>

    <div id="reply-area" class="card" style="padding:16px;flex-shrink:0;<?php echo $isClosed ? 'display:none' : '' ?>">
        <form id="reply-form" onsubmit="sendMessage(event)">
            <textarea id="msg-input" name="message" class="input" rows="3"
                placeholder="Respuesta al cliente... (Enter para enviar, Shift+Enter para nueva línea)"
                style="margin-bottom:10px;resize:none" required></textarea>
            <div class="flex gap-2" style="align-items:center">
                <button type="submit" class="btn btn-primary" id="send-btn">Enviar respuesta</button>
                <span style="font-size:12px;color:var(--gf-text-muted)">
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
    const USER_ROLE = 'superadmin';

    const STATUS_LABEL = { open: 'Abierto', in_progress: 'En curso', resolved: 'Resuelto', closed: 'Cerrado' };
    const STATUS_COLOR = { open: '#f59e0b', in_progress: '#6366f1', resolved: '#10b981', closed: '#6b7280' };

    // ── Socket ─────────────────────────────────────────────────────────────────
    const socket = io(SOCKET_URL, { transports: ['websocket', 'polling'] });

    socket.on('connect', () => {
        setConnState(true);
        socket.emit('support:join', { ticket_id: TICKET_ID, role: 'superadmin' });
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
    socket.on('support:new_message', (m) => { appendBubble(m); clearTyping(); });

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
        renderStatusActions(status);
        if (status === 'closed') document.getElementById('reply-area').style.display = 'none';
        else document.getElementById('reply-area').style.display = '';
    });

    // ── Send message ───────────────────────────────────────────────────────────
    function sendMessage(e) {
        e.preventDefault();
        const msg = document.getElementById('msg-input').value.trim();
        if (!msg) return;
        document.getElementById('send-btn').disabled = true;
        socket.emit('support:message', { ticket_id: TICKET_ID, user_id: USER_ID, role: 'superadmin', name: USER_NAME, message: msg });
        document.getElementById('msg-input').value = '';
        document.getElementById('send-btn').disabled = false;
    }

    document.getElementById('msg-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(e); }
    });

    let typDebounce;
    document.getElementById('msg-input')?.addEventListener('input', () => {
        clearTimeout(typDebounce);
        typDebounce = setTimeout(() => {
            socket.emit('support:typing', { ticket_id: TICKET_ID, name: USER_NAME });
        }, 800);
    });

    // ── Status actions ─────────────────────────────────────────────────────────
    function setStatus(status) {
        socket.emit('support:status_change', { ticket_id: TICKET_ID, status, user_id: USER_ID, role: 'superadmin' });
    }

    function renderStatusActions(status) {
        const closed = status === 'closed';
        const defs = [
            { st: 'open', lbl: 'Reabrir', show: closed },
            { st: 'in_progress', lbl: 'En curso', show: !closed && status !== 'in_progress' },
            { st: 'resolved', lbl: '✔ Resuelto', show: !closed && status === 'in_progress' },
            { st: 'closed', lbl: '✖ Cerrar', show: !closed },
        ];
        document.getElementById('status-actions').innerHTML = defs
            .filter(d => d.show)
            .map(d => `<button class="btn btn-secondary btn-sm" onclick="setStatus('${d.st}')">${d.lbl}</button>`)
            .join('');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    function appendBubble(m) {
        const isSA = m.author_role === 'superadmin';
        const align = isSA ? 'flex-end' : 'flex-start';
        const bg = isSA ? 'rgba(0,245,212,.08)' : 'var(--gf-surface)';
        const bdr = isSA ? 'rgba(0,245,212,.2)' : 'var(--gf-border)';
        const br = isSA ? '14px 4px 14px 14px' : '4px 14px 14px 14px';
        const nameColor = isSA ? 'var(--gf-accent)' : 'var(--gf-accent-2)';
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

    window.addEventListener('load', () => {
        const t = document.getElementById('thread');
        t.scrollTop = t.scrollHeight;
    });
</script>