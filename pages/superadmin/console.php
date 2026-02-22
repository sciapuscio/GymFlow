<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');

layout_header('Sistema · Consola', 'superadmin', $user);
nav_section('Super Admin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'console');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>', 'gyms', 'console');
nav_item(BASE_URL . '/pages/superadmin/users.php', 'Usuarios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'users', 'console');
nav_item(BASE_URL . '/pages/superadmin/console.php', 'Consola', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'console', 'console');
layout_footer($user);
?>

<style>
    /* ── Console overwrites ──────────────────────────────────────────────── */
    @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap');

    #gf-console {
        font-family: 'JetBrains Mono', 'Courier New', monospace;
        font-size: 13px;
        background: #050a05;
        color: #00ff41;
        border: 1px solid #0f3a0f;
        border-radius: 14px;
        padding: 16px;
        height: calc(100vh - 210px);
        min-height: 400px;
        overflow-y: auto;
        line-height: 1.65;
        scrollbar-width: thin;
        scrollbar-color: #0f3a0f #050a05;
    }

    #gf-console::-webkit-scrollbar {
        width: 6px;
    }

    #gf-console::-webkit-scrollbar-track {
        background: #050a05;
    }

    #gf-console::-webkit-scrollbar-thumb {
        background: #0f3a0f;
        border-radius: 3px;
    }

    .log-line {
        display: flex;
        gap: 8px;
        padding: 1px 0;
        animation: fadeIn .15s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(2px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }

    .log-ts {
        color: #2a5a2a;
        flex-shrink: 0;
        user-select: none;
    }

    .log-type {
        flex-shrink: 0;
        width: 52px;
        text-align: right;
        font-weight: 700;
    }

    .log-msg {
        color: #aaffaa;
        word-break: break-word;
    }

    /* type colors */
    .lt-connect {
        color: #00ff41;
    }

    .lt-disconnect {
        color: #ff4444;
    }

    .lt-instructor {
        color: #00d4ff;
    }

    .lt-display {
        color: #a78bfa;
    }

    .lt-system {
        color: #fbbf24;
    }

    .lt-play {
        color: #00ff41;
    }

    .lt-pause {
        color: #f59e0b;
    }

    .lt-stop {
        color: #ef4444;
    }

    .lt-skip {
        color: #38bdf8;
    }

    .lt-broadcast {
        color: #ff6b35;
    }

    .lt-error {
        color: #ff4444;
    }

    /* status bar */
    #mon-status {
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        color: #2a5a2a;
    }

    #mon-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #555;
        flex-shrink: 0;
        transition: background .3s;
    }

    #mon-dot.live {
        background: #00ff41;
        box-shadow: 0 0 8px #00ff41;
    }

    .console-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }

    .filter-btn {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        padding: 3px 10px;
        border-radius: 6px;
        border: 1px solid #0f3a0f;
        background: transparent;
        cursor: pointer;
        transition: all .15s;
    }

    .filter-btn.active {
        background: #0f3a0f;
        color: #00ff41;
    }

    .filter-btn.inactive {
        color: #2a5a2a;
    }

    .filter-btn:hover {
        border-color: #00ff41;
        color: #00ff41;
    }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <h1 style="font-size:20px;font-weight:700;font-family:'JetBrains Mono',monospace">
            <span style="color:var(--gf-accent)">$</span> consola
        </h1>
        <div id="mon-status">
            <div id="mon-dot"></div>
            <span id="mon-label">Conectando…</span>
        </div>
    </div>
    <div style="display:flex;gap:8px;margin-left:auto">
        <button class="btn btn-ghost btn-sm" onclick="clearConsole()">Limpiar</button>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--gf-text-muted)">
            <input type="checkbox" id="autoscroll" checked style="accent-color:var(--gf-accent)"> Auto-scroll
        </label>
    </div>
</div>

<div class="page-body">
    <div class="console-toolbar">
        <?php
        $filters = [
            'connect' => '🔌 Conexión',
            'disconnect' => '💔 Desconexión',
            'instructor' => '🎓 Instructor',
            'display' => '📺 Pantalla',
            'system' => '⚙️ Sistema',
            'play' => '▶ Play',
            'pause' => '⏸ Pause',
            'stop' => '⏹ Stop',
            'skip' => '⏭ Skip',
            'broadcast' => '📢 Broadcast',
            'error' => '🚨 Error',
        ];
        foreach ($filters as $k => $label):
            ?>
            <button class="filter-btn active" data-filter="<?php echo $k ?>" onclick="toggleFilter(this)">
                <?php echo $label ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div id="gf-console">
        <div class="log-line">
            <span class="log-ts">--:--:--</span>
            <span class="log-type lt-system">SYS</span>
            <span class="log-msg">GymFlow Activity Console. Conectando al sync-server…</span>
        </div>
    </div>
</div>

<script src="http://localhost:3001/socket.io/socket.io.js"></script>
<script>
    (function () {
        const console_ = document.getElementById('gf-console');
        const dot = document.getElementById('mon-dot');
        const label = document.getElementById('mon-label');
        let lineCount = 0;
        const MAX_LINES = 500;

        // Active filters
        const activeFilters = new Set(<?php echo json_encode(array_keys($filters)) ?>);

        window.toggleFilter = function (btn) {
            const f = btn.dataset.filter;
            if (activeFilters.has(f)) {
                activeFilters.delete(f);
                btn.classList.remove('active'); btn.classList.add('inactive');
            } else {
                activeFilters.add(f);
                btn.classList.add('active'); btn.classList.remove('inactive');
            }
            // Show/hide existing lines
            console_.querySelectorAll('.log-line[data-type]').forEach(el => {
                el.style.display = activeFilters.has(el.dataset.type) ? '' : 'none';
            });
        };

        window.clearConsole = function () {
            console_.innerHTML = '';
            lineCount = 0;
        };

        const TYPE_LABELS = {
            connect: 'CON', disconnect: 'DIS', instructor: 'INS', display: 'DSP',
            system: 'SYS', play: 'PLY', pause: 'PSE', stop: 'STO', skip: 'SKP',
            broadcast: 'BRD', error: 'ERR',
        };

        function pad(n) { return String(n).padStart(2, '0'); }

        function addLine(type, msg, ts) {
            if (!activeFilters.has(type)) return;

            const d = ts ? new Date(ts) : new Date();
            const time = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
            const typeLabel = TYPE_LABELS[type] || type.toUpperCase().slice(0, 3);

            const line = document.createElement('div');
            line.className = 'log-line';
            line.dataset.type = type;
            line.innerHTML =
                `<span class="log-ts">${time}</span>` +
                `<span class="log-type lt-${type}">${typeLabel}</span>` +
                `<span class="log-msg">${escHtml(msg)}</span>`;

            console_.appendChild(line);
            lineCount++;

            // Prune old lines to keep performance
            if (lineCount > MAX_LINES) {
                const first = console_.querySelector('.log-line');
                if (first) { console_.removeChild(first); lineCount--; }
            }

            if (document.getElementById('autoscroll').checked) {
                console_.scrollTop = console_.scrollHeight;
            }
        }

        function escHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        // ── Socket connection ───────────────────────────────────────────────────
        const sock = io('http://localhost:3001', { transports: ['websocket', 'polling'] });

        sock.on('connect', () => {
            dot.classList.add('live');
            label.textContent = `Conectado · ${sock.id}`;
            sock.emit('join:monitor');
        });

        sock.on('disconnect', () => {
            dot.classList.remove('live');
            label.textContent = 'Desconectado · reintentando…';
            addLine('disconnect', 'Monitor desconectado del sync-server.');
        });

        sock.on('monitor:log', ({ type, msg, ts }) => {
            addLine(type, msg, ts);
        });

        sock.on('connect_error', (err) => {
            label.textContent = `Error: ${err.message}`;
            addLine('error', `No se pudo conectar: ${err.message}`);
        });
    })();
</script>
<?php layout_end(); ?>