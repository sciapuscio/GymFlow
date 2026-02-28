<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Identificar el gym por slug
$slug = trim($_GET['gym'] ?? '');
if (!$slug) {
    http_response_code(400);
    exit('<p style="color:#fff;padding:40px;font-family:sans-serif">Error: parámetro ?gym=slug requerido</p>');
}

$stmtGym = db()->prepare(
    "SELECT id, name, slug, primary_color, secondary_color, logo_path FROM gyms WHERE slug = ? AND active = 1"
);
$stmtGym->execute([$slug]);
$gym = $stmtGym->fetch();
if (!$gym) {
    http_response_code(404);
    exit('<p style="color:#fff;padding:40px;font-family:sans-serif">Gimnasio no encontrado</p>');
}

// Filtro opcional por sala o sede
$salaId = isset($_GET['sala']) ? (int) $_GET['sala'] : 0;
$sedeId = isset($_GET['sede']) ? (int) $_GET['sede'] : 0;
$salaName = '';
$sedeName = '';

// Cargar slots iniciales
$sql = "SELECT ss.*, ss.label AS class_name,
            s.name AS sala_name, s.accent_color AS sala_accent, s.bg_color AS sala_bg,
            se.name AS sede_name,
            COALESCE(ss.sede_id, s.sede_id) AS effective_sede_id
     FROM   schedule_slots ss
     LEFT JOIN salas s ON ss.sala_id = s.id
     LEFT JOIN sedes se ON se.id = COALESCE(ss.sede_id, s.sede_id)
     WHERE  ss.gym_id = ?";
$params = [$gym['id']];
if ($sedeId) {
    // Sede específica: solo slots de esa sede (directo o herencia de sala)
    $sql .= " AND COALESCE(ss.sede_id, s.sede_id) = ?";
    $params[] = $sedeId;
    $stmtSedeName = db()->prepare("SELECT name FROM sedes WHERE id = ? AND gym_id = ?");
    $stmtSedeName->execute([$sedeId, $gym['id']]);
    $sedeName = $stmtSedeName->fetchColumn() ?: '';
} elseif ($salaId) {
    $sql .= " AND ss.sala_id = ?";
    $params[] = $salaId;
    $stmtSalaName = db()->prepare("SELECT name FROM salas WHERE id = ? AND gym_id = ?");
    $stmtSalaName->execute([$salaId, $gym['id']]);
    $salaName = $stmtSalaName->fetchColumn() ?: '';
} else {
    // Sin filtro = Gimnasio central: solo slots SIN sede específica
    $sql .= " AND COALESCE(ss.sede_id, s.sede_id) IS NULL";
}
$sql .= " ORDER BY ss.day_of_week, ss.start_time";
$stmtSlots = db()->prepare($sql);
$stmtSlots->execute($params);
$slots = $stmtSlots->fetchAll();

// Pre-agrupar por día
$byDay = array_fill(0, 7, []);
foreach ($slots as $s) {
    $byDay[(int) $s['day_of_week']][] = $s;
}

$accent = htmlspecialchars($gym['primary_color'] ?? '#00f5d4');
$accent2 = htmlspecialchars($gym['secondary_color'] ?? '#ff6b35');
$gymName = htmlspecialchars($gym['name']);
$gymId = (int) $gym['id'];
$filterLabel = $sedeName ? htmlspecialchars($sedeName) : ($salaName ? 'Sala: ' . htmlspecialchars($salaName) : '');

$pageTitle = $filterLabel ?: 'Programación Semanal';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cartelera —
        <?= $gymName ?>
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --accent:
                <?= $accent ?>
            ;
            --accent2:
                <?= $accent2 ?>
            ;
            --bg: #07070f;
            --card: rgba(255, 255, 255, .04);
            --border: rgba(255, 255, 255, .07);
            --text: #e8e8f0;
            --muted: rgba(232, 232, 240, .45);
            --today-border: var(--accent);
        }

        html,
        body {
            height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            overflow: hidden;
        }

        /* ── HEADER ────────────────────────────────────────────────────────────── */
        #header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 28px;
            background: rgba(255, 255, 255, .025);
            border-bottom: 1px solid var(--border);
            gap: 24px;
        }

        #header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        #gym-logo {
            height: 42px;
            width: 42px;
            border-radius: 10px;
            object-fit: contain;
            background: rgba(255, 255, 255, .06);
            padding: 4px;
        }

        #gym-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(22px, 2.4vw, 34px);
            letter-spacing: .06em;
            color: var(--accent);
            line-height: 1;
        }

        #agenda-label {
            font-size: clamp(11px, 1vw, 14px);
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .12em;
            margin-top: 2px;
        }

        #live-badge {
            display: flex;
            align-items: center;
            gap: 7px;
            background: rgba(229, 255, 61, .08);
            border: 1px solid rgba(229, 255, 61, .2);
            border-radius: 999px;
            padding: 5px 14px 5px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #e5ff3d;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        #live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e5ff3d;
            animation: blink 1.8s ease-in-out infinite;
        }

        #clock-display {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(26px, 3vw, 44px);
            letter-spacing: .05em;
            color: var(--text);
            min-width: 120px;
            text-align: right;
        }

        #date-display {
            font-size: clamp(10px, .9vw, 13px);
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .1em;
            text-align: right;
            margin-top: 2px;
        }

        /* ── GRID ──────────────────────────────────────────────────────────────── */
        #grid-wrap {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 82px);
            padding: 16px 20px 14px;
            gap: 12px;
        }

        #day-headers {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .day-header {
            text-align: center;
            font-size: clamp(10px, 1.1vw, 14px);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--muted);
            padding: 6px 0;
            border-radius: 8px;
            transition: color .3s;
        }

        .day-header.today {
            color: var(--accent);
            background: rgba(0, 245, 212, .06);
        }

        #day-columns {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            flex: 1;
            overflow: hidden;
        }

        .day-col {
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 2px;
            border-radius: 10px;
            padding: 8px 6px;
            background: var(--card);
            border: 1px solid var(--border);
            transition: border-color .4s, background .4s;
        }

        .day-col.today {
            border-color: rgba(0, 245, 212, .25);
            background: rgba(0, 245, 212, .03);
        }

        .day-col::-webkit-scrollbar {
            width: 3px;
        }

        .day-col::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .1);
            border-radius: 99px;
        }

        /* ── SLOT CARD ──────────────────────────────────────────────────────────── */
        .slot-card {
            border-radius: 8px;
            padding: 8px 10px;
            background: rgba(255, 255, 255, .05);
            border-left: 3px solid var(--accent);
            position: relative;
            overflow: hidden;
            transition: transform .3s ease, box-shadow .3s ease;
            animation: slideIn .35s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slot-card.live-now {
            border-left-color: #ff6b35;
            background: rgba(255, 107, 53, .08);
            box-shadow: 0 0 0 1px rgba(255, 107, 53, .25), 0 4px 20px rgba(255, 107, 53, .12);
            animation: slideIn .35s ease, pulseGlow 3s ease-in-out infinite;
        }

        @keyframes pulseGlow {

            0%,
            100% {
                box-shadow: 0 0 0 1px rgba(255, 107, 53, .25), 0 4px 20px rgba(255, 107, 53, .12);
            }

            50% {
                box-shadow: 0 0 0 1px rgba(255, 107, 53, .5), 0 4px 28px rgba(255, 107, 53, .22);
            }
        }

        .slot-time {
            font-size: clamp(9px, .85vw, 12px);
            font-weight: 700;
            letter-spacing: .06em;
            color: var(--accent);
            margin-bottom: 2px;
        }

        .slot-card.live-now .slot-time {
            color: #ff6b35;
        }

        .slot-name {
            font-size: clamp(10px, 1vw, 14px);
            font-weight: 700;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .slot-sala {
            font-size: clamp(8px, .75vw, 11px);
            color: var(--muted);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .live-badge-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 107, 53, .18);
            border: 1px solid rgba(255, 107, 53, .35);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: 800;
            color: #ff6b35;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .live-badge-chip::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: #ff6b35;
            animation: blink 1.2s ease-in-out infinite;
        }

        .empty-col {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: .06em;
        }

        /* ── FOOTER ────────────────────────────────────────────────────────────── */
        #footer {
            text-align: center;
            padding: 6px;
            font-size: 10px;
            color: var(--muted);
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        /* ── CONNECTION STATUS ──────────────────────────────────────────────────── */
        #conn-status {
            position: fixed;
            bottom: 14px;
            left: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--muted);
            opacity: 0;
            transition: opacity .5s;
        }

        #conn-status.show {
            opacity: 1;
        }

        #conn-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #33cc77;
            transition: background .4s;
        }

        #conn-dot.offline {
            background: #ff4444;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .3;
            }
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <div id="header">
        <div id="header-left">
            <?php if ($gym['logo_path']): ?>
                <img id="gym-logo" src="<?= htmlspecialchars($gym['logo_path']) ?>" alt="<?= $gymName ?>">
            <?php endif; ?>
            <div>
                <div id="gym-name">
                    <?= $gymName ?>
                </div>
                <div id="agenda-label"><?= htmlspecialchars($pageTitle) ?></div>
            </div>
        </div>

        <div id="live-badge">
            <div id="live-dot"></div>
            En vivo
        </div>

        <div>
            <div id="clock-display">--:--</div>
            <div id="date-display">—</div>
        </div>
    </div>

    <!-- GRID -->
    <div id="grid-wrap">
        <div id="day-headers">
            <?php
            $dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
            // PHP date('N') → 1=Lun..7=Dom → we map to 0=Lun..6=Dom
            $phpDay = (int) date('N') - 1;  // 0-indexed Mon-based
            foreach ($dayNames as $i => $d):
                $cls = ($i === $phpDay) ? 'day-header today' : 'day-header';
                ?>
                <div class="<?= $cls ?>">
                    <?= $d ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="day-columns">
            <?php foreach ($byDay as $dayIdx => $daySlots):
                $isToday = ($dayIdx === $phpDay);
                $colCls = $isToday ? 'day-col today' : 'day-col';
                ?>
                <div class="<?= $colCls ?>" id="col-<?= $dayIdx ?>">
                    <?php if (empty($daySlots)): ?>
                        <div class="empty-col">Sin clases</div>
                    <?php else:
                        foreach ($daySlots as $slot):
                            $timeStr = substr($slot['start_time'], 0, 5) . ' – ' . substr($slot['end_time'], 0, 5);
                            $name = htmlspecialchars($slot['class_name'] ?? 'Clase');
                            $salaStr = htmlspecialchars($slot['sala_name'] ?? '');
                            // Is it happening right now? (only matters for today)
                            $isLive = false;
                            if ($isToday) {
                                $nowSec = (int) date('H') * 3600 + (int) date('i') * 60 + (int) date('s');
                                $startSec = strtotime($slot['start_time']) - strtotime('today');
                                $endSec = strtotime($slot['end_time']) - strtotime('today');
                                $isLive = ($nowSec >= $startSec && $nowSec < $endSec);
                            }
                            $cardCls = $isLive ? 'slot-card live-now' : 'slot-card';
                            ?>
                            <div class="<?= $cardCls ?>" data-start="<?= htmlspecialchars($slot['start_time']) ?>"
                                data-end="<?= htmlspecialchars($slot['end_time']) ?>" data-day="<?= $dayIdx ?>">
                                <div class="slot-time">
                                    <?= $timeStr ?>
                                </div>
                                <div class="slot-name">
                                    <?= $name ?>
                                </div>
                                <?php if ($salaStr): ?>
                                    <div class="slot-sala">
                                        <?= $salaStr ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$sedeId && !empty($slot['sede_name'])): ?>
                                    <div class="slot-sede"
                                        style="font-size:clamp(7px,.65vw,9px);font-weight:800;color:var(--accent);text-transform:uppercase;letter-spacing:.06em;margin-top:1px">
                                        <?= htmlspecialchars($slot['sede_name']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($isLive): ?>
                                    <div class="live-badge-chip">En curso</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="conn-status">
        <div id="conn-dot"></div>
        <span id="conn-text">Conectado</span>
    </div>

    <!-- Socket.IO -->
    <script src="<?= SOCKET_URL ?>/socket.io/socket.io.js"></script>
    <script>
        const GYM_ID = <?= $gymId ?>;
        const SALA_ID = <?= $salaId ?>; // 0 = todas las salas
        const SEDE_ID = <?= $sedeId ?>; // 0 = todas las sedes
        const TODAY = <?= $phpDay ?>; // 0=Lun..6=Dom

        // ── Clock ──────────────────────────────────────────────────────────────────
        (function clockTick() {
            const now = new Date();
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('clock-display').textContent = `${hh}:${mm}:${ss}`;

            const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            document.getElementById('date-display').textContent =
                `${days[now.getDay()]} ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;

            setTimeout(clockTick, 1000);
        })();

        // ── Live-now updater (checks every 30s to refresh EN CURSO badges) ────────
        function updateLiveBadges() {
            const now = new Date();
            const nowSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
            document.querySelectorAll('.slot-card').forEach(card => {
                const day = parseInt(card.dataset.day);
                if (day !== TODAY) return;
                const [sh, sm, ss2] = card.dataset.start.split(':').map(Number);
                const [eh, em, es] = card.dataset.end.split(':').map(Number);
                const startSec = sh * 3600 + sm * 60 + (ss2 || 0);
                const endSec = eh * 3600 + em * 60 + (es || 0);
                const live = (nowSec >= startSec && nowSec < endSec);
                card.classList.toggle('live-now', live);
                const chip = card.querySelector('.live-badge-chip');
                if (live && !chip) {
                    const el = document.createElement('div');
                    el.className = 'live-badge-chip';
                    el.textContent = 'En curso';
                    card.appendChild(el);
                } else if (!live && chip) {
                    chip.remove();
                }
            });
        }
        setInterval(updateLiveBadges, 30000);

        // ── Render slots ──────────────────────────────────────────────────────────
        function fmt(t) { return t ? t.slice(0, 5) : ''; }

        function renderSlots(slots) {
            // Apply sala filter if active
            if (SEDE_ID) slots = slots.filter(s => parseInt(s.sede_id) === SEDE_ID);
            else if (SALA_ID) slots = slots.filter(s => parseInt(s.sala_id) === SALA_ID);

            // Group by day
            const byDay = Array.from({ length: 7 }, () => []);
            slots.forEach(s => byDay[parseInt(s.day_of_week)].push(s));

            const now = new Date();
            const nowSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();

            for (let d = 0; d < 7; d++) {
                const col = document.getElementById('col-' + d);
                if (!col) continue;
                col.innerHTML = '';
                const daySlots = byDay[d];

                if (!daySlots.length) {
                    col.innerHTML = '<div class="empty-col">Sin clases</div>';
                    continue;
                }

                daySlots.forEach(s => {
                    const startStr = (s.start_time || '').slice(0, 8);
                    const endStr = (s.end_time || '').slice(0, 8);
                    const [sh, sm, ss2 = 0] = startStr.split(':').map(Number);
                    const [eh, em, es = 0] = endStr.split(':').map(Number);
                    const startSec = sh * 3600 + sm * 60 + ss2;
                    const endSec = eh * 3600 + em * 60 + es;
                    const isLive = (d === TODAY) && (nowSec >= startSec && nowSec < endSec);

                    const card = document.createElement('div');
                    card.className = isLive ? 'slot-card live-now' : 'slot-card';
                    card.dataset.start = startStr;
                    card.dataset.end = endStr;
                    card.dataset.day = d;

                    const name = s.class_name || s.label || 'Clase';
                    const sala = s.sala_name || '';

                    card.innerHTML = `
        <div class="slot-time">${fmt(s.start_time)} – ${fmt(s.end_time)}</div>
        <div class="slot-name">${name}</div>
        ${sala ? `<div class="slot-sala">${sala}</div>` : ''}
        ${!SEDE_ID && s.sede_name ? `<div class="slot-sede" style="font-size:clamp(7px,.65vw,9px);font-weight:800;color:var(--accent);text-transform:uppercase;letter-spacing:.06em;margin-top:1px">${s.sede_name}</div>` : ''}
        ${isLive ? '<div class="live-badge-chip">En curso</div>' : ''}
      `;
                    col.appendChild(card);
                });
            }
        }

        // ── Socket.IO ──────────────────────────────────────────────────────────────
        const connStatus = document.getElementById('conn-status');
        const connDot = document.getElementById('conn-dot');
        const connText = document.getElementById('conn-text');

        const socket = io('<?= SOCKET_URL ?>', { transports: ['websocket'] });

        socket.on('connect', () => {
            socket.emit('join:agenda', { gym_id: GYM_ID });
            connDot.classList.remove('offline');
            connText.textContent = 'Conectado';
            connStatus.classList.add('show');
            setTimeout(() => connStatus.classList.remove('show'), 4000);
            console.log('[Agenda] Conectado — gym', GYM_ID);
        });

        socket.on('disconnect', () => {
            connDot.classList.add('offline');
            connText.textContent = 'Reconectando...';
            connStatus.classList.add('show');
        });

        socket.on('schedule:updated', ({ slots }) => {
            console.log('[Agenda] schedule:updated —', slots.length, 'slots');
            renderSlots(slots);
        });
    </script>
</body>

</html>