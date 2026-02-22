<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = (int) $user['gym_id'];

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header('Location: ' . BASE_URL . '/pages/instructor/dashboard.php');
    exit;
}

$stmt = db()->prepare("SELECT gs.*, s.name as sala_name, s.display_code, g.primary_color, g.secondary_color FROM gym_sessions gs LEFT JOIN salas s ON gs.sala_id = s.id LEFT JOIN gyms g ON gs.gym_id = g.id WHERE gs.id = ? AND gs.instructor_id = ?");
$stmt->execute([$id, $user['id']]);
$session = $stmt->fetch();
if (!$session) {
    header('Location: ' . BASE_URL . '/pages/instructor/dashboard.php');
    exit;
}

$stmtSalas = db()->prepare("SELECT id, name, display_code FROM salas WHERE gym_id = ? AND active = 1");
$stmtSalas->execute([$gymId]);
$salas = $stmtSalas->fetchAll();

$blocks = json_decode($session['blocks_json'], true) ?: [];

// Check Spotify connection
$spStmt = db()->prepare("SELECT spotify_access_token, spotify_client_id FROM instructor_profiles WHERE user_id = ?");
$spStmt->execute([$user['id']]);
$spProfile = $spStmt->fetch();
$spotifyConnected = !empty($spProfile['spotify_access_token']);

layout_header('Control en Vivo — ' . $session['name'], 'live', $user);
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'live');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'live');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'live');
nav_item(BASE_URL . '/pages/instructor/library.php', 'Biblioteca', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>', 'library', 'live');
nav_item(BASE_URL . '/pages/instructor/profile.php', 'Mi Perfil', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'profile', 'live');
layout_footer($user);
?>

<!-- Live Page Header -->
<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="<?php echo BASE_URL ?>/pages/instructor/dashboard.php" class="btn btn-ghost btn-icon">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <h1 style="font-size:18px;font-weight:700">
                <?php echo htmlspecialchars($session['name']) ?>
            </h1>
            <div style="font-size:12px;color:var(--gf-text-muted)" id="status-label">Listo para iniciar</div>
        </div>
    </div>
    <div class="flex gap-2 ml-auto">
        <select id="live-sala-select" class="form-control" style="width:auto;padding:8px 12px;font-size:13px"
            onchange="setSala(this.value)">
            <option value="">— Sin sala —</option>
            <?php foreach ($salas as $s): ?>
                <option value="<?php echo $s['id'] ?>" <?php echo ($s['id'] == ($session['sala_id'] ?? 0)) ? 'selected' : '' ?>>
                    <?php echo htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($session['display_code'] ?? null): ?>
            <a href="<?php echo BASE_URL ?>/pages/display/sala.php?code=<?php echo urlencode($session['display_code']) ?>"
                target="_blank" class="btn btn-secondary btn-sm">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                Display
            </a>
            <button id="btn-wod-overlay" class="btn btn-secondary btn-sm" onclick="toggleWodOverlay()"
                title="Mostrar/ocultar resumen WOD en sala">
                📋 WOD
            </button>
            <button id="btn-clock-mode" class="btn btn-secondary btn-sm" onclick="toggleClockMode()"
                title="Mostrar/ocultar reloj en pantalla">
                🕐 Reloj
            </button>
        <?php endif; ?>
        <a href="<?php echo BASE_URL ?>/pages/instructor/builder.php?id=<?php echo $id ?>"
            class="btn btn-secondary btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Editar
        </a>
    </div>
</div>

<div class="page-body" style="padding:20px">
    <div class="grid" style="grid-template-columns:1fr 320px;gap:20px;height:calc(100vh - 140px)">

        <!-- Live Clock Panel (left) -->
        <div class="card" style="display:flex;flex-direction:column;gap:20px">

            <!-- Current Block Display -->
            <div style="text-align:center;padding:20px 0">
                <div id="live-block-type" class="badge badge-accent"
                    style="font-size:14px;padding:6px 16px;margin-bottom:12px">—</div>
                <div id="live-block-name"
                    style="font-family:var(--font-display);font-size:42px;letter-spacing:.05em;margin-bottom:8px">
                    PREPARADO</div>
                <div id="live-exercise-name" style="font-size:20px;color:var(--gf-text-muted)">—</div>
            </div>

            <!-- Big Clock -->
            <div style="text-align:center">
                <div id="live-clock"
                    style="font-family:var(--font-display);font-size:min(15vw,140px);color:var(--gf-accent);line-height:1;text-shadow:0 0 40px rgba(0,245,212,0.3);font-variant-numeric:tabular-nums">
                    0:00</div>
                <div id="live-block-info" style="color:var(--gf-text-muted);font-size:14px;margin-top:8px"></div>
            </div>

            <!-- Block Progress bar -->
            <div style="height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden">
                <div id="live-block-progress"
                    style="height:100%;background:var(--gf-accent);border-radius:3px;transition:width .5s linear;width:0%">
                </div>
            </div>

            <!-- Controls -->
            <div style="display:flex;flex-direction:column;gap:12px;align-items:center">
                <div style="display:flex;gap:12px;align-items:center">
                    <button class="btn btn-secondary" onclick="liveControl('prev')" id="btn-prev"
                        style="width:48px;height:48px;padding:0;justify-content:center">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button class="btn btn-primary" id="btn-play" onclick="togglePlay()"
                        style="width:72px;height:72px;padding:0;justify-content:center;border-radius:50%;font-size:24px">▶</button>
                    <button class="btn btn-secondary" onclick="liveControl('skip')"
                        style="width:48px;height:48px;padding:0;justify-content:center">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span
                        style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--gf-text-muted)">Prep</span>
                    <div id="prep-selector" style="display:flex;gap:4px">
                        <button class="prep-btn" onclick="setPrepTime(0)" data-sec="0">0s</button>
                        <button class="prep-btn" onclick="setPrepTime(5)" data-sec="5">5s</button>
                        <button class="prep-btn" onclick="setPrepTime(10)" data-sec="10">10s</button>
                        <button class="prep-btn" onclick="setPrepTime(15)" data-sec="15">15s</button>
                        <button class="prep-btn" onclick="setPrepTime(30)" data-sec="30">30s</button>
                    </div>
                </div>
                <!-- Autoplay / Manual toggle -->
                <div style="display:flex;align-items:center;gap:10px">
                    <span
                        style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--gf-text-muted)">Modo</span>
                    <label class="gf-switch" title="Continuo: avanza solo. Manual: pausa entre bloques.">
                        <input type="checkbox" id="autoplay-switch" checked onchange="setAutoPlay(this.checked)">
                        <span class="gf-switch-track"></span>
                    </label>
                    <span id="autoplay-label"
                        style="font-size:12px;font-weight:600;color:var(--gf-text-muted);min-width:48px">Continuo</span>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-secondary btn-sm" onclick="liveControl('extend',{seconds:30})">+30s</button>
                    <button class="btn btn-secondary btn-sm" onclick="liveControl('extend',{seconds:60})">+1min</button>
                    <button class="btn btn-danger btn-sm" onclick="liveControl('stop')">&#9209; Terminar</button>
                </div>
            </div>

            <!-- Total progress -->
            <div>
                <div
                    style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:12px;color:var(--gf-text-muted)">
                    <span>Progreso total</span>
                    <span id="progress-text">0 /
                        <?php echo count($blocks) ?> bloques
                    </span>
                </div>
                <div style="height:4px;background:rgba(255,255,255,0.07);border-radius:2px;overflow:hidden">
                    <div id="live-total-progress"
                        style="height:100%;background:linear-gradient(90deg,var(--gf-accent),var(--gf-accent-2));transition:width .5s;width:0%">
                    </div>
                </div>
            </div>

            <!-- Clock Mode Config Panel -->
            <div id="clock-config-panel"
                style="border-top:1px solid var(--gf-border);padding-top:16px;display:none;flex-direction:column;gap:12px">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span
                        style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-muted)">🕐
                        Reloj en Pantalla</span>
                    <span id="clock-status-badge"
                        style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;letter-spacing:.06em;background:var(--gf-surface-2);color:var(--gf-text-muted)">INACTIVO</span>
                </div>

                <!-- Mode selector -->
                <div>
                    <label
                        style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--gf-text-muted);display:block;margin-bottom:6px">Modo</label>
                    <div style="display:flex;gap:4px;flex-wrap:wrap">
                        <button class="clock-mode-btn active" data-mode="session"
                            onclick="setClockDisplayMode('session')">Sesión</button>
                        <button class="clock-mode-btn" data-mode="countdown"
                            onclick="setClockDisplayMode('countdown')">Cuenta Regresiva</button>
                        <button class="clock-mode-btn" data-mode="countup"
                            onclick="setClockDisplayMode('countup')">Conteo Arriba</button>
                    </div>
                </div>

                <!-- Custom params (hidden for 'session' mode) -->
                <div id="clock-custom-params" style="display:none;flex-direction:column;gap:8px">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label
                                style="font-size:10px;font-weight:700;color:var(--gf-text-muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:4px">Duración
                                (seg)</label>
                            <input type="number" id="clock-cfg-duration" class="form-control"
                                style="font-size:12px;padding:5px 8px" min="10" max="7200" value="600">
                        </div>
                        <div>
                            <label
                                style="font-size:10px;font-weight:700;color:var(--gf-text-muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:4px">Rondas</label>
                            <input type="number" id="clock-cfg-rounds" class="form-control"
                                style="font-size:12px;padding:5px 8px" min="1" max="99" value="1">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <div>
                            <label
                                style="font-size:10px;font-weight:700;color:var(--gf-text-muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:4px">Trabajo
                                (seg)</label>
                            <input type="number" id="clock-cfg-work" class="form-control"
                                style="font-size:12px;padding:5px 8px" min="1" max="600" value="20">
                        </div>
                        <div>
                            <label
                                style="font-size:10px;font-weight:700;color:var(--gf-text-muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:4px">Descanso
                                (seg)</label>
                            <input type="number" id="clock-cfg-rest" class="form-control"
                                style="font-size:12px;padding:5px 8px" min="0" max="600" value="10">
                        </div>
                    </div>
                </div>

                <!-- Quick Presets -->
                <div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <span
                            style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--gf-text-muted)">Presets</span>
                        <button class="btn btn-ghost btn-sm" onclick="saveClockPreset()"
                            style="font-size:10px;padding:2px 8px">+ Guardar</button>
                    </div>
                    <div id="clock-presets" style="display:flex;flex-wrap:wrap;gap:4px"></div>
                </div>
            </div>
        </div>

        <!-- Right: Block list + Spotify -->
        <div style="display:flex;flex-direction:column;gap:16px;overflow:hidden">

            <!-- Stickman mini (exercise technique viewer) -->
            <div class="card" style="padding:14px;display:flex;gap:14px;align-items:flex-start">
                <div id="stickman-mini" style="flex-shrink:0;width:80px"></div>
                <div id="stickman-live-tips" style="flex:1;display:flex;flex-direction:column;gap:4px;padding-top:4px">
                    <div
                        style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-muted);margin-bottom:4px">
                        Técnica</div>
                </div>
            </div>

            <!-- Block list -->
            <div class="card" style="overflow-y:auto;flex:1">
                <h3
                    style="font-size:14px;font-weight:700;margin-bottom:16px;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-muted)">
                    Bloques (<?php echo count($blocks) ?>)
                </h3>
                <div style="display:flex;flex-direction:column;gap:6px" id="block-list">
                    <?php foreach ($blocks as $i => $block):
                        $dur = formatDuration(computeBlockDuration($block));
                        $typeColors = ['interval' => '#00f5d4', 'tabata' => '#ff6b35', 'amrap' => '#7c3aed', 'emom' => '#0ea5e9', 'fortime' => '#f59e0b', 'series' => '#ec4899', 'circuit' => '#10b981', 'rest' => '#3d5afe', 'briefing' => '#6b7280'];
                        $col = $typeColors[$block['type']] ?? '#888';
                        ?>
                        <div class="block-list-item" data-idx="<?php echo $i ?>" onclick="jumpToBlock(<?php echo $i ?>)"
                            style="padding:10px 12px;background:var(--gf-surface-2);border:1px solid var(--gf-border);border-radius:8px;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:10px">
                            <div
                                style="width:6px;height:36px;border-radius:3px;background:<?php echo $col ?>;flex-shrink:0">
                            </div>
                            <div style="flex:1;min-width:0">
                                <div
                                    style="font-size:11px;font-weight:700;text-transform:uppercase;color:<?php echo $col ?>;letter-spacing:.08em">
                                    <?php echo strtoupper($block['type']) ?>
                                </div>
                                <div
                                    style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <?php echo htmlspecialchars($block['name'] ?? 'Bloque') ?>
                                </div>
                                <?php if (!empty($block['spotify_name'])): ?>
                                    <div
                                        style="font-size:10px;color:#1DB954;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;display:flex;align-items:center;gap:4px">
                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="#1DB954">
                                            <path
                                                d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z" />
                                        </svg>
                                        <?php echo htmlspecialchars($block['spotify_name']) ?>
                                        <?php if (!empty($block['spotify_intro'])): ?>
                                            · <?php echo (int) $block['spotify_intro'] ?>s prep<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:12px;color:var(--gf-text-muted);font-weight:600;flex-shrink:0">
                                <?php echo $dur ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <!-- Spotify Panel -->
            <div class="card" id="spotify-panel">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#1DB954">
                        <path
                            d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z" />
                    </svg>
                    <span style="font-size:13px;font-weight:700">Música</span>
                    <?php if (!$spotifyConnected): ?>
                        <a href="<?php echo BASE_URL ?>/pages/instructor/profile.php" class="badge badge-muted"
                            style="margin-left:auto;font-size:11px;text-decoration:none">Conectar →</a>
                    <?php else: ?>
                        <span class="badge badge-work" style="margin-left:auto;font-size:10px">Activo</span>
                    <?php endif; ?>
                </div>

                <?php if ($spotifyConnected): ?>
                    <!-- Now playing -->
                    <div id="sp-now-playing" style="display:none;margin-bottom:12px">
                        <div style="display:flex;gap:10px;align-items:center">
                            <img id="sp-cover" src="" alt=""
                                style="width:44px;height:44px;border-radius:6px;object-fit:cover;display:none">
                            <div style="flex:1;min-width:0">
                                <div id="sp-track"
                                    style="font-size:12px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    —</div>
                                <div id="sp-artist"
                                    style="font-size:11px;color:var(--gf-text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    —</div>
                            </div>
                        </div>
                        <div
                            style="margin-top:8px;height:3px;background:rgba(255,255,255,.1);border-radius:2px;overflow:hidden">
                            <div id="sp-progress-bar"
                                style="height:100%;background:#1DB954;border-radius:2px;transition:width .5s linear;width:0%">
                            </div>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div style="display:flex;align-items:center;justify-content:center;gap:8px">
                        <button class="btn btn-ghost btn-sm" onclick="spControl('prev')"
                            style="padding:6px;width:32px;height:32px;justify-content:center">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 6h2v12H6zm3.5 6 8.5 6V6z" />
                            </svg>
                        </button>
                        <button class="btn btn-ghost btn-sm" id="sp-play-btn" onclick="spTogglePlay()"
                            style="padding:6px;width:38px;height:38px;justify-content:center;border-radius:50%">
                            <svg id="sp-play-ico" width="16" height="16" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </button>
                        <button class="btn btn-ghost btn-sm" onclick="spControl('next')"
                            style="padding:6px;width:32px;height:32px;justify-content:center">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 18l8.5-6L6 6v12zm2-8.14 5.1 2.14L8 14.14V9.86zM16 6h2v12h-2z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Search -->
                    <div style="margin-top:12px;display:flex;gap:6px">
                        <input class="form-control" id="sp-search" placeholder="Buscar playlist o canción..."
                            style="font-size:12px;padding:6px 10px" onkeydown="if(event.key==='Enter')spSearch()">
                        <button class="btn btn-ghost btn-sm" onclick="spSearch()">🔍</button>
                    </div>
                    <div id="sp-results"
                        style="margin-top:8px;max-height:160px;overflow-y:auto;display:flex;flex-direction:column;gap:4px">
                    </div>

                <?php else: ?>
                    <p style="font-size:12px;color:var(--gf-text-muted);text-align:center;padding:8px 0">
                        Conectá tu cuenta Spotify en<br><a href="<?php echo BASE_URL ?>/pages/instructor/profile.php"
                            style="color:#1DB954">Mi Perfil</a> para usar música en tus sesiones.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .block-list-item.current {
        border-color: var(--gf-accent) !important;
        background: var(--gf-accent-dim) !important;
    }

    .block-list-item.done {
        opacity: .45;
    }

    #btn-play.paused {
        background: #ff9800;
    }

    #btn-play.playing {
        background: var(--gf-accent);
    }

    .prep-btn {
        background: var(--gf-surface-2);
        border: 1px solid var(--gf-border);
        color: var(--gf-text-muted);
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 9px;
        cursor: pointer;
        transition: all .15s;
        line-height: 1;
    }

    .prep-btn:hover {
        border-color: var(--gf-accent);
        color: var(--gf-accent);
    }

    .prep-btn.active {
        background: var(--gf-accent);
        border-color: var(--gf-accent);
        color: #000;
    }

    /* ── Clock Mode Buttons ──────────────────────────────────────── */
    .clock-mode-btn {
        background: var(--gf-surface-2);
        border: 1px solid var(--gf-border);
        color: var(--gf-text-muted);
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        padding: 4px 9px;
        cursor: pointer;
        transition: all .15s;
        line-height: 1;
    }

    .clock-mode-btn:hover {
        border-color: var(--gf-accent);
        color: var(--gf-accent);
    }

    .clock-mode-btn.active {
        background: var(--gf-accent);
        border-color: var(--gf-accent);
        color: #000;
    }

    .clock-preset-btn {
        background: var(--gf-surface-2);
        border: 1px solid var(--gf-border);
        color: var(--gf-text-muted);
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        padding: 4px 9px;
        cursor: pointer;
        transition: all .15s;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .clock-preset-btn:hover {
        border-color: var(--gf-accent);
        color: var(--gf-accent);
    }

    .clock-preset-delete {
        opacity: 0.5;
        font-size: 9px;
        cursor: pointer;
    }

    .clock-preset-delete:hover {
        opacity: 1;
        color: #ef4444;
    }

    .sp-result-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: background .12s;
        font-size: 12px;
    }

    .sp-result-item:hover {
        background: var(--gf-surface-2);
    }

    .sp-result-item img {
        width: 32px;
        height: 32px;
        border-radius: 4px;
        object-fit: cover;
        flex-shrink: 0;
    }

    /* ── Autoplay Toggle Switch ───────────────────────────────────────────── */
    .gf-switch {
        position: relative;
        display: inline-flex;
        cursor: pointer;
        flex-shrink: 0;
    }

    .gf-switch input {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }

    .gf-switch-track {
        width: 38px;
        height: 22px;
        background: var(--gf-surface-2);
        border: 1px solid var(--gf-border);
        border-radius: 11px;
        transition: background .2s, border-color .2s;
        position: relative;
    }

    .gf-switch-track::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: var(--gf-text-muted);
        transition: transform .2s, background .2s;
    }

    .gf-switch input:checked+.gf-switch-track {
        background: var(--gf-accent);
        border-color: var(--gf-accent);
    }

    .gf-switch input:checked+.gf-switch-track::after {
        transform: translateX(16px);
        background: #000;
    }

    /* ── Toast Notifications ───────────────────────────────────────────────── */
    #gf-toast-container {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
    }

    .gf-toast {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        max-width: 300px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, .4);
        backdrop-filter: blur(8px);
        pointer-events: auto;
        animation: gf-toast-in .25s cubic-bezier(.34, 1.56, .64, 1) forwards;
        border: 1px solid rgba(255, 255, 255, .1);
    }

    .gf-toast.hiding {
        animation: gf-toast-out .3s ease forwards;
    }

    .gf-toast-icon {
        font-size: 15px;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .gf-toast-body {
        flex: 1;
        line-height: 1.4;
    }

    .gf-toast-title {
        font-weight: 700;
        margin-bottom: 2px;
    }

    .gf-toast-msg {
        font-weight: 400;
        opacity: .85;
    }

    .gf-toast.error {
        background: rgba(220, 38, 38, .85);
        color: #fff;
    }

    .gf-toast.warning {
        background: rgba(217, 119, 6, .85);
        color: #fff;
    }

    .gf-toast.info {
        background: rgba(37, 99, 235, .85);
        color: #fff;
    }

    .gf-toast.success {
        background: rgba(5, 150, 105, .85);
        color: #fff;
    }

    @keyframes gf-toast-in {
        from {
            opacity: 0;
            transform: translateX(40px) scale(.92);
        }

        to {
            opacity: 1;
            transform: translateX(0) scale(1);
        }
    }

    @keyframes gf-toast-out {
        from {
            opacity: 1;
            transform: translateX(0) scale(1);
            max-height: 120px;
            margin: 0;
        }

        to {
            opacity: 0;
            transform: translateX(40px) scale(.9);
            max-height: 0;
            margin: -8px 0 0;
        }
    }
</style>

<!-- Toast container -->
<div id="gf-toast-container"></div>

<script src="http://localhost:3001/socket.io/socket.io.js"></script>
<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script src="<?php echo BASE_URL ?>/assets/js/exercise-poses.js"></script>
<script src="<?php echo BASE_URL ?>/assets/js/stickman.js"></script>
<script src="<?php echo BASE_URL ?>/assets/js/live-control.js"></script>
<script>
    const SESSION_DATA = <?php echo json_encode(['id' => $id, 'blocks' => $blocks, 'status' => $session['status'], 'sala_id' => (int) $session['sala_id'], 'current_block_index' => (int) $session['current_block_index'], 'current_block_elapsed' => (int) $session['current_block_elapsed']]) ?>;
    const SALAS = <?php echo json_encode($salas) ?>;
    const SPOTIFY_CONNECTED = <?php echo $spotifyConnected ? 'true' : 'false' ?>;
    window.GF_SOCKET_URL = 'http://localhost:3001';

    document.addEventListener('DOMContentLoaded', () => {
        GFLive.init(SESSION_DATA);
        if (SPOTIFY_CONNECTED) spPollNowPlaying();
        // Init prep time from localStorage
        const savedPrep = parseInt(localStorage.getItem('gf_prep_time') || '0', 10);
        setPrepTime(isNaN(savedPrep) ? 0 : savedPrep);
        // Init autoplay mode from localStorage (default ON)
        const savedAP = localStorage.getItem('gf_autoplay');
        const initAP = savedAP === null ? true : savedAP === '1';
        // Apply to switch UI immediately, then emit to server once socket connects
        const apSwitch = document.getElementById('autoplay-switch');
        if (apSwitch) apSwitch.checked = initAP;
        const apLabel = document.getElementById('autoplay-label');
        if (apLabel) apLabel.textContent = initAP ? 'Continuo' : 'Manual';
        // Emit after a short delay to ensure socket is connected
        setTimeout(() => setAutoPlay(initAP), 1200);
    });

    // ── Prep Time Selector ──────────────────────────────────────────────────
    function setPrepTime(sec) {
        window._gfPrepTime = sec;
        localStorage.setItem('gf_prep_time', String(sec));
        document.querySelectorAll('.prep-btn').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.sec) === sec);
        });
    }

    // ── Toast Notifications ──────────────────────────────────────────────────
    function gfToast(type, title, msg, duration = 4000) {
        const container = document.getElementById('gf-toast-container');
        const toast = document.createElement('div');
        toast.className = `gf-toast ${type}`;
        const icons = { error: '🚫', warning: '⚠️', info: 'ℹ️', success: '✅' };
        toast.innerHTML = `
            <span class="gf-toast-icon">${icons[type] || 'ℹ️'}</span>
            <div class="gf-toast-body">
                <div class="gf-toast-title">${title}</div>
                ${msg ? `<div class="gf-toast-msg">${msg}</div>` : ''}
            </div>`;
        container.appendChild(toast);
        const dismiss = () => {
            toast.classList.add('hiding');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        };
        toast.addEventListener('click', dismiss);
        setTimeout(dismiss, duration);
    }

    // Maps Spotify HTTP status codes to human-readable messages
    function spCheckStatus(res, action = '') {
        const st = res?.status ?? 0;
        if (!st || st === 200 || st === 204) return; // all good
        const labels = {
            429: ['Límite de Spotify', 'Demasiadas solicitudes. Esperá unos segundos.'],
            403: ['Sin permiso', 'Tu cuenta Spotify no permite esta acción (¿es Premium?).'],
            404: ['Sin dispositivo activo', 'Abrí Spotify en tu celular o PC y reproducí algo primero.'],
            401: ['Sesión expirada', 'Reconectá tu cuenta Spotify desde Mi Perfil.'],
        };
        const [title, msg] = labels[st] || [`Error Spotify ${st}`, action ? `Falló al ejecutar: ${action}` : ''];
        const type = st === 429 ? 'warning' : st === 404 ? 'info' : 'error';
        gfToast(type, title, msg);
    }

    // ── Spotify Controls ─────────────────────────────────────────────────────
    let spPlaying = false;
    let spPollTimer = null;
    let sp429Streak = 0;       // consecutive 429 responses
    let spRefreshPending = false; // debounce rapid refresh calls

    async function spPollNowPlaying() {
        clearTimeout(spPollTimer);
        let nextInterval = 10000; // base: 10s (down from 3s)
        try {
            const d = await GF.get(window.GF_BASE + '/api/spotify.php?action=now-playing');

            // 429 backoff: 30s → 60s → 120s (cap)
            if (d?.status === 429) {
                sp429Streak++;
                nextInterval = Math.min(120000, 30000 * sp429Streak);
                spCheckStatus(d, 'now-playing');
                spPollTimer = setTimeout(spPollNowPlaying, nextInterval);
                return;
            }
            sp429Streak = 0;

            const np = document.getElementById('sp-now-playing');
            if (d.playing && d.track) {
                document.getElementById('sp-track').textContent = d.track;
                document.getElementById('sp-artist').textContent = d.artists || '';
                const img = document.getElementById('sp-cover');
                if (d.cover) { img.src = d.cover; img.style.display = 'block'; }
                if (d.duration_ms > 0) {
                    document.getElementById('sp-progress-bar').style.width = ((d.progress_ms / d.duration_ms) * 100).toFixed(1) + '%';
                }
                np.style.display = 'block';
            } else {
                np.style.display = 'none';
            }
            const ico = document.getElementById('sp-play-ico');
            spPlaying = d.playing;
            ico.innerHTML = d.playing
                ? '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>'
                : '<path d="M8 5v14l11-7z"/>';
        } catch (e) { }
        spPollTimer = setTimeout(spPollNowPlaying, nextInterval);
    }

    // Debounced refresh: after an action (play/skip) do ONE refresh 1.5s later,
    // ignoring any additional refresh calls that arrive in that window.
    function spRefreshNow() {
        if (spRefreshPending) return;
        spRefreshPending = true;
        setTimeout(() => { spRefreshPending = false; spPollNowPlaying(); }, 1500);
    }

    async function spTogglePlay() {
        const res = await GF.post(window.GF_BASE + '/api/spotify.php?action=' + (spPlaying ? 'pause' : 'play'), {});
        spCheckStatus(res, spPlaying ? 'pause' : 'play');
        setTimeout(spPollNowPlaying, 600);
    }

    async function spControl(action) {
        const res = await GF.post(window.GF_BASE + '/api/spotify.php?action=' + action, {});
        spCheckStatus(res, action);
        setTimeout(spPollNowPlaying, 800);
    }

    async function spSearch() {
        const q = document.getElementById('sp-search').value.trim();
        if (!q) return;
        const res = document.getElementById('sp-results');
        res.innerHTML = '<div style="font-size:11px;color:var(--gf-text-muted);padding:6px">Buscando...</div>';
        const d = await GF.get(window.GF_BASE + '/api/spotify.php?action=search&type=track,playlist&q=' + encodeURIComponent(q));
        if (d?.status && d.status !== 200 && d.status !== 204) {
            spCheckStatus(d, 'search');
            res.innerHTML = '';
            return;
        }
        res.innerHTML = '';
        const tracks = d.tracks?.items || [];
        const playlists = d.playlists?.items || [];
        [...tracks.slice(0, 5), ...playlists.slice(0, 4)].forEach(item => {
            if (!item) return;
            const isPlaylist = !!item.tracks;
            const img = item.album?.images?.[0]?.url || item.images?.[0]?.url || '';
            const name = item.name || '';
            const sub = isPlaylist ? (item.owner?.display_name || '') : item.artists?.map(a => a.name).join(', ');
            const uri = item.uri;
            const el = document.createElement('div');
            el.className = 'sp-result-item';
            el.innerHTML = img ? `<img src="${img}">` : `<div style="width:32px;height:32px;background:var(--gf-surface-2);border-radius:4px;flex-shrink:0"></div>`;
            el.innerHTML += `<div style="flex:1;min-width:0"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${name}</div><div style="color:var(--gf-text-muted);font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${sub}</div></div>`;
            el.onclick = () => spPlay(uri, isPlaylist);
            res.appendChild(el);
        });
    }

    async function spPlay(uri, isContext = false) {
        const body = isContext ? { context_uri: uri } : { uris: [uri] };
        const res = await GF.post(window.GF_BASE + '/api/spotify.php?action=play', body);
        spCheckStatus(res, 'play');
        document.getElementById('sp-search').value = '';
        document.getElementById('sp-results').innerHTML = '';
        setTimeout(spPollNowPlaying, 1000);
    }

    // ── WOD Summary Overlay ──────────────────────────────────────────────────
    let _wodOverlayActive = false;
    function toggleWodOverlay() {
        _wodOverlayActive = !_wodOverlayActive;
        GFLive.emitWodOverlay(_wodOverlayActive, <?php echo json_encode($blocks) ?>);
        const btn = document.getElementById('btn-wod-overlay');
        if (btn) {
            btn.style.background = _wodOverlayActive ? 'var(--gf-accent)' : '';
            btn.style.color = _wodOverlayActive ? '#000' : '';
            btn.style.borderColor = _wodOverlayActive ? 'var(--gf-accent)' : '';
        }
    }

    // ── Clock Mode ──────────────────────────────────────────────────────────
    window._clockModeActive = false;
    let _clockDisplayMode = 'session';

    const _DEFAULT_PRESETS = [
        { name: 'Tabata 20/10×8', mode: 'session', config: { work: 20, rest: 10, rounds: 8 } },
        { name: 'EMOM 10′', mode: 'session', config: { duration: 600 } },
        { name: 'AMRAP 12′', mode: 'session', config: { duration: 720 } },
        { name: 'Countdown 5′', mode: 'countdown', config: { duration: 300 } },
        { name: 'Count-Up', mode: 'countup', config: { duration: 600 } },
    ];

    function toggleClockMode() {
        window._clockModeActive = !window._clockModeActive;
        _syncClockBtnLocal(window._clockModeActive);

        const panel = document.getElementById('clock-config-panel');
        if (panel) panel.style.display = window._clockModeActive ? 'flex' : 'none';

        // Emit to server regardless of panel visibility
        _emitCurrentClockMode();
    }

    function _syncClockBtnLocal(active) {
        const btn = document.getElementById('btn-clock-mode');
        if (!btn) return;
        btn.style.background = active ? 'var(--gf-accent)' : '';
        btn.style.color = active ? '#000' : '';
        btn.style.borderColor = active ? 'var(--gf-accent)' : '';
        const badge = document.getElementById('clock-status-badge');
        if (badge) {
            badge.textContent = active ? 'ACTIVO' : 'INACTIVO';
            badge.style.background = active ? 'var(--gf-accent)' : 'var(--gf-surface-2)';
            badge.style.color = active ? '#000' : 'var(--gf-text-muted)';
        }
    }

    function setClockDisplayMode(mode) {
        _clockDisplayMode = mode;
        // Toggle active class on mode buttons
        document.querySelectorAll('.clock-mode-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.mode === mode);
        });
        // Show/hide custom params section
        const params = document.getElementById('clock-custom-params');
        if (params) params.style.display = (mode === 'session') ? 'none' : 'flex';
        // If clock is active, update immediately
        if (window._clockModeActive) _emitCurrentClockMode();
    }

    function _getClockConfig() {
        return {
            duration: parseInt(document.getElementById('clock-cfg-duration')?.value || '600', 10),
            rounds: parseInt(document.getElementById('clock-cfg-rounds')?.value || '1', 10),
            work: parseInt(document.getElementById('clock-cfg-work')?.value || '20', 10),
            rest: parseInt(document.getElementById('clock-cfg-rest')?.value || '10', 10),
        };
    }

    function _emitCurrentClockMode() {
        GFLive.emitClockMode(
            window._clockModeActive,
            _clockDisplayMode,
            _clockDisplayMode === 'session' ? {} : _getClockConfig()
        );
    }

    // ── Preset management ─────────────────────────────────────────────────────
    function _loadClockPresets() {
        try { return JSON.parse(localStorage.getItem('gf_clock_presets') || 'null') || _DEFAULT_PRESETS; }
        catch { return _DEFAULT_PRESETS.slice(); }
    }

    function _saveClockPresets(presets) {
        localStorage.setItem('gf_clock_presets', JSON.stringify(presets));
    }

    function saveClockPreset() {
        const name = prompt('Nombre del preset:');
        if (!name) return;
        const presets = _loadClockPresets();
        presets.push({ name: name.trim(), mode: _clockDisplayMode, config: _getClockConfig() });
        _saveClockPresets(presets);
        renderClockPresets();
    }

    function applyClockPreset(idx) {
        const presets = _loadClockPresets();
        const p = presets[idx];
        if (!p) return;
        setClockDisplayMode(p.mode);
        if (p.config) {
            const d = document.getElementById('clock-cfg-duration'); if (d && p.config.duration) d.value = p.config.duration;
            const r = document.getElementById('clock-cfg-rounds'); if (r && p.config.rounds) r.value = p.config.rounds;
            const w = document.getElementById('clock-cfg-work'); if (w && p.config.work) w.value = p.config.work;
            const rs = document.getElementById('clock-cfg-rest'); if (rs && p.config.rest) rs.value = p.config.rest;
        }
        if (window._clockModeActive) _emitCurrentClockMode();
    }

    function deleteClockPreset(idx) {
        const presets = _loadClockPresets();
        presets.splice(idx, 1);
        _saveClockPresets(presets);
        renderClockPresets();
    }

    function renderClockPresets() {
        const container = document.getElementById('clock-presets');
        if (!container) return;
        const presets = _loadClockPresets();
        container.innerHTML = presets.map((p, i) => `
            <button class="clock-preset-btn" onclick="applyClockPreset(${i})" title="${p.mode}">
                ${p.name}
                <span class="clock-preset-delete" onclick="event.stopPropagation();deleteClockPreset(${i})" title="Eliminar">✕</span>
            </button>
        `).join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderClockPresets();
    });
</script>
<?php layout_end(); ?>