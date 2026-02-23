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

    <style>
        /* DSEG7 Classic — hosted locally (CDN was returning 500) */
        @font-face {
            font-family: 'DSEG7 Classic';
            src: url('/assets/fonts/DSEG7Classic-Regular.woff2') format('woff2'),
                url('/assets/fonts/DSEG7Classic-Regular.woff') format('woff');
            font-weight: normal;
            font-style: normal;
            font-display: block;
        }
    </style>

    <style>
        /* ── CLOCK MODE — CrossFit Box Hardware Style ───────────────────────── */

        /* WOD shrinks to top 75% when clock is active */
        body.clock-active .live-screen {
            height: 75vh !important;
            max-height: 75vh !important;
            box-sizing: border-box;
        }

        /* Smooth WOD transition */
        .live-screen {
            transition: height .3s cubic-bezier(.4, 0, .2, 1), max-height .3s cubic-bezier(.4, 0, .2, 1);
        }

        /* ═══════════════════════════════════════════════════════════════
           HARDWARE TIMER — physical box aesthetic
           ═══════════════════════════════════════════════════════════════ */

        /* ── Panel shell — looks like the back of a wall-mounted LED box ── */
        .clock-panel {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: auto;
            /* Dark brushed-metal finish */
            background:
                /* dot-matrix grid texture */
                repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(0, 0, 0, .18) 3px, rgba(0, 0, 0, .18) 4px),
                repeating-linear-gradient(90deg, transparent, transparent 3px, rgba(0, 0, 0, .18) 3px, rgba(0, 0, 0, .18) 4px),
                linear-gradient(180deg, #1a1a1a 0%, #0d0d0d 100%);
            /* Physical top-bevel + shadow depth */
            border-top: 2px solid #383838;
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, .06),
                inset 0 -1px 0 rgba(0, 0, 0, .9),
                0 -4px 20px rgba(0, 0, 0, .8);
            z-index: 120;
            overflow: hidden;
            align-items: center;
            justify-content: center;
        }

        body.clock-active .clock-panel {
            display: flex;
        }

        /* Inner row */
        .clock-hw-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(8px, 2.4vw, 40px);
            width: 100%;
            padding: clamp(10px, 1.6vh, 18px) clamp(12px, 4vw, 60px);
        }

        /* ── Left info column ── */
        .clock-hw-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            min-width: 0;
        }

        .clock-sub {
            font-family: 'Bebas Neue', 'Impact', sans-serif;
            font-size: clamp(13px, 2.2vh, 26px);
            color: rgba(255, 255, 255, 0.82);
            letter-spacing: .08em;
            white-space: nowrap;
            line-height: 1;
        }

        .clock-progress-label {
            font-size: clamp(8px, 1.2vh, 13px);
            color: rgba(255, 255, 255, 0.15);
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .clock-progress-fill {
            display: none;
        }

        /* kept for JS compat */

        /* ── PHYSICAL DIGIT ENCLOSURE — the rectangular LED housing ── */
        .clock-hw-digits-block {
            display: flex;
            align-items: center;
            gap: clamp(4px, 1vw, 16px);
        }

        /* The phase label sits in its own rectangular cell */
        .clock-phase-wrapper {
            position: relative;
            line-height: 1;
            /* Physical enclosure */
            background: #080808;
            border: 1.5px solid #2a2a2a;
            border-radius: 3px;
            padding: clamp(3px, .8vh, 8px) clamp(5px, 1vw, 12px);
            box-shadow:
                inset 0 0 12px rgba(0, 0, 0, .9),
                inset 0 1px 0 rgba(0, 0, 0, .8);
        }

        .clock-phase-wrapper::before {
            content: attr(data-ghost);
            position: absolute;
            inset: 0;
            padding: inherit;
            font-family: 'DSEG7 Classic', monospace;
            font-size: inherit;
            /* Strong ghost — 20% makes segments clearly visible */
            color: rgba(10, 111, 255, 0.20);
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
        }

        .clock-phase-label {
            font-family: 'Bebas Neue', 'Impact', sans-serif;
            font-size: clamp(22px, 6vh, 72px);
            line-height: 1;
            color: #0a6fff;
            text-shadow:
                0 0 6px rgba(10, 111, 255, 1),
                0 0 18px rgba(10, 111, 255, 0.6),
                0 0 40px rgba(10, 111, 255, 0.2);
            text-transform: uppercase;
            white-space: nowrap;
            letter-spacing: .04em;
            font-variant-numeric: tabular-nums;
            position: relative;
            /* above ghost */
        }

        /* The time digits — main LED panel */
        .clock-digits-wrapper {
            position: relative;
            line-height: 1;
            background: #060606;
            border: 1.5px solid #2e2e2e;
            border-radius: 3px;
            padding: clamp(4px, 1vh, 10px) clamp(8px, 1.4vw, 18px);
            box-shadow:
                inset 0 0 20px rgba(0, 0, 0, .95),
                inset 0 2px 4px rgba(0, 0, 0, .8),
                0 0 0 1px #111;
        }

        /* Ghost digits — clearly visible inactive segments */
        .clock-digits-wrapper::before {
            content: attr(data-ghost);
            position: absolute;
            inset: 0;
            padding: inherit;
            font-family: 'DSEG7 Classic', monospace;
            font-size: inherit;
            color: rgba(255, 36, 0, 0.18);
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
            letter-spacing: inherit;
        }

        .clock-digits {
            font-family: 'DSEG7 Classic', monospace;
            font-size: clamp(54px, 17vh, 170px);
            line-height: 1;
            letter-spacing: .04em;
            color: #ff3300;
            text-shadow:
                0 0 4px rgba(255, 51, 0, 1),
                0 0 12px rgba(255, 51, 0, 0.8),
                0 0 28px rgba(255, 51, 0, 0.35);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            position: relative;
            /* above ghost */
            transition: color .25s, text-shadow .25s;
        }

        /* REST phase: blue */
        body.state-rest .clock-digits {
            color: #1a7fff;
            text-shadow:
                0 0 4px rgba(26, 127, 255, 1),
                0 0 12px rgba(26, 127, 255, 0.8),
                0 0 28px rgba(26, 127, 255, 0.35);
        }

        body.state-rest .clock-digits-wrapper::before {
            color: rgba(26, 127, 255, 0.18);
        }

        body.state-rest .clock-digits-wrapper {
            border-color: #1a3a5e;
            box-shadow: inset 0 0 20px rgba(0, 0, 6, .95), inset 0 2px 4px rgba(0, 0, 0, .8), 0 0 0 1px #0a1a30;
        }

        /* ── Expand button ── */
        .clock-fs-btn {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 4px;
            color: rgba(255, 255, 255, 0.25);
            font-size: clamp(14px, 2.2vh, 20px);
            padding: 4px 8px;
            cursor: pointer;
            transition: color .2s, border-color .2s;
            flex-shrink: 0;
        }

        .clock-fs-btn:hover {
            color: #ff3300;
            border-color: #ff3300;
        }

        /* ═══════════════════════════════════════════════════════════
           FULLSCREEN overlay — same hardware look, fully dark room
           ═══════════════════════════════════════════════════════════ */
        #clock-fullscreen {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 500;
            /* Same dot-matrix background */
            background:
                repeating-linear-gradient(0deg, transparent, transparent 5px, rgba(0, 0, 0, .08) 5px, rgba(0, 0, 0, .08) 6px),
                repeating-linear-gradient(90deg, transparent, transparent 5px, rgba(0, 0, 0, .08) 5px, rgba(0, 0, 0, .08) 6px),
                #070707;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2.5vh;
            cursor: pointer;
        }

        body.clock-fs #clock-fullscreen {
            display: flex;
        }

        body.clock-fs .live-screen {
            display: none !important;
        }

        body.clock-fs .clock-panel {
            display: none !important;
        }

        /* Phase label — fullscreen */
        .clock-fs-phase-wrapper {
            position: relative;
            line-height: 1;
            background: #080808;
            border: 2px solid #272727;
            border-radius: 4px;
            padding: clamp(6px, 1.2vh, 14px) clamp(14px, 2vw, 30px);
            box-shadow: inset 0 0 30px rgba(0, 0, 0, .95);
        }

        .clock-fs-phase-wrapper::before {
            content: attr(data-ghost);
            position: absolute;
            inset: 0;
            padding: inherit;
            font-family: 'DSEG7 Classic', monospace;
            font-size: inherit;
            color: rgba(10, 111, 255, 0.20);
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
        }

        .clock-fs-phase {
            font-family: 'DSEG7 Classic', monospace;
            font-size: clamp(50px, 10vw, 150px);
            line-height: 1;
            color: #0a6fff;
            text-shadow:
                0 0 6px rgba(10, 111, 255, 1),
                0 0 20px rgba(10, 111, 255, 0.6),
                0 0 50px rgba(10, 111, 255, 0.2);
            letter-spacing: .06em;
            white-space: nowrap;
            position: relative;
        }

        /* Fullscreen digits — big physical box */
        .clock-fs-digits-wrapper {
            position: relative;
            line-height: 1;
            background: #060606;
            border: 2px solid #2e2e2e;
            border-radius: 4px;
            padding: clamp(10px, 2vh, 28px) clamp(20px, 3vw, 50px);
            box-shadow:
                inset 0 0 60px rgba(0, 0, 0, .98),
                inset 0 3px 8px rgba(0, 0, 0, .9),
                0 0 0 1px #111,
                0 4px 40px rgba(0, 0, 0, .8);
        }

        .clock-fs-digits-wrapper::before {
            content: attr(data-ghost);
            position: absolute;
            inset: 0;
            padding: inherit;
            font-family: 'DSEG7 Classic', monospace;
            font-size: inherit;
            color: rgba(255, 51, 0, 0.18);
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
            letter-spacing: inherit;
        }

        .clock-fs-digits {
            font-family: 'DSEG7 Classic', monospace;
            font-size: clamp(120px, 28vw, 420px);
            line-height: 1;
            letter-spacing: .04em;
            color: #ff3300;
            text-shadow:
                0 0 6px rgba(255, 51, 0, 1),
                0 0 20px rgba(255, 51, 0, 0.75),
                0 0 55px rgba(255, 51, 0, 0.3);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            position: relative;
            transition: color .25s, text-shadow .25s;
        }

        body.state-rest .clock-fs-digits {
            color: #1a7fff;
            text-shadow:
                0 0 6px rgba(26, 127, 255, 1),
                0 0 20px rgba(26, 127, 255, 0.75),
                0 0 55px rgba(26, 127, 255, 0.3);
        }

        body.state-rest .clock-fs-digits-wrapper::before {
            color: rgba(26, 127, 255, 0.18);
        }

        body.state-rest .clock-fs-digits-wrapper {
            border-color: #1a3a5e;
        }

        /* Sub text */
        .clock-fs-sub {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(18px, 2.6vw, 44px);
            color: rgba(255, 255, 255, 0.22);
            letter-spacing: .3em;
            text-transform: uppercase;
        }

        .clock-fs-hint {
            position: absolute;
            bottom: 20px;
            right: 28px;
            font-size: clamp(10px, 1.2vw, 14px);
            color: rgba(255, 255, 255, 0.08);
            letter-spacing: .15em;
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
        <div style="display:flex;flex-direction:column;align-items:center;gap:6px">
            <div
                style="font-size:clamp(13px,1.6vw,20px);font-weight:700;color:rgba(255,255,255,0.35);letter-spacing:.12em;text-transform:uppercase">
                Siguiente →
            </div>
            <div id="rest-next-exercise"
                style="font-family:'Bebas Neue',sans-serif;font-size:clamp(28px,4vw,58px);letter-spacing:.08em;color:rgba(255,255,255,0.75);text-transform:uppercase;text-align:center">
            </div>
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

            <!-- Center column: exercise list chips -->
            <div class="display-center-panel">
                <div id="exercise-list" style="display:none;flex-direction:column;align-items:center;gap:16px">
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
        <?php if ($sala['gym_logo']): ?>
            <img src="<?php echo BASE_URL . htmlspecialchars($sala['gym_logo']) ?>"
                alt="<?php echo htmlspecialchars($sala['gym_name']) ?>" class="finished-gym-logo"
                onerror="this.style.display='none'">
        <?php endif; ?>
        <div class="finished-icon">🏆</div>
        <div class="finished-title">¡EXCELENTE!</div>
        <div class="finished-subtitle">Sesión completada, ¡Gracias por venir!</div>
    </div>

    <div id="wod-overlay"
        style="display:none;position:fixed;inset:0;z-index:300;background:rgba(8,8,14,0.97);backdrop-filter:blur(12px);flex-direction:column;align-items:center;justify-content:flex-start;padding:clamp(20px,3vh,48px) clamp(20px,4vw,80px);overflow:hidden;gap:0">
        <div style="width:100%;max-width:1000px">
            <!-- Header -->
            <div id="wod-overlay-header"
                style="display:flex;align-items:center;gap:24px;margin-bottom:clamp(16px,2.5vh,36px)">
                <?php if ($sala['gym_logo']): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($sala['gym_logo']) ?>"
                        alt="<?php echo htmlspecialchars($sala['gym_name']) ?>"
                        style="height:clamp(48px,7vh,90px);max-width:200px;object-fit:contain;filter:brightness(1.15)"
                        onerror="this.style.display='none'">
                <?php else: ?>
                    <div
                        style="height:clamp(48px,7vh,90px);aspect-ratio:1;border-radius:50%;background:var(--d-accent,#00f5d4);display:flex;align-items:center;justify-content:center;font-family:'Bebas Neue',sans-serif;font-size:clamp(20px,3vw,36px);color:#000">
                        <?php echo htmlspecialchars(strtoupper(substr($sala['gym_name'], 0, 2))) ?>
                    </div>
                <?php endif; ?>
                <div style="display:flex;align-items:baseline;gap:14px">
                    <div
                        style="font-family:'Bebas Neue',sans-serif;font-size:clamp(36px,5vw,72px);letter-spacing:.12em;color:#fff;line-height:1">
                        WOD</div>
                    <div id="wod-session-title"
                        style="font-size:clamp(14px,1.8vw,22px);font-weight:600;color:rgba(255,255,255,0.45);letter-spacing:.06em;text-transform:uppercase">
                    </div>
                </div>
            </div>
            <!-- Block list -->
            <div id="wod-block-list" style="display:flex;flex-direction:column;gap:12px"></div>
        </div>
    </div>

    <!-- Flash transition effect -->
    <div class="block-transition-flash" id="transition-flash" style="display:none"></div>

    <!-- CLOCK MODE PANEL — CrossFit Box Hardware Timer ──────────────────────
         Layout mirrors real hardware:  [INFO]  [PHASE]  [TIME]
         Phase label = LED blue   |   Time digits = LED red
    ──────────────────────────────────────────────────────────────────────── -->
    <div class="clock-panel" id="clock-panel">
        <div class="clock-hw-row">

            <!-- Left: round counter + block name (small, dim) -->
            <div class="clock-hw-info">
                <div class="clock-sub" id="clock-sub"></div>
                <div class="clock-progress-label" id="clock-progress-label"></div>
            </div>

            <!-- Physical LED housing: phase cell + time cell -->
            <div class="clock-hw-digits-block">
                <!-- Phase label (WORK / REST / CD / CU…) in LED blue -->
                <div class="clock-phase-wrapper" id="clock-phase-wrapper" data-ghost="88">
                    <div class="clock-phase-label" id="clock-phase-label">--</div>
                </div>

                <!-- Time countdown in LED red -->
                <div class="clock-digits-wrapper" id="clock-digits-wrapper" data-ghost="88:88">
                    <div class="clock-digits" id="clock-digits">00:00</div>
                </div>
            </div>

            <!-- Right: expand to fullscreen button -->
            <button class="clock-fs-btn" onclick="window.toggleClockFs()" title="Pantalla completa">&#x26F6;</button>

        </div>
        <!-- Hidden fill: kept for JS compatibility in display-sync.js -->
        <div style="display:none">
            <div id="clock-progress-fill"></div>
        </div>
    </div>

    <!-- FULLSCREEN CLOCK OVERLAY — takes 100% screen, WOD hidden completely -->
    <div id="clock-fullscreen" onclick="window.toggleClockFs()" title="Click para salir">
        <div class="clock-fs-phase-wrapper" id="clock-fs-phase-wrapper" data-ghost="88">
            <div class="clock-fs-phase" id="clock-fs-phase">--</div>
        </div>
        <div class="clock-fs-digits-wrapper" id="clock-fs-digits-wrapper" data-ghost="88:88">
            <div class="clock-fs-digits" id="clock-fs-digits">00:00</div>
        </div>
        <div class="clock-fs-sub" id="clock-fs-sub"></div>
        <div class="clock-fs-hint">&#x26F6; toca para salir</div>
    </div>

    <script>
        const SALA_ID = <?php echo (int) $sala['id'] ?>;
        const BASE = '<?php echo BASE_URL ?>';
        const GYM_ACCENT = '<?php echo htmlspecialchars($sala['primary_color'] ?? '#00f5d4') ?>';
        const GYM_ACCENT2 = '<?php echo htmlspecialchars($sala['secondary_color'] ?? '#ff6b35') ?>';
        window.GF_SOCKET_URL = 'https://trainingsocket.access.ly';
    </script>
    <script src="https://trainingsocket.access.ly/socket.io/socket.io.js"></script>
    <script src="<?php echo BASE_URL ?>/assets/js/display-sync.js"></script>
    <script>
        // Spotify now-playing widget
        // Polls at 15s intervals — decorative widget, doesn't need high reactivity.
        // Backs off to 60s if Spotify returns 429 (rate limited).
        let spDisplay429 = false;
        async function spPollDisplay() {
            const interval = spDisplay429 ? 60000 : 15000;
            spDisplay429 = false;
            try {
                const r = await fetch(BASE + '/api/spotify.php?action=now-playing&sala_id=' + SALA_ID, { credentials: 'include' });
                if (!r.ok) { setTimeout(spPollDisplay, 8000); return; }
                const d = await r.json();
                if (d?.status === 429) { spDisplay429 = true; setTimeout(spPollDisplay, interval); return; }
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
            setTimeout(spPollDisplay, interval);
        }
        // Start polling after 5s so session loads first
        setTimeout(spPollDisplay, 5000);
    </script>
    <script src="<?php echo BASE_URL ?>/assets/js/exercise-poses.js"></script>
    <script src="<?php echo BASE_URL ?>/assets/js/stickman.js"></script>
</body>

</html>