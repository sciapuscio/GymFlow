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
            <a href="<?php echo BASE_URL ?>/pages/display/sala.php?code=<?php echo urlencode($session['display_code']) ?>" target="_blank"
                class="btn btn-secondary btn-sm">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
                Display
            </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL ?>/pages/instructor/builder.php?id=<?php echo $id ?>" class="btn btn-secondary btn-sm">
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
                <div style="display:flex;gap:8px">
                    <button class="btn btn-secondary btn-sm" onclick="liveControl('extend',{seconds:30})">+30s</button>
                    <button class="btn btn-secondary btn-sm" onclick="liveControl('extend',{seconds:60})">+1min</button>
                    <button class="btn btn-danger btn-sm" onclick="liveControl('stop')">⏹ Terminar</button>
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
        </div>

        <!-- Right: Block list + Spotify -->
        <div style="display:flex;flex-direction:column;gap:16px;overflow:hidden">

            <!-- Block list -->
            <div class="card" style="overflow-y:auto;flex:1">
                <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-muted)">
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
                            <div style="width:6px;height:36px;border-radius:3px;background:<?php echo $col ?>;flex-shrink:0"></div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:<?php echo $col ?>;letter-spacing:.08em">
                                    <?php echo strtoupper($block['type']) ?>
                                </div>
                                <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <?php echo htmlspecialchars($block['name'] ?? 'Bloque') ?>
                                </div>
                                <?php if (!empty($block['spotify_name'])): ?>
                                <div style="font-size:10px;color:#1DB954;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:2px;display:flex;align-items:center;gap:4px">
                                    <svg width="9" height="9" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z"/></svg>
                                    <?php echo htmlspecialchars($block['spotify_name']) ?><?php if (!empty($block['spotify_intro'])): ?> · <?php echo (int)$block['spotify_intro'] ?>s prep<?php endif; ?>
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#1DB954"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z"/></svg>
                    <span style="font-size:13px;font-weight:700">Música</span>
                    <?php if (!$spotifyConnected): ?>
                        <a href="<?php echo BASE_URL ?>/pages/instructor/profile.php" class="badge badge-muted" style="margin-left:auto;font-size:11px;text-decoration:none">Conectar →</a>
                    <?php else: ?>
                        <span class="badge badge-work" style="margin-left:auto;font-size:10px">Activo</span>
                    <?php endif; ?>
                </div>

                <?php if ($spotifyConnected): ?>
                <!-- Now playing -->
                <div id="sp-now-playing" style="display:none;margin-bottom:12px">
                    <div style="display:flex;gap:10px;align-items:center">
                        <img id="sp-cover" src="" alt="" style="width:44px;height:44px;border-radius:6px;object-fit:cover;display:none">
                        <div style="flex:1;min-width:0">
                            <div id="sp-track" style="font-size:12px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">—</div>
                            <div id="sp-artist" style="font-size:11px;color:var(--gf-text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">—</div>
                        </div>
                    </div>
                    <div style="margin-top:8px;height:3px;background:rgba(255,255,255,.1);border-radius:2px;overflow:hidden">
                        <div id="sp-progress-bar" style="height:100%;background:#1DB954;border-radius:2px;transition:width .5s linear;width:0%"></div>
                    </div>
                </div>

                <!-- Controls -->
                <div style="display:flex;align-items:center;justify-content:center;gap:8px">
                    <button class="btn btn-ghost btn-sm" onclick="spControl('prev')" style="padding:6px;width:32px;height:32px;justify-content:center">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/></svg>
                    </button>
                    <button class="btn btn-ghost btn-sm" id="sp-play-btn" onclick="spTogglePlay()" style="padding:6px;width:38px;height:38px;justify-content:center;border-radius:50%">
                        <svg id="sp-play-ico" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="spControl('next')" style="padding:6px;width:32px;height:32px;justify-content:center">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zm2-8.14 5.1 2.14L8 14.14V9.86zM16 6h2v12h-2z"/></svg>
                    </button>
                </div>

                <!-- Search -->
                <div style="margin-top:12px;display:flex;gap:6px">
                    <input class="form-control" id="sp-search" placeholder="Buscar playlist o canción..." style="font-size:12px;padding:6px 10px" onkeydown="if(event.key==='Enter')spSearch()">
                    <button class="btn btn-ghost btn-sm" onclick="spSearch()">🔍</button>
                </div>
                <div id="sp-results" style="margin-top:8px;max-height:160px;overflow-y:auto;display:flex;flex-direction:column;gap:4px"></div>

                <?php else: ?>
                <p style="font-size:12px;color:var(--gf-text-muted);text-align:center;padding:8px 0">
                    Conectá tu cuenta Spotify en<br><a href="<?php echo BASE_URL ?>/pages/instructor/profile.php" style="color:#1DB954">Mi Perfil</a> para usar música en tus sesiones.
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

    .sp-result-item {
        display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:6px;cursor:pointer;transition:background .12s;font-size:12px;
    }
    .sp-result-item:hover { background:var(--gf-surface-2); }
    .sp-result-item img { width:32px;height:32px;border-radius:4px;object-fit:cover;flex-shrink:0; }
</style>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script src="<?php echo BASE_URL ?>/assets/js/live-control.js"></script>
<script>
    const SESSION_DATA = <?php echo json_encode(['id' => $id, 'blocks' => $blocks, 'status' => $session['status'], 'sala_id' => $session['sala_id'], 'current_block_index' => (int) $session['current_block_index'], 'current_block_elapsed' => (int) $session['current_block_elapsed']]) ?>;
    const SALAS = <?php echo json_encode($salas) ?>;
    const SPOTIFY_CONNECTED = <?php echo $spotifyConnected ? 'true' : 'false' ?>;

    document.addEventListener('DOMContentLoaded', () => {
        GFLive.init(SESSION_DATA);
        if (SPOTIFY_CONNECTED) spPollNowPlaying();
    });

    // ── Spotify Controls ─────────────────────────────────────────────────────
    let spPlaying = false;
    let spPollTimer = null;

    async function spPollNowPlaying() {
        clearTimeout(spPollTimer);
        try {
            const d = await GF.get(window.GF_BASE + '/api/spotify.php?action=now-playing');
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
                ? '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>'  // pause icon
                : '<path d="M8 5v14l11-7z"/>';                     // play icon
        } catch(e) {}
        spPollTimer = setTimeout(spPollNowPlaying, 3000);
    }
    // Called immediately after autoplay fires so the UI reflects the new track fast
    function spRefreshNow() { setTimeout(spPollNowPlaying, 1200); }

    async function spTogglePlay() {
        await GF.post(window.GF_BASE + '/api/spotify.php?action=' + (spPlaying ? 'pause' : 'play'), {});
        setTimeout(spPollNowPlaying, 600);
    }

    async function spControl(action) {
        await GF.post(window.GF_BASE + '/api/spotify.php?action=' + action, {});
        setTimeout(spPollNowPlaying, 800);
    }

    async function spSearch() {
        const q = document.getElementById('sp-search').value.trim();
        if (!q) return;
        const res = document.getElementById('sp-results');
        res.innerHTML = '<div style="font-size:11px;color:var(--gf-text-muted);padding:6px">Buscando...</div>';
        const d = await GF.get(window.GF_BASE + '/api/spotify.php?action=search&type=track,playlist&q=' + encodeURIComponent(q));
        res.innerHTML = '';
        const tracks = d.tracks?.items || [];
        const playlists = d.playlists?.items || [];
        [...tracks.slice(0,5), ...playlists.slice(0,4)].forEach(item => {
            if (!item) return;
            const isPlaylist = !!item.tracks;
            const img = item.album?.images?.[0]?.url || item.images?.[0]?.url || '';
            const name = item.name || '';
            const sub = isPlaylist ? (item.owner?.display_name || '') : item.artists?.map(a=>a.name).join(', ');
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
        await GF.post(window.GF_BASE + '/api/spotify.php?action=play', body);
        document.getElementById('sp-search').value = '';
        document.getElementById('sp-results').innerHTML = '';
        setTimeout(spPollNowPlaying, 1000);
    }
</script>
<?php layout_end(); ?>
