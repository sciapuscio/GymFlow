// GymFlow — Live Control Engine (Socket.IO version)
// The clock lives on the server. This module only sends commands and renders ticks.
const GFLive = (() => {
    let session = null;
    let blocks = [];
    let socket = null;

    // Local state (driven by server ticks)
    let currentIdx = 0;
    let elapsed = 0;
    let isPlaying = false;
    let prepRemaining = 0;
    let _lastBlockIdx = -1;

    // Spotify
    let _lastAutoPlayUri = null;

    // ── Init ─────────────────────────────────────────────────────────────────
    function init(data) {
        session = data;
        blocks = data.blocks || [];
        currentIdx = data.current_block_index || 0;
        elapsed = data.current_block_elapsed || 0;
        isPlaying = data.status === 'playing';

        renderBlockList();
        updateBlockDisplay();
        updateControls();

        connectSocket();
    }

    // ── Socket.IO Connection ─────────────────────────────────────────────────
    function connectSocket() {
        const url = window.GF_SOCKET_URL || 'http://localhost:3001';
        socket = io(url, { transports: ['websocket', 'polling'] });

        socket.on('connect', () => {
            console.log('[GFLive] Socket connected:', socket.id);
            socket.emit('join:session', {
                session_id: session.id,
                sala_id: session.sala_id,
            });
        });

        socket.on('session:state', (tick) => applyTick(tick));
        socket.on('session:tick', (tick) => applyTick(tick));

        // Block changed (server auto-advance): handle Spotify
        socket.on('session:block_change', ({ block }) => {
            if (block?.spotify_uri) {
                autoPlayBlockSpotify(block);
            } else {
                // New block has no track (Rest, Briefing, etc.) — always stop
                _lastAutoPlayUri = null;
                spotifyPause();
            }
        });

        socket.on('error', (msg) => {
            console.error('[GFLive] Socket error:', msg);
            showToast('Error de sincronización: ' + msg, 'error');
        });

        socket.on('disconnect', () => {
            console.warn('[GFLive] Socket disconnected — reconnecting...');
        });
    }

    // ── Apply Server Tick ────────────────────────────────────────────────────
    function applyTick(tick) {
        const prevIdx = currentIdx;
        const prevStatus = isPlaying;

        currentIdx = tick.current_block_index || 0;
        elapsed = tick.elapsed || 0;
        prepRemaining = tick.prep_remaining || 0;
        isPlaying = tick.status === 'playing';

        if (session) session.status = tick.status;

        const blockChanged = currentIdx !== prevIdx;

        if (tick.status === 'finished') {
            showToast('Sesión finalizada', 'info');
        }

        updateBlockDisplay();
        updateControls();
        renderClock();

        // Spotify: on block change, play new track or stop if no track assigned
        if (blockChanged && isPlaying) {
            const b = blocks[currentIdx];
            if (b?.spotify_uri) {
                autoPlayBlockSpotify(b);
            } else {
                // New block has no track — always stop, regardless of how music started
                _lastAutoPlayUri = null;
                spotifyPause();
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    function currentBlock() { return blocks[currentIdx] || null; }

    function blockDuration(block) {
        if (!block) return 300;
        return computeBlockDuration(block);
    }

    // ── Render ───────────────────────────────────────────────────────────────
    function renderClock() {
        const b = currentBlock();
        if (!b) return;
        const dur = blockDuration(b);
        const remaining = Math.max(0, dur - elapsed);

        const clockEl = document.getElementById('live-clock');
        if (clockEl) clockEl.textContent = prepRemaining > 0 ? `PREP ${prepRemaining}s` : formatDuration(remaining);

        const pct = dur > 0 ? Math.min(100, (elapsed / dur) * 100) : 0;
        const bar = document.getElementById('live-block-progress');
        if (bar) bar.style.width = pct + '%';

        const doneBlocks = blocks.slice(0, currentIdx).reduce((s, b) => s + blockDuration(b), 0);
        const totalDur = blocks.reduce((s, b) => s + blockDuration(b), 0);
        const totalPct = totalDur > 0 ? Math.min(100, ((doneBlocks + elapsed) / totalDur) * 100) : 0;
        const totalBar = document.getElementById('live-total-progress');
        if (totalBar) totalBar.style.width = totalPct + '%';

        const statusEl = document.getElementById('status-label');
        if (statusEl) statusEl.textContent = isPlaying ? 'En Vivo' : (session?.status === 'paused' ? 'Pausado' : 'Listo');

        const infoEl = document.getElementById('live-block-info');
        if (infoEl && (b.type === 'interval' || b.type === 'tabata')) {
            const cfg = b.config || {};
            const workSecs = cfg.work || 40;
            const restSecs = cfg.rest || (b.type === 'tabata' ? 10 : 20);
            const roundDur = workSecs + restSecs;
            const roundMax = cfg.rounds || 8;
            const roundNum = Math.min(Math.floor(elapsed / roundDur) + 1, roundMax);
            const phaseEl = elapsed % roundDur;
            const inWork = phaseEl < workSecs;
            infoEl.textContent = `Ronda ${roundNum} / ${roundMax} — ${inWork ? 'WORK' : 'REST'}`;
        } else if (infoEl) {
            infoEl.textContent = '';
        }
    }

    function updateBlockDisplay() {
        const b = currentBlock();
        if (!b) return;

        const typeEl = document.getElementById('live-block-type');
        if (typeEl) {
            typeEl.textContent = (b.type || '').toUpperCase();
            const col = blockTypeColor(b.type);
            typeEl.style.backgroundColor = col + '26';
            typeEl.style.color = col;
        }
        const nameEl = document.getElementById('live-block-name');
        if (nameEl) nameEl.textContent = b.name || 'Bloque';

        const exEl = document.getElementById('live-exercise-name');
        if (exEl) {
            const exs = b.exercises || [];
            exEl.textContent = exs.length ? exs.map(e => e.name || e).join(' · ') : '—';
        }

        renderBlockList();

        const pt = document.getElementById('progress-text');
        if (pt) pt.textContent = `${currentIdx + 1} / ${blocks.length} bloques`;

        renderClock();
        autoPlayBlockSpotify(b);
    }

    function renderBlockList() {
        document.querySelectorAll('.block-list-item').forEach((el, i) => {
            el.classList.toggle('current', i === currentIdx);
            el.classList.toggle('done', i < currentIdx);
        });
    }

    function updateControls() {
        const btn = document.getElementById('btn-play');
        if (!btn) return;
        if (isPlaying) {
            btn.textContent = '⏸';
            btn.style.background = '#ff9800';
        } else {
            btn.textContent = '▶';
            btn.style.background = 'var(--gf-accent)';
        }
    }

    // ── Control Emitters ─────────────────────────────────────────────────────
    function emit(event, data = {}) {
        if (!socket?.connected) {
            showToast('Sin conexión al servidor de sync', 'error');
            return;
        }
        socket.emit(event, data);
    }

    async function togglePlay() {
        if (!session) return;

        if (isPlaying) {
            // PAUSE
            prepRemaining = 0;
            spotifyPause();
            emit('control:pause');
        } else {
            const b = currentBlock();
            const isResume = elapsed > 0 && _lastAutoPlayUri;

            if (isResume) {
                // RESUME: seek Spotify to exact position
                const introSecs = (b?.spotify_intro | 0) || 0;
                const positionMs = (introSecs + elapsed) * 1000;
                emit('control:play', { prep_remaining: 0 });
                if (b?.spotify_uri) {
                    const isCtx = b.spotify_uri.includes(':playlist:') || b.spotify_uri.includes(':album:');
                    const body = isCtx
                        ? { context_uri: b.spotify_uri, position_ms: positionMs }
                        : { uris: [b.spotify_uri], position_ms: positionMs };
                    GF.post(window.GF_BASE + '/api/spotify.php?action=play', body)
                        .then(() => { if (typeof spRefreshNow === 'function') setTimeout(spRefreshNow, 1200); })
                        .catch(() => { });
                }
            } else {
                // FRESH START
                _lastAutoPlayUri = null;
                const prep = (b?.spotify_intro > 0 && b?.spotify_uri) ? (b.spotify_intro | 0) : 0;
                emit('control:play', { prep_remaining: prep });
                autoPlayBlockSpotify(b);
            }
        }
        updateControls();
    }

    async function skipForward() {
        const prevBlock = blocks[currentIdx];
        emit('control:skip');
        // Spotify: if next block has no track, stop music
        const nextBlock = blocks[currentIdx + 1];
        if (prevBlock?.spotify_uri && !nextBlock?.spotify_uri) spotifyAutoPause();
    }

    async function stopSession() {
        spotifyAutoPause();
        emit('control:stop');
        showToast('Sesión finalizada', 'success');
        if (session) session.status = 'finished';
        updateControls();
    }

    async function jumpToBlock(idx) {
        emit('control:goto', { index: idx });
    }

    async function setSala(salaId) {
        // set_sala is still a PHP operation (not real-time)
        try {
            await GF.post(`${window.GF_BASE}/api/sessions.php?id=${session.id}&action=set_sala`, { sala_id: parseInt(salaId) });
            session.sala_id = parseInt(salaId);
            // Reconnect socket with new sala
            if (socket?.connected) {
                socket.emit('join:session', { session_id: session.id, sala_id: parseInt(salaId) });
            }
            const salaName = document.getElementById('live-sala-select')?.selectedOptions[0]?.text || '';
            showToast(`Sala asignada: ${salaName}`, 'success');
            const salas = window.SALAS || [];
            const sala = salas.find(s => s.id == salaId);
            if (sala) {
                const displayLink = document.querySelector('a[href*="/display/"]');
                if (displayLink) { displayLink.href = `${window.GF_BASE}/pages/display/sala.php?code=${sala.display_code}`; displayLink.style.display = ''; }
            }
        } catch (e) { showToast('Error al asignar sala', 'error'); }
    }

    // ── Spotify Helpers ──────────────────────────────────────────────────────
    function spotifyPause() {
        GF.post(window.GF_BASE + '/api/spotify.php?action=pause', {}).catch(() => { });
    }
    function spotifyAutoPause() {
        if (!_lastAutoPlayUri) return;
        _lastAutoPlayUri = null;
        GF.post(window.GF_BASE + '/api/spotify.php?action=pause', {}).catch(() => { });
    }
    async function autoPlayBlockSpotify(block) {
        if (!block?.spotify_uri) return;
        if (!isPlaying) return;
        if (block.spotify_uri === _lastAutoPlayUri) return;
        _lastAutoPlayUri = block.spotify_uri;
        try {
            const isPlaylist = block.spotify_uri.startsWith('spotify:playlist:') || block.spotify_uri.startsWith('spotify:album:');
            const body = isPlaylist ? { context_uri: block.spotify_uri } : { uris: [block.spotify_uri] };
            await GF.post(window.GF_BASE + '/api/spotify.php?action=play', body);
            if (typeof spRefreshNow === 'function') spRefreshNow();
        } catch (e) { /* Spotify not available */ }
    }

    function blockTypeColor(type) {
        const colors = { interval: '#00f5d4', tabata: '#ff6b35', amrap: '#7c3aed', emom: '#0ea5e9', fortime: '#f59e0b', series: '#ec4899', circuit: '#10b981', rest: '#3d5afe', briefing: '#6b7280' };
        return colors[type] || '#888';
    }

    async function skipBackward() {
        emit('control:prev');
    }

    function getSocket() { return socket; }

    return { init, togglePlay, skipForward, skipBackward, stopSession, jumpToBlock, setSala, getSocket };
})();

// Expose globals used inline by live.php buttons
window.liveControl = (action, data) => {
    const sock = GFLive.getSocket();
    if (action === 'skip') return GFLive.skipForward();
    if (action === 'prev') return GFLive.skipBackward();
    if (action === 'stop') return GFLive.stopSession();
    if (action === 'extend') { if (sock?.connected) sock.emit('control:extend', data); return; }
    // Fallback: PHP API for non-live ops (set_sala etc.)
    GF.post(`${window.GF_BASE}/api/sessions.php?id=${SESSION_DATA.id}&action=${action}`, data || {}).then(r => r && showToast('OK', 'success'));
};
window.togglePlay = () => GFLive.togglePlay();
window.jumpToBlock = idx => GFLive.jumpToBlock(idx);
window.setSala = id => GFLive.setSala(id);
