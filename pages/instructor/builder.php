<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = (int) $user['gym_id'];

$stmtSalas = db()->prepare("SELECT id, name, display_code FROM salas WHERE gym_id = ? AND active = 1 ORDER BY name");
$stmtSalas->execute([$gymId]);
$salas = $stmtSalas->fetchAll();

$stmtTpls = db()->prepare("SELECT id, name, total_duration FROM templates WHERE (gym_id = ? AND (created_by = ? OR is_shared = 1)) ORDER BY name");
$stmtTpls->execute([$gymId, $user['id']]);
$templates = $stmtTpls->fetchAll();

// Edit existing session?
$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$editSession = null;
if ($editId) {
    $stmt = db()->prepare("SELECT * FROM gym_sessions WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$editId, $user['id']]);
    $editSession = $stmt->fetch();
}

// Check Spotify connection for builder
$spStmt = db()->prepare("SELECT spotify_access_token FROM instructor_profiles WHERE user_id = ?");
$spStmt->execute([$user['id']]);
$spProfile = $spStmt->fetch();
$spotifyConnected = !empty($spProfile['spotify_access_token']);

layout_header('Builder de Sesión', 'builder', $user);
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'builder');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'builder');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'builder');
nav_item(BASE_URL . '/pages/instructor/library.php', 'Biblioteca', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>', 'library', 'builder');
nav_item(BASE_URL . '/pages/instructor/profile.php', 'Mi Perfil', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'profile', 'builder');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'builder');
layout_footer($user);
?>

<!-- Builder Header -->
<div class="page-header">
    <div class="flex items-center gap-3" style="flex:1">
        <a href="<?php echo BASE_URL ?>/pages/instructor/dashboard.php" class="btn btn-ghost btn-icon"
            style="width:32px;height:32px">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <h1 style="font-size:18px;font-weight:700">Session Builder</h1>
    </div>
    <div class="flex gap-2">
        <select id="sala-select" class="form-control" style="width:auto;padding:8px 12px;font-size:13px">
            <option value="">— Sin sala asignada —</option>
            <?php foreach ($salas as $s): ?>
                <option value="<?php echo $s['id'] ?>">
                    <?php echo htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary btn-sm" onclick="loadTemplate()">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Plantilla
        </button>
        <button class="btn btn-secondary btn-sm" id="save-as-template-btn" onclick="saveAsTemplate()">Guardar
            Plantilla</button>
        <button class="btn" id="wod-gen-btn" onclick="openWodGenerator()"
            style="background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;font-weight:700;font-size:13px;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;box-shadow:0 0 16px rgba(168,85,247,.45);transition:box-shadow .2s,transform .15s;display:flex;align-items:center;gap:7px"
            onmouseover="this.style.boxShadow='0 0 28px rgba(168,85,247,.7)';this.style.transform='translateY(-1px)'"
            onmouseout="this.style.boxShadow='0 0 16px rgba(168,85,247,.45)';this.style.transform=''">
            ✨ Generar WOD
        </button>
        <button class="btn btn-primary" id="save-btn" onclick="saveSession()">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7" />
            </svg>
            Guardar y Continuar
        </button>
    </div>
</div>

<!-- Builder Layout -->
<div class="builder-layout" style="height:calc(100vh - 64px)">

    <!-- LEFT: Block Types + Exercise Library -->
    <div class="builder-panel">
        <div class="builder-panel-header">
            <span
                style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-muted)">Bloques</span>
        </div>
        <div class="builder-panel-body">
            <div class="block-type-grid" id="block-type-palette">
                <?php
                $blockTypes = [
                    ['interval', '⏱️', 'Intervalo'],
                    ['tabata', '🔥', 'Tabata'],
                    ['amrap', '♾️', 'AMRAP'],
                    ['emom', '⚡', 'EMOM'],
                    ['fortime', '🏁', 'For Time'],
                    ['series', '💪', 'Series'],
                    ['circuit', '🔄', 'Circuito'],
                    ['rest', '😴', 'Descanso'],
                    ['briefing', '📋', 'Briefing'],
                ];
                foreach ($blockTypes as [$type, $icon, $label]): ?>
                    <div class="block-type-card" data-type="<?php echo $type ?>" draggable="true"
                        onclick="addBlockByType('<?php echo $type ?>')">
                        <span class="block-type-icon">
                            <?php echo $icon ?>
                        </span>
                        <div class="block-type-name">
                            <?php echo $label ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Exercise Library -->
            <hr style="border-color:var(--gf-border);margin:16px 0">
            <div
                style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-text-dim);margin-bottom:10px">
                Ejercicios</div>

            <div class="search-box mb-4">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" class="form-control" id="ex-search" placeholder="Buscar ejercicio..."
                    oninput="filterExercises(this.value)" style="padding-left:36px;font-size:13px">
            </div>

            <div class="muscle-filter-chips" id="muscle-chips">
                <?php foreach (['chest', 'back', 'shoulders', 'arms', 'core', 'legs', 'glutes', 'full_body', 'cardio'] as $m): ?>
                    <button class="muscle-chip" data-muscle="<?php echo $m ?>" onclick="filterMuscle(this)">
                        <?php echo ucfirst(str_replace('_', ' ', $m)) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div id="exercise-list" style="max-height:300px;overflow-y:auto">
                <div class="spinner" style="margin:20px auto"></div>
            </div>
        </div>
    </div>

    <!-- CENTER: Canvas -->
    <div class="builder-canvas">
        <div class="canvas-toolbar">
            <input type="text" class="session-name-input" id="session-name" placeholder="Nombre de la sesión..."
                value="<?php echo htmlspecialchars($editSession['name'] ?? '') ?>">
        </div>

        <div id="blocks-canvas">
            <div class="drop-zone" id="empty-drop" ondragover="canvasDragOver(event)" ondrop="canvasDrop(event, -1)">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    style="margin:0 auto 8px;display:block;color:var(--gf-text-dim)">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                </svg>
                Arrastrá un bloque o hacé click en un tipo para comenzar
            </div>
        </div>

        <div class="session-summary-bar" id="session-summary">
            <div class="summary-item"><span>Bloques:</span><strong id="sum-blocks">0</strong></div>
            <div class="summary-item"><span>Duración:</span><strong id="sum-duration">0:00</strong></div>
            <div class="summary-item"><span>Sala:</span><strong id="sum-sala">Sin asignar</strong></div>
        </div>
    </div>

    <!-- RIGHT: Properties -->
    <div class="props-panel">
        <div class="builder-panel-header">
            <span
                style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-muted)">Propiedades</span>
        </div>
        <div id="props-content" class="builder-panel-body">
            <div class="props-placeholder">
                <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5" />
                </svg>
                <p>Seleccioná un bloque para editar sus propiedades</p>
            </div>
        </div>
    </div>
</div>

<!-- Load Template Modal -->
<div class="modal-overlay" id="tpl-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Cargar Plantilla</h3>
            <button class="modal-close" onclick="closeTplModal()"><svg width="20" height="20" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($templates as $t): ?>
                <div class="card-hover"
                    style="padding:14px;background:var(--gf-surface-2);border-radius:10px;border:1px solid var(--gf-border);cursor:pointer"
                    onclick="selectTemplate(<?php echo $t['id'] ?>)">
                    <div style="font-weight:600;font-size:14px">
                        <?php echo htmlspecialchars($t['name']) ?>
                    </div>
                    <div style="font-size:12px;color:var(--gf-text-muted);margin-top:4px">Duración:
                        <?php echo formatDuration($t['total_duration'] ?? 0) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($templates)): ?>
                <p style="color:var(--gf-text-dim);text-align:center;padding:20px">No hay plantillas disponibles</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- /tpl-modal -->


<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
    <script src="<?php echo BASE_URL ?>/assets/js/builder.js"></script>
    <script>
        // Initialize builder
        const EDIT_SESSION = <?php echo json_encode($editSession ? ['id' => $editSession['id'], 'blocks_json' => json_decode($editSession['blocks_json'], true), 'sala_id' => $editSession['sala_id']] : null) ?>;
        const SALAS = <?php echo json_encode(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], $salas)) ?>;
        window.SPOTIFY_CONNECTED = <?php echo $spotifyConnected ? 'true' : 'false' ?>;

        document.addEventListener('DOMContentLoaded', () => {
            GFBuilder.init(EDIT_SESSION);
            if (EDIT_SESSION?.sala_id) {
                document.getElementById('sala-select').value = EDIT_SESSION.sala_id;
            }
        });

        function loadTemplate() { document.getElementById('tpl-modal').classList.add('open'); }
        function closeTplModal() { document.getElementById('tpl-modal').classList.remove('open'); }

        async function selectTemplate(id) {
            const r = await fetch(`${window.GF_BASE}/api/templates.php?id=${id}`, { credentials: 'include' });
            const tpl = await r.json();
            const blocks = typeof tpl.blocks_json === 'string' ? JSON.parse(tpl.blocks_json) : tpl.blocks_json;
            if (GFBuilder.blocks.length > 0 && !confirm('¿Reemplazar los bloques actuales?')) return;
            GFBuilder.loadBlocks(blocks);
            document.getElementById('session-name').value = tpl.name;
            closeTplModal();
            showToast('Plantilla cargada', 'success');
        }

        async function saveAsTemplate() {
            const name = prompt('Nombre de la plantilla:', document.getElementById('session-name').value || 'Mi Plantilla');
            if (!name) return;
            await GF.post(window.GF_BASE + '/api/templates.php', {
                name, blocks_json: GFBuilder.blocks, is_shared: false
            });
            showToast('Guardada como plantilla', 'success');
        }

        async function saveSession() {
            const name = document.getElementById('session-name').value.trim() || 'Sesión sin nombre';
            const salaId = document.getElementById('sala-select').value || null;
            const blocks = GFBuilder.blocks;

            document.getElementById('sala-select').options[0] && (document.getElementById('sum-sala').textContent =
                document.getElementById('sala-select').selectedOptions[0]?.text || 'Sin asignar');

            const body = { name, sala_id: salaId ? parseInt(salaId) : null, blocks_json: blocks };
            let resp;
            if (EDIT_SESSION) {
                resp = await GF.put(`${window.GF_BASE}/api/sessions.php?id=${EDIT_SESSION.id}`, body);
            } else {
                resp = await GF.post(window.GF_BASE + '/api/sessions.php', body);
            }

            if (resp?.id || resp?.success) {
                showToast('Sesión guardada', 'success');
                const sessionId = resp.id || EDIT_SESSION?.id;
                setTimeout(() => location.href = `${window.GF_BASE}/pages/instructor/live.php?id=${sessionId}`, 800);
            } else {
                showToast('Error al guardar', 'error');
            }
        }

        // ── Spotify Block Helpers ─────────────────────────────────────────────────
        async function spBlockSearch(blockIdx) {
            const input = document.getElementById(`sp-block-search-${blockIdx}`);
            const res = document.getElementById(`sp-block-results-${blockIdx}`);
            if (!input || !res) return;
            const q = input.value.trim();
            if (!q) return;
            res.innerHTML = `<div style="font-size:11px;color:var(--gf-text-muted);padding:4px">Buscando...</div>`;
            try {
                const d = await GF.get(window.GF_BASE + '/api/spotify.php?action=search&type=track,playlist&q=' + encodeURIComponent(q));
                res.innerHTML = '';
                const tracks = d.tracks?.items || [];
                const playlists = d.playlists?.items || [];
                [...tracks.slice(0, 4), ...playlists.slice(0, 3)].forEach(item => {
                    if (!item) return;
                    const isPlaylist = !!item.tracks;
                    const img = item.album?.images?.[0]?.url || item.images?.[0]?.url || '';
                    const name = item.name || '';
                    const sub = isPlaylist ? (item.owner?.display_name || 'Playlist') : item.artists?.map(a => a.name).join(', ');
                    const uri = item.uri;
                    const el = document.createElement('div');
                    el.style.cssText = 'display:flex;align-items:center;gap:8px;padding:5px 7px;border-radius:6px;cursor:pointer;font-size:11px;transition:background .12s';
                    el.onmouseover = () => el.style.background = 'var(--gf-surface-2)';
                    el.onmouseout = () => el.style.background = '';
                    el.innerHTML = img
                        ? `<img src="${img}" style="width:28px;height:28px;border-radius:4px;object-fit:cover;flex-shrink:0">`
                        : `<div style="width:28px;height:28px;background:var(--gf-surface-2);border-radius:4px;flex-shrink:0"></div>`;
                    el.innerHTML += `<div style="flex:1;min-width:0"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${name}</div><div style="color:var(--gf-text-muted);font-size:10px">${sub}</div></div>`;
                    el.onclick = () => spBlockPick(blockIdx, uri, name, isPlaylist);
                    res.appendChild(el);
                });
                if (!res.children.length) res.innerHTML = `<div style="font-size:11px;color:var(--gf-text-muted);padding:4px">Sin resultados</div>`;
            } catch (e) { res.innerHTML = `<div style="font-size:11px;color:#ef4444;padding:4px">Error al buscar</div>`; }
        }

        function spBlockPick(blockIdx, uri, name, isPlaylist) {
            GFBuilder.setBlockSpotify(blockIdx, uri, name);
            showToast(`🎵 ${name.substring(0, 30)} asignado al bloque`, 'success');
        }

        function spBlockClear(blockIdx) {
            GFBuilder.setBlockSpotify(blockIdx, '', '');
        }
    </script>

    <!-- ╔══════════════════════════════════════════════════════╗ -->
    <!-- ║          WOD GENERATOR — Config Modal               ║ -->
    <!-- ╚══════════════════════════════════════════════════════╝ -->
    <div class="modal-overlay" id="wod-gen-modal" style="z-index:9000">
        <div class="modal" style="max-width:480px;width:94vw">
            <div class="modal-header"
                style="background:linear-gradient(135deg,#a855f7 0%,#6366f1 100%);border-radius:12px 12px 0 0;padding:18px 22px">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:22px">✨</span>
                    <div>
                        <h3 class="modal-title" style="color:#fff;margin:0;font-size:16px">Generador Inteligente de WOD
                        </h3>
                        <p style="color:rgba(255,255,255,.75);font-size:11px;margin:2px 0 0">Sesión de 50 min armada
                            como un profesional</p>
                    </div>
                </div>
                <button class="modal-close" onclick="closeWodGenerator()" style="color:#fff;opacity:.8">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div style="padding:22px;display:flex;flex-direction:column;gap:20px">

                <!-- Dificultad -->
                <div>
                    <label
                        style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-text-muted);display:block;margin-bottom:10px">Nivel
                        de Dificultad</label>
                    <div style="display:flex;gap:8px">
                        <?php foreach ([['beginner', '🟢 Fácil'], ['intermediate', '🟡 Intermedio'], ['advanced', '🔴 Difícil']] as [$val, $lbl]): ?>
                                <label class="wod-level-btn" for="wod-lvl-<?php echo $val ?>"
                                    style="flex:1;text-align:center;padding:10px 6px;border-radius:10px;border:2px solid var(--gf-border);cursor:pointer;font-size:12px;font-weight:600;transition:all .15s;user-select:none">
                                    <input type="radio" name="wod_level" id="wod-lvl-<?php echo $val ?>"
                                        value="<?php echo $val ?>" style="display:none" <?php echo $val === 'intermediate' ? 'checked' : '' ?>>
                                    <?php echo $lbl ?>
                                </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Distribución corporal -->
                <div>
                    <label
                        style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-text-muted);display:block;margin-bottom:12px">Distribución
                        Corporal</label>
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <div class="wod-zone-row">
                            <div
                                style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                                <span style="font-size:12px;font-weight:600">💪 Tren Superior</span>
                                <span id="upper-pct-label"
                                    style="font-size:13px;font-weight:700;color:#a855f7">35%</span>
                            </div>
                            <input type="range" id="upper-pct" min="10" max="70" value="35" class="wod-slider"
                                oninput="syncSliders('upper')">
                        </div>
                        <div class="wod-zone-row">
                            <div
                                style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                                <span style="font-size:12px;font-weight:600">🦵 Tren Inferior</span>
                                <span id="lower-pct-label"
                                    style="font-size:13px;font-weight:700;color:#6366f1">45%</span>
                            </div>
                            <input type="range" id="lower-pct" min="10" max="70" value="45" class="wod-slider"
                                oninput="syncSliders('lower')">
                        </div>
                        <div class="wod-zone-row">
                            <div
                                style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                                <span style="font-size:12px;font-weight:600">🔥 Tren Medio (Core)</span>
                                <span id="core-pct-label"
                                    style="font-size:13px;font-weight:700;color:#f59e0b">20%</span>
                            </div>
                            <input type="range" id="core-pct" min="5" max="50" value="20" class="wod-slider"
                                oninput="syncSliders('core')">
                        </div>
                        <!-- Visual bar -->
                        <div style="height:8px;border-radius:999px;overflow:hidden;display:flex;gap:2px">
                            <div id="bar-upper"
                                style="background:#a855f7;height:100%;border-radius:999px 0 0 999px;transition:width .2s;width:35%">
                            </div>
                            <div id="bar-lower" style="background:#6366f1;height:100%;transition:width .2s;width:45%">
                            </div>
                            <div id="bar-core"
                                style="background:#f59e0b;height:100%;border-radius:0 999px 999px 0;transition:width .2s;width:20%">
                            </div>
                        </div>
                        <div style="text-align:right;font-size:11px;color:var(--gf-text-muted)">Total: <span
                                id="pct-total" style="font-weight:700">100%</span></div>
                    </div>
                </div>

                <!-- Estilo -->
                <div>
                    <label
                        style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-text-muted);display:block;margin-bottom:10px">Estilo
                        de Sesión</label>
                    <div style="display:flex;gap:8px">
                        <?php foreach ([['crossfit', '🏋️ CrossFit'], ['hiit', '⚡ HIIT'], ['strength', '🏆 Fuerza'], ['mixed', '🎯 Mixto']] as [$val, $lbl]): ?>
                                <label class="wod-style-btn" for="wod-sty-<?php echo $val ?>"
                                    style="flex:1;text-align:center;padding:8px 4px;border-radius:10px;border:2px solid var(--gf-border);cursor:pointer;font-size:11px;font-weight:600;transition:all .15s;user-select:none">
                                    <input type="radio" name="wod_style" id="wod-sty-<?php echo $val ?>"
                                        value="<?php echo $val ?>" style="display:none" <?php echo $val === 'crossfit' ? 'checked' : '' ?>>
                                    <?php echo $lbl ?>
                                </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            <?php if ($spotifyConnected): ?>
            <div style="padding:0 22px 12px">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;background:rgba(29,185,84,.07);border:1px solid rgba(29,185,84,.2);border-radius:10px;user-select:none">
                    <input type="checkbox" id="wod-auto-music" checked style="width:16px;height:16px;accent-color:#1DB954;cursor:pointer;flex-shrink:0">
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#1DB954">🎵 Auto-asignar música</div>
                        <div style="font-size:10px;color:var(--gf-text-muted);margin-top:1px">Busca y asigna en Spotify el tema sugerido para cada bloque al cargar el WOD</div>
                    </div>
                </label>
            </div>
            <?php endif; ?>
            <div style="padding:0 22px 22px;display:flex;gap:10px">
                <button class="btn btn-secondary" style="flex:1" onclick="closeWodGenerator()">Cancelar</button>
                <button id="wod-gen-submit" class="btn"
                    style="flex:2;background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;font-weight:700;border:none"
                    onclick="generateWod()">
                    <span id="wod-gen-submit-txt">✨ Generar WOD</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ╔══════════════════════════════════════════════════════╗ -->
    <!-- ║          WOD GENERATOR — Preview Modal              ║ -->
    <!-- ╚══════════════════════════════════════════════════════╝ -->
    <div class="modal-overlay" id="wod-preview-modal" style="z-index:9100">
        <div class="modal" style="max-width:560px;width:96vw;max-height:90vh;display:flex;flex-direction:column">
            <div class="modal-header"
                style="background:linear-gradient(135deg,#a855f7 0%,#6366f1 100%);border-radius:12px 12px 0 0;padding:16px 22px">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:20px">🏋️</span>
                    <div>
                        <h3 class="modal-title" style="color:#fff;margin:0;font-size:15px" id="wod-preview-title">WOD
                            Generado</h3>
                        <p style="color:rgba(255,255,255,.75);font-size:11px;margin:2px 0 0" id="wod-preview-subtitle">—
                        </p>
                    </div>
                </div>
                <button class="modal-close" onclick="closeWodPreview()" style="color:#fff;opacity:.8">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Muscle dist bar -->
            <div style="padding:14px 22px 10px;border-bottom:1px solid var(--gf-border)">
                <div
                    style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--gf-text-muted);margin-bottom:8px">
                    Distribución Muscular Real</div>
                <div id="wod-muscle-bar" style="height:10px;border-radius:999px;overflow:hidden;display:flex;gap:2px">
                </div>
                <div id="wod-muscle-legend" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;font-size:10px">
                </div>
            </div>

            <!-- Blocks list -->
            <div id="wod-blocks-list"
                style="overflow-y:auto;flex:1;padding:14px 22px;display:flex;flex-direction:column;gap:10px"></div>

            <!-- Footer -->
            <div style="padding:14px 22px;border-top:1px solid var(--gf-border);display:flex;gap:10px">
                <button class="btn btn-secondary" style="flex:1" onclick="regenerateWod()">🔄 Regenerar</button>
                <button class="btn"
                    style="flex:2;background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;font-weight:700;border:none"
                    onclick="loadWodIntoBuilder()">✅ Cargar en Builder</button>
            </div>
        </div>
    </div>

    <style>
        /* ── WOD Generator styles ── */
        .wod-level-btn:has(input:checked),
        .wod-style-btn:has(input:checked) {
            border-color: #a855f7 !important;
            background: rgba(168, 85, 247, .12) !important;
            color: #a855f7 !important;
        }

        .wod-slider {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 999px;
            background: var(--gf-border);
            outline: none;
            cursor: pointer;
        }

        .wod-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #a855f7;
            cursor: pointer;
            box-shadow: 0 0 6px rgba(168, 85, 247, .5);
        }

        .wod-block-row {
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--gf-border);
            background: var(--gf-surface-2);
        }

        .wod-block-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }
    </style>

    <script>
        // ── WOD Generator State ─────────────────────────────────────────────────────
        let _wodGeneratedData = null;

        function openWodGenerator() {
            document.getElementById('wod-gen-modal').classList.add('open');
        }
        function closeWodGenerator() {
            document.getElementById('wod-gen-modal').classList.remove('open');
        }
        function closeWodPreview() {
            document.getElementById('wod-preview-modal').classList.remove('open');
        }

        // ── Slider sync (always sum to 100%) ──────────────────────────────────────────
        function syncSliders(changed) {
            const upper = parseInt(document.getElementById('upper-pct').value);
            const lower = parseInt(document.getElementById('lower-pct').value);
            const core = parseInt(document.getElementById('core-pct').value);
            const total = upper + lower + core;

            document.getElementById('upper-pct-label').textContent = Math.round(upper * 100 / total) + '%';
            document.getElementById('lower-pct-label').textContent = Math.round(lower * 100 / total) + '%';
            document.getElementById('core-pct-label').textContent = Math.round(core * 100 / total) + '%';

            document.getElementById('bar-upper').style.width = (upper * 100 / total) + '%';
            document.getElementById('bar-lower').style.width = (lower * 100 / total) + '%';
            document.getElementById('bar-core').style.width = (core * 100 / total) + '%';

            document.getElementById('pct-total').textContent = '100%';
            document.getElementById('pct-total').style.color = 'var(--gf-success, #22c55e)';
        }

        // Style highlights
        document.querySelectorAll('.wod-level-btn, .wod-style-btn').forEach(label => {
            label.addEventListener('click', () => {
                const name = label.querySelector('input').name;
                document.querySelectorAll(`[name=${name}]`).forEach(inp => {
                    inp.closest('label').style.borderColor = '';
                    inp.closest('label').style.background = '';
                    inp.closest('label').style.color = '';
                });
            });
        });

        // ── Call API ─────────────────────────────────────────────────────────────────
        async function generateWod() {
            const btn = document.getElementById('wod-gen-submit');
            const txt = document.getElementById('wod-gen-submit-txt');
            btn.disabled = true;
            txt.textContent = 'Generando...';

            const level = document.querySelector('[name=wod_level]:checked')?.value || 'intermediate';
            const style = document.querySelector('[name=wod_style]:checked')?.value || 'crossfit';
            const upper = parseInt(document.getElementById('upper-pct').value);
            const lower = parseInt(document.getElementById('lower-pct').value);
            const core = parseInt(document.getElementById('core-pct').value);
            const total = upper + lower + core;

            try {
                const resp = await fetch(window.GF_BASE + '/api/wod_generator.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        level,
                        style,
                        upper_pct: Math.round(upper * 100 / total),
                        lower_pct: Math.round(lower * 100 / total),
                        core_pct: Math.round(core * 100 / total),
                    })
                });
                const data = await resp.json();
                if (!data.blocks || data.blocks.length === 0) throw new Error('No blocks returned');
                _wodGeneratedData = data;
                closeWodGenerator();
                openWodPreview(data);
            } catch (e) {
                showToast('Error al generar el WOD', 'error');
                console.error(e);
            } finally {
                btn.disabled = false;
                txt.textContent = '✨ Generar WOD';
            }
        }

        async function regenerateWod() {
            closeWodPreview();
            openWodGenerator();
            await generateWod();
        }

        // ── Render preview ───────────────────────────────────────────────────────────
        const BLOCK_TYPE_COLORS = {
            briefing: '#64748b', rest: '#475569', interval: '#0ea5e9',
            tabata: '#ef4444', emom: '#f59e0b', amrap: '#8b5cf6',
            fortime: '#ec4899', series: '#16a34a', circuit: '#06b6d4',
        };
        const BLOCK_TYPE_LABELS = {
            briefing: 'Briefing', rest: 'Descanso', interval: 'Intervalo',
            tabata: 'Tabata', emom: 'EMOM', amrap: 'AMRAP',
            fortime: 'For Time', series: 'Series', circuit: 'Circuito',
        };
        const MUSCLE_COLORS = {
            chest: '#e11d48', back: '#0ea5e9', shoulders: '#8b5cf6',
            arms: '#f97316', core: '#f59e0b', legs: '#16a34a',
            glutes: '#a855f7', full_body: '#64748b', cardio: '#06b6d4',
        };
        const MUSCLE_LABELS = {
            chest: 'Pecho', back: 'Espalda', shoulders: 'Hombros',
            arms: 'Brazos', core: 'Core', legs: 'Piernas',
            glutes: 'Glúteos', full_body: 'Full Body', cardio: 'Cardio',
        };

        function formatSecs(s) {
            if (s >= 60) return Math.round(s / 60) + ' min';
            return s + 's';
        }

        function renderBlockRow(block, idx) {
            const type = block.type;
            const cfg = block.config || {};
            const color = BLOCK_TYPE_COLORS[type] || '#64748b';
            const label = BLOCK_TYPE_LABELS[type] || type;

            let details = '';
            if (type === 'interval' || type === 'tabata') {
                details = `${cfg.rounds || '?'} rondas · ${cfg.work || '?'}s trabajo / ${cfg.rest || '?'}s descanso`;
            } else if (type === 'amrap') {
                details = `${formatSecs(cfg.duration || 0)} AMRAP`;
            } else if (type === 'emom') {
                details = `EMOM ${formatSecs(cfg.duration || 0)}`;
            } else if (type === 'fortime') {
                details = `${cfg.rounds || '?'} rondas · Time cap ${formatSecs(cfg.time_cap || 0)}`;
            } else if (type === 'series') {
                details = `${cfg.sets || '?'} series · ${cfg.rest || '?'}s descanso`;
            } else if (type === 'circuit') {
                details = `${cfg.rounds || '?'} rondas`;
            } else if (type === 'rest') {
                details = formatSecs(cfg.duration || 60);
            } else if (type === 'briefing') {
                details = formatSecs(cfg.duration || 90);
            }

            const exList = (block.exercises || []).map(ex => {
                const reps = ex.reps ? ` × ${ex.reps}` : '';
                return `<span style="display:inline-block;background:var(--gf-surface-1);border:1px solid var(--gf-border);border-radius:6px;padding:2px 8px;font-size:10px;margin:2px">${ex.name}${reps}</span>`;
            }).join('');

            // Music chip
            let musicHtml = '';
            if (block.music) {
                const m = block.music;
                const isChill = ['briefing','rest'].includes(type);
                const chipBg = isChill
                    ? 'linear-gradient(90deg,#1e3a5f,#0ea5e9)'
                    : 'linear-gradient(90deg,#7c1f1f,#ef4444)';
                const spotifyBtn = window.SPOTIFY_CONNECTED
                    ? `<a href="https://open.spotify.com/search/${m.query}" target="_blank"
                          style="display:inline-flex;align-items:center;gap:3px;background:#1db954;color:#fff;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:600;text-decoration:none;margin-left:6px">
                          <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                          Buscar</a>`
                    : '';
                musicHtml = `
                <div style="margin-top:6px;display:flex;align-items:center;flex-wrap:wrap;gap:4px">
                    <span style="display:inline-flex;align-items:center;gap:5px;background:${chipBg};color:#fff;border-radius:999px;padding:3px 10px;font-size:10px;font-weight:600">
                        ${m.icon} ${m.genre}
                    </span>
                    <span style="font-size:11px;color:var(--gf-text-muted)"><strong>${m.artist}</strong> — ${m.track}</span>
                    ${spotifyBtn}
                </div>`;
            }

            return `
    <div class="wod-block-row">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <span style="font-size:11px;font-weight:700;color:#fff;background:${color};padding:3px 10px;border-radius:999px">${label}</span>
            <span style="font-weight:600;font-size:13px;flex:1">${block.name}</span>
            <span style="font-size:11px;color:var(--gf-text-muted)">${details}</span>
        </div>
        ${exList ? `<div style="margin-top:4px">${exList}</div>` : ''}
        ${musicHtml}
    </div>`;
        }

        function openWodPreview(data) {
            const meta = data.meta || {};

            // Title
            document.getElementById('wod-preview-title').textContent =
                `WOD Generado · ${Math.round((meta.total_duration || 0) / 60)} min`;
            document.getElementById('wod-preview-subtitle').textContent =
                `${meta.label || ''} · Superior ${meta.upper_pct}% · Inferior ${meta.lower_pct}% · Medio ${meta.core_pct}%`;

            // Muscle bar
            const dist = meta.muscle_dist || {};
            const barEl = document.getElementById('wod-muscle-bar');
            const legEl = document.getElementById('wod-muscle-legend');
            barEl.innerHTML = '';
            legEl.innerHTML = '';
            const total = Object.values(dist).reduce((a, b) => a + b, 0) || 1;
            Object.entries(dist).forEach(([mg, cnt], i) => {
                const pct = Math.round(cnt / total * 100);
                const col = MUSCLE_COLORS[mg] || '#64748b';
                const seg = document.createElement('div');
                seg.style.cssText = `width:${pct}%;background:${col};height:100%;transition:width .3s`;
                if (i === 0) seg.style.borderRadius = '999px 0 0 999px';
                if (i === Object.keys(dist).length - 1) seg.style.borderRadius = '0 999px 999px 0';
                barEl.appendChild(seg);
                legEl.innerHTML += `<span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:50%;background:${col};display:inline-block"></span>${MUSCLE_LABELS[mg] || mg} ${pct}%</span>`;
            });

            // Blocks
            const listEl = document.getElementById('wod-blocks-list');
            listEl.innerHTML = data.blocks.map((b, i) => renderBlockRow(b, i)).join('');

            document.getElementById('wod-preview-modal').classList.add('open');
        }

        // ── Load into Builder canvas ──────────────────────────────────────────────────
        async function loadWodIntoBuilder() {
            if (!_wodGeneratedData?.blocks) return;
            if (GFBuilder.blocks.length > 0 && !confirm('¿Reemplazar los bloques actuales con el WOD generado?')) return;
            GFBuilder.loadBlocks(_wodGeneratedData.blocks);
            const meta = _wodGeneratedData.meta || {};
            if (!document.getElementById('session-name').value) {
                const today = new Date();
                const label = `WOD ${today.getDate().toString().padStart(2, '0')}/${(today.getMonth() + 1).toString().padStart(2, '0')} · ${meta.label || ''}`;
                document.getElementById('session-name').value = label;
            }
            closeWodPreview();
            showToast('✨ WOD cargado en el Builder', 'success');

            // ── Auto-assign Spotify music suggestions ─────────────────────────────────
            const autoMusic = document.getElementById('wod-auto-music')?.checked;
            if (!autoMusic || !window.SPOTIFY_CONNECTED) return;

            const blocks = GFBuilder.blocks;
            let assigned = 0;
            showToast('🎵 Buscando música en Spotify...', 'info');

            for (let i = 0; i < blocks.length; i++) {
                const b = blocks[i];
                if (!b.music?.query || b.spotify_uri) continue; // skip if no suggestion or already has track
                try {
                    const d = await GF.get(window.GF_BASE + '/api/spotify.php?action=search&type=track&q=' + encodeURIComponent(b.music.query));
                    const first = d.tracks?.items?.find(t => t?.uri);
                    if (first) {
                        GFBuilder.setBlockSpotify(i, first.uri, first.name);
                        assigned++;
                    }
                } catch (e) { /* silently skip if Spotify fails for one block */ }
                // Small stagger to avoid rate-limiting
                if (i < blocks.length - 1) await new Promise(r => setTimeout(r, 300));
            }

            if (assigned > 0) showToast(`🎵 ${assigned} tema${assigned > 1 ? 's' : ''} asignado${assigned > 1 ? 's' : ''} automáticamente`, 'success');
        }
    </script>

    <?php layout_end(); ?>