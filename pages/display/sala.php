<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

$code = $_GET['code'] ?? '';
if (!$code) {
    http_response_code(400);
    exit('<p style="color:#fff;padding:40px;font-family:sans-serif">Error: sala_code requerido</p>');
}

$stmt = db()->prepare(
    "SELECT s.*, g.primary_color, g.secondary_color, g.font_family, g.font_display,
      g.logo_path as gym_logo, g.name as gym_name
   FROM salas s JOIN gyms g ON s.gym_id = g.id WHERE s.display_code = ? AND s.active = 1"
);
$stmt->execute([$code]);
$sala = $stmt->fetch();
if (!$sala) {
    http_response_code(404);
    exit('<p style="color:#fff;padding:40px;font-family:sans-serif">Sala no encontrada</p>');
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>GymFlow — Display</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL ?>/assets/css/display.css">
</head>

<body class="state-idle">
    <?php // sala already loaded above ?>

    <style>
        :root {
            --d-accent:
                <?php echo htmlspecialchars($sala['primary_color'] ?? '#00f5d4') ?>
            ;
            --d-accent2:
                <?php echo htmlspecialchars($sala['secondary_color'] ?? '#ff6b35') ?>
            ;
        }

        .display-status-label {
            color: var(--d-accent);
            border-color: color-mix(in srgb, var(--d-accent) 40%, transparent);
            background: color-mix(in srgb, var(--d-accent) 10%, transparent);
        }

        .display-clock {
            color: var(--d-accent);
            text-shadow: 0 0 80px color-mix(in srgb, var(--d-accent) 40%, transparent);
        }

        .block-progress-fill,
        .display-total-progress-fill,
        .block-dot.current {
            background: var(--d-accent);
            box-shadow: 0 0 12px var(--d-accent);
        }

        .block-dot.current {
            box-shadow: 0 0 8px var(--d-accent);
        }

        .block-dot.done {
            background: var(--d-accent);
        }
    </style>

    <!-- Ambient decorations -->
    <div class="ambient-rings" id="ambient-rings">
        <div class="ring"></div>
        <div class="ring"></div>
        <div class="ring"></div>
    </div>

    <!-- IDLE SCREEN -->
    <div class="idle-screen" id="idle-screen">
        <?php if ($sala['gym_logo']): ?>
            <img src="<?php echo BASE_URL . htmlspecialchars($sala['gym_logo']) ?>"
                alt="<?php echo htmlspecialchars($sala['gym_name']) ?>" class="idle-gym-logo"
                onerror="this.style.display='none';document.getElementById('idle-name-fallback').style.display='block'">
            <div id="idle-name-fallback" style="display:none" class="idle-gym-logo-placeholder">
                <?php echo htmlspecialchars(strtoupper(substr($sala['gym_name'], 0, 2))) ?>
            </div>
        <?php else: ?>
            <div class="idle-gym-logo-placeholder">
                <?php echo htmlspecialchars(strtoupper(substr($sala['gym_name'], 0, 2))) ?>
            </div>
        <?php endif; ?>

        <div>
            <div id="idle-class-label" class="idle-class-label"></div>
            <div class="idle-sala-name">
                <?php echo htmlspecialchars($sala['name']) ?>
            </div>
        </div>

        <div class="idle-ready-text">Preparándose</div>
    </div>

    <!-- PREPARATE OVERLAY (shown during spotify intro countdown) -->
    <div id="prep-overlay"
        style="display:none;position:fixed;inset:0;background:#0a0a0f;z-index:200;flex-direction:column;align-items:center;justify-content:center;gap:24px">
        <div
            style="font-family:'Bebas Neue',sans-serif;font-size:clamp(40px,6vw,80px);letter-spacing:.15em;color:rgba(255,107,53,0.7);text-transform:uppercase">
            ¡PREPARATE!
        </div>
        <div id="prep-countdown"
            style="font-family:'Bebas Neue',sans-serif;font-size:clamp(140px,25vw,280px);line-height:1;color:#ff6b35;text-shadow:0 0 120px rgba(255,107,53,0.6);animation:prepPulse 1s ease-in-out infinite">
            10
        </div>
        <div id="prep-block-name"
            style="font-size:clamp(16px,2.5vw,28px);font-weight:700;color:rgba(255,255,255,0.5);letter-spacing:.08em;text-transform:uppercase">
        </div>
        <style>
            @keyframes prepPulse {

                0%,
                100% {
                    transform: scale(1);
                    opacity: 1;
                }

                50% {
                    transform: scale(1.04);
                    opacity: .85;
                }
            }
        </style>
    </div>

    <!-- DESCANSO OVERLAY (shown during tabata/interval REST phase) -->
    <div id="rest-overlay"
        style="display:none;position:fixed;inset:0;background:rgba(10,10,15,0.92);z-index:150;flex-direction:column;align-items:center;justify-content:center;gap:20px;backdrop-filter:blur(4px)">
        <div
            style="font-family:'Bebas Neue',sans-serif;font-size:clamp(36px,5.5vw,70px);letter-spacing:.15em;color:rgba(61,90,254,0.8);text-transform:uppercase">
            Descanso
        </div>
        <div id="rest-countdown"
            style="font-family:'Bebas Neue',sans-serif;font-size:clamp(120px,22vw,240px);line-height:1;color:#3d5afe;text-shadow:0 0 100px rgba(61,90,254,0.5);animation:restPulse 1s ease-in-out infinite">
            5
        </div>
        <div id="rest-next-label"
            style="font-size:clamp(14px,2vw,24px);font-weight:700;color:rgba(255,255,255,0.4);letter-spacing:.08em;text-transform:uppercase">
            Siguiente ronda →
        </div>
        <style>
            @keyframes restPulse {

                0%,
                100% {
                    transform: scale(1);
                    opacity: 1;
                }

                50% {
                    transform: scale(1.03);
                    opacity: .8;
                }
            }
        </style>
    </div>

    <!-- LIVE SESSION SCREEN (hidden initially) -->
    <div class="live-screen" id="live-screen" style="display:none">


        <!-- Top bar -->
        <div>
            <div class="display-topbar">
                <?php if ($sala['gym_logo']): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($sala['gym_logo']) ?>" alt="Logo"
                        class="display-gym-logo">
                <?php else: ?>
                    <div class="display-gym-name">
                        <?php echo htmlspecialchars($sala['gym_name']) ?>
                    </div>
                <?php endif; ?>

                <div class="display-sala-info">
                    <div class="display-sala-name">
                        <?php echo htmlspecialchars($sala['name']) ?>
                    </div>
                    <div class="display-session-name" id="display-session-name"></div>
                </div>
            </div>
            <div class="display-total-progress">
                <div class="display-total-progress-fill" id="total-progress-fill" style="width:0%"></div>
            </div>
        </div>

        <!-- Main content -->
        <div class="display-main">
            <!-- Left: current block -->
            <div class="display-current-block">
                <div class="display-status-label" id="status-label">WORK</div>

                <div class="display-clock" id="display-clock">0:00</div>

                <div class="display-exercise-name" id="display-exercise-name">—</div>

                <!-- Exercise list: all exercises in current block as chips -->
                <div id="exercise-list"
                    style="display:none;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:10px;margin-bottom:4px">
                </div>

                <div class="display-block-info">
                    <div class="display-block-meta" id="block-meta">—</div>
                </div>

                <div style="width:100%;max-width:600px">
                    <div
                        style="display:flex;justify-content:space-between;font-size:14px;color:rgba(255,255,255,0.4);margin-bottom:8px">
                        <span id="block-type-label">—</span>
                        <span id="block-time-total">—</span>
                    </div>
                    <div class="block-progress-bar-wrap">
                        <div class="block-progress-fill" id="block-progress-fill" style="width:0%"></div>
                    </div>
                </div>
            </div>

            <!-- Right panel -->
            <div class="display-right-panel">
                <!-- Rounds counter (shown when relevant) -->
                <div class="display-rounds-widget" id="rounds-widget" style="display:none">
                    <div class="display-rounds-label">Ronda</div>
                    <div class="display-rounds-value">
                        <span id="current-round">1</span><span class="display-rounds-total"> / <span
                                id="total-rounds">—</span></span>
                    </div>
                </div>

                <!-- Stickman exercise animator -->
                <div id="stickman-container" style="width:100%"></div>

                <!-- Next block -->
                <div class="display-next-block">
                    <div class="display-next-label">A continuación</div>
                    <div id="next-block-type" class="display-next-type">—</div>
                    <div id="next-block-name" class="display-next-name">—</div>
                    <div id="next-block-duration" class="display-next-duration"></div>
                </div>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="display-bottom-bar">
            <div class="block-dots" id="block-dots"></div>
            <div class="display-block-counter" id="block-counter">0 / 0</div>
        </div>

        <!-- Now Playing Widget -->
        <div id="sp-widget"
            style="display:none;position:fixed;bottom:24px;right:24px;background:rgba(0,0,0,0.75);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:12px 16px;min-width:240px;max-width:300px;z-index:100">
            <div style="display:flex;align-items:center;gap:10px">
                <img id="sp-cover" src="" alt=""
                    style="width:40px;height:40px;border-radius:6px;object-fit:cover;flex-shrink:0">
                <div style="flex:1;min-width:0">
                    <div id="sp-track"
                        style="font-size:12px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    </div>
                    <div id="sp-artist"
                        style="font-size:10px;color:rgba(255,255,255,0.55);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    </div>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#1DB954" style="flex-shrink:0">
                    <path
                        d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z" />
                </svg>
            </div>
            <div style="margin-top:8px;height:2px;background:rgba(255,255,255,0.15);border-radius:1px;overflow:hidden">
                <div id="sp-progress"
                    style="height:100%;background:#1DB954;border-radius:1px;transition:width 1s linear;width:0%"></div>
            </div>
        </div>
    </div>

    <!-- Paused overlay -->
    <div class="paused-overlay" id="paused-overlay">
        <div class="paused-icon">⏸</div>
        <div class="paused-text">PAUSA</div>
    </div>

    <!-- Finished screen -->
    <div class="finished-screen" id="finished-screen" style="display:none">
        <div class="finished-icon">🏆</div>
        <div class="finished-title">¡EXCELENTE!</div>
        <div class="finished-subtitle">Sesión completada</div>
    </div>

    <!-- Flash transition effect -->
    <div class="block-transition-flash" id="transition-flash" style="display:none"></div>

    <script>
        const SALA_ID = <?php echo (int) $sala['id'] ?>;
        const BASE = '<?php echo BASE_URL ?>';
        const GYM_ACCENT = '<?php echo htmlspecialchars($sala['primary_color'] ?? '#00f5d4') ?>';
        const GYM_ACCENT2 = '<?php echo htmlspecialchars($sala['secondary_color'] ?? '#ff6b35') ?>';
        window.GF_SOCKET_URL = 'http://localhost:3001';
    </script>
    <script src="http://localhost:3001/socket.io/socket.io.js"></script>
    <script src="<?php echo BASE_URL ?>/assets/js/display-sync.js"></script>
    <script>
        // Spotify now-playing widget
        async function spPollDisplay() {
            try {
                const r = await fetch(BASE + '/api/spotify.php?action=now-playing&sala_id=' + SALA_ID, { credentials: 'include' });
                if (!r.ok) { setTimeout(spPollDisplay, 8000); return; }
                const d = await r.json();
                const widget = document.getElementById('sp-widget');
                if (d.playing && d.track) {
                    document.getElementById('sp-track').textContent = d.track;
                    document.getElementById('sp-artist').textContent = d.artists || '';
                    const cover = document.getElementById('sp-cover');
                    if (d.cover) cover.src = d.cover;
                    if (d.duration_ms > 0) {
                        document.getElementById('sp-progress').style.width = ((d.progress_ms / d.duration_ms) * 100).toFixed(1) + '%';
                    }
                    widget.style.display = 'block';
                } else {
                    widget.style.display = 'none';
                }
            } catch (e) { }
            setTimeout(spPollDisplay, 4000);
        }
        // Start polling after 3s so session loads first
        setTimeout(spPollDisplay, 3000);
    </script>
    <script src="<?php echo BASE_URL ?>/assets/js/exercise-poses.js"></script>
    <script src="<?php echo BASE_URL ?>/assets/js/stickman.js"></script>
</body>

</html>