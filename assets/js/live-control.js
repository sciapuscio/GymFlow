// GymFlow — Live Control Engine
const GFLive = (() => {
    let session = null;
    let blocks = [];
    let currentIdx = 0;
    let elapsed = 0;
    let isPlaying = false;
    let ticker = null;
    let currentPhase = 'work'; // work | rest
    let phaseElapsed = 0;

    function init(data) {
        session = data;
        blocks = data.blocks || [];
        currentIdx = data.current_block_index || 0;
        elapsed = data.current_block_elapsed || 0;
        isPlaying = data.status === 'playing';

        renderBlockList();
        updateBlockDisplay();
        updateControls();

        if (isPlaying) startTicker();
    }

    function currentBlock() { return blocks[currentIdx] || null; }

    function blockDuration(block) {
        if (!block) return 300;
        return computeBlockDuration(block);
    }

    let _elapsedTick = 0;
    function startTicker() {
        if (ticker) clearInterval(ticker);
        isPlaying = true;
        ticker = setInterval(() => {
            elapsed++;
            phaseElapsed++;
            _elapsedTick++;
            renderClock();
            // Push elapsed to server every 3s so display stays in sync
            if (_elapsedTick % 3 === 0) sendElapsedUpdate();

            const b = currentBlock();
            if (!b) return;
            const dur = blockDuration(b);

            if (elapsed >= dur) {
                // Auto advance
                if (currentIdx < blocks.length - 1) {
                    skipForward();
                } else {
                    stopSession();
                }
            }
        }, 1000);
    }

    function stopTicker() {
        if (ticker) { clearInterval(ticker); ticker = null; }
        isPlaying = false;
    }

    function renderClock() {
        const b = currentBlock();
        if (!b) return;
        const dur = blockDuration(b);
        const remaining = Math.max(0, dur - elapsed);

        const clockEl = document.getElementById('live-clock');
        if (clockEl) clockEl.textContent = formatDuration(remaining);

        // Block progress bar
        const pct = dur > 0 ? Math.min(100, (elapsed / dur) * 100) : 0;
        const bar = document.getElementById('live-block-progress');
        if (bar) bar.style.width = pct + '%';

        // Total progress
        const doneBlocks = blocks.slice(0, currentIdx).reduce((s, b) => s + blockDuration(b), 0);
        const totalDur = blocks.reduce((s, b) => s + blockDuration(b), 0);
        const totalPct = totalDur > 0 ? Math.min(100, ((doneBlocks + elapsed) / totalDur) * 100) : 0;
        const totalBar = document.getElementById('live-total-progress');
        if (totalBar) totalBar.style.width = totalPct + '%';

        // Status label
        const statusEl = document.getElementById('status-label');
        if (statusEl) {
            const status = isPlaying ? (session.status === 'paused' ? 'Pausado' : 'En Vivo') : 'Listo';
            statusEl.textContent = status;
        }

        // Block info
        const infoEl = document.getElementById('live-block-info');
        if (infoEl) {
            const b2 = currentBlock();
            if (b2?.type === 'interval' || b2?.type === 'tabata') {
                const cfg = b2.config || {};
                const roundDur = (cfg.work || 40) + (cfg.rest || 20);
                const roundNum = Math.floor(elapsed / roundDur) + 1;
                const roundMax = cfg.rounds || 8;
                const inWork = (elapsed % roundDur) < (cfg.work || 40);
                if (infoEl) infoEl.textContent = `Ronda ${Math.min(roundNum, roundMax)} / ${roundMax} — ${inWork ? 'WORK' : 'REST'}`;
            }
        }
    }

    function updateBlockDisplay() {
        const b = currentBlock();
        if (!b) return;

        const typeEl = document.getElementById('live-block-type');
        if (typeEl) { typeEl.textContent = (b.type || '').toUpperCase(); typeEl.style.backgroundColor = blockTypeColor(b.type) + '26'; typeEl.style.color = blockTypeColor(b.type); }

        const nameEl = document.getElementById('live-block-name');
        if (nameEl) nameEl.textContent = b.name || 'Bloque';

        const exEl = document.getElementById('live-exercise-name');
        if (exEl) {
            const exs = b.exercises || [];
            exEl.textContent = exs.length ? exs.map(e => e.name || e).join(' · ') : '—';
        }

        // Block list highlighting
        renderBlockList();

        // Progress text
        const pt = document.getElementById('progress-text');
        if (pt) pt.textContent = `${currentIdx + 1} / ${blocks.length} bloques`;

        renderClock();

        // Auto-play Spotify track assigned to this block
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
            btn.style.color = '#0a0a0f';
        }
    }

    async function liveControl(action, data = {}) {
        try {
            const resp = await GF.post(`${window.GF_BASE}/api/sessions.php?id=${session.id}&action=${action}`, data);
            if (resp?.success) {
                if (resp.state) syncState(resp.state);
            }
        } catch (e) { showToast('Error de control: ' + e.message, 'error'); }
    }

    function syncState(state) {
        currentIdx = state.current_block_index || 0;
        const newStatus = state.status;
        if (newStatus === 'playing' && !isPlaying) startTicker();
        if (newStatus === 'paused' && isPlaying) stopTicker();
        if (newStatus === 'finished') { stopTicker(); showToast('Sesión terminada', 'info'); }
        session.status = newStatus;
        updateBlockDisplay();
        updateControls();
    }

    // Throttled: fires every 3 seconds to keep display in sync without flooding the server
    async function sendElapsedUpdate() {
        if (!session?.id || !isPlaying) return;
        try {
            await GF.post(`${window.GF_BASE}/api/sessions.php?id=${session.id}&action=update_elapsed`, { elapsed });
        } catch (e) { /* ignore — non-critical */ }
    }

    async function togglePlay() {
        if (isPlaying) {
            stopTicker();
            await liveControl('pause');
        } else {
            // Reset so Spotify fires for current block on resume/play
            _lastAutoPlayUri = null;
            startTicker();
            await liveControl('play');
            // Trigger Spotify for the currently active block
            autoPlayBlockSpotify(currentBlock());
        }
        updateControls();
    }

    async function skipForward() {
        stopTicker();
        elapsed = 0;
        currentIdx = Math.min(currentIdx + 1, blocks.length - 1);
        await liveControl('skip');
        if (session.status !== 'finished') startTicker();
        updateBlockDisplay();
    }

    async function stopSession() {
        stopTicker();
        await liveControl('stop');
        showToast('Sesión finalizada', 'success');
        session.status = 'finished';
        updateControls();
    }

    async function jumpToBlock(idx) {
        stopTicker();
        currentIdx = idx;
        elapsed = 0;
        await liveControl('goto', { index: idx });
        if (isPlaying) startTicker();
        updateBlockDisplay();
    }

    async function setSala(salaId) {
        await liveControl('set_sala', { sala_id: parseInt(salaId) });
        const salaName = document.getElementById('live-sala-select')?.selectedOptions[0]?.text || '';
        showToast(`Sala asignada: ${salaName}`, 'success');
        // Update display link
        const salas = window.SALAS || [];
        const sala = salas.find(s => s.id == salaId);
        if (sala) {
            const displayLink = document.querySelector('a[href*="/display/"]');
            if (displayLink) { displayLink.href = `${window.GF_BASE}/pages/display/sala.php?code=${sala.display_code}`; displayLink.style.display = ''; }
        }
    }


    function blockTypeColor(type) {
        const colors = { interval: '#00f5d4', tabata: '#ff6b35', amrap: '#7c3aed', emom: '#0ea5e9', fortime: '#f59e0b', series: '#ec4899', circuit: '#10b981', rest: '#3d5afe', briefing: '#6b7280' };
        return colors[type] || '#888';
    }

    let _lastAutoPlayUri = null;
    async function autoPlayBlockSpotify(block) {
        if (!block?.spotify_uri) return;
        if (!isPlaying) return;                          // don't fire on init/load
        if (block.spotify_uri === _lastAutoPlayUri) return; // already playing this URI
        _lastAutoPlayUri = block.spotify_uri;
        try {
            const isPlaylist = block.spotify_uri.startsWith('spotify:playlist:') || block.spotify_uri.startsWith('spotify:album:');
            const body = isPlaylist ? { context_uri: block.spotify_uri } : { uris: [block.spotify_uri] };
            await GF.post(window.GF_BASE + '/api/spotify.php?action=play', body);
            // Refresh the now-playing widget quickly after play fires
            if (typeof spRefreshNow === 'function') spRefreshNow();
        } catch (e) { /* Spotify not available, silently ignore */ }
    }

    return { init, togglePlay, skipForward, stopSession, jumpToBlock, setSala };
})();

// Expose globals used inline
window.liveControl = (action, data) => GFLive[action] ? GFLive[action](data) : GF.post(`${window.GF_BASE}/api/sessions.php?id=${SESSION_DATA.id}&action=${action}`, data || {}).then(r => r && showToast('OK', 'success'));
window.togglePlay = () => GFLive.togglePlay();
window.jumpToBlock = idx => GFLive.jumpToBlock(idx);
window.setSala = id => GFLive.setSala(id);
