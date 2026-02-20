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

    <!-- Builder Header -->
    <div class="page-header">
        <div class="flex items-center gap-3" style="flex:1">
            <a href="<?php echo BASE_URL ?>/pages/instructor/dashboard.php" class="btn btn-ghost btn-icon"
                style="width:32px;height:32px">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
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
            <button class="btn btn-primary" id="save-btn" onclick="saveSession()">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7" />
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
                <div class="drop-zone" id="empty-drop" ondragover="canvasDragOver(event)"
                    ondrop="canvasDrop(event, -1)">
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
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

    <?php layout_end(); ?>