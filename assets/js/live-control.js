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

    // Volume fade-out state
    let _fadeInterval = null;   // setInterval handle for volume steps
    let _fadePauseTimeout = null; // setTimeout handle for the final pause call
    let _fadingOut = false;     // true while fade is in progress

    // Autoplay (continuous block playback)
    let _autoPlay = true;        // mirrors server-side autoPlay flag

    // Stickman (mini, instructor panel)
    let stickMini = null;

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

        // Init mini stickman in instructor panel
        setTimeout(() => {
            const el = document.getElementById('stickman-mini');
            if (el && typeof StickmanWidget !== 'undefined') {
                stickMini = new StickmanWidget(el, { size: 'mini' });
                _updateStickman();
            }
        }, 300);

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

        // Block held: server paused after finishing a block (manual-advance mode)
        socket.on('session:block_held', ({ block }) => {
            const name = block?.name || 'Bloque';
            showToast(`▶ Listo para iniciar: ${name}`, 'info');
            // Flash the play button so the instructor notices
            const btn = document.getElementById('btn-play');
            if (btn) {
                btn.style.boxShadow = '0 0 0 0 rgba(0,245,212,0.7)';
                btn.animate([
                    { boxShadow: '0 0 0 0 rgba(0,245,212,0.8)' },
                    { boxShadow: '0 0 0 18px rgba(0,245,212,0)' },
                ], { duration: 600, iterations: 3 });
            }
        });

        // Block changed: Spotify is handled exclusively by applyTick's blockChanged
        // branch (which has all the right guards). This handler is a no-op for Spotify
        // — it only exists so the server can emit other notifications (block_held).
        socket.on('session:block_change', () => { /* Spotify handled in applyTick */ });

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

        // Sync autoplay switch if server state differs
        if (tick.auto_play !== undefined && tick.auto_play !== _autoPlay) {
            _autoPlay = tick.auto_play;
            _syncAutoPlaySwitch();
        }

        // Sync WOD overlay button with server state (fixes reconnect desync)
        if (tick.wod_overlay !== undefined) {
            const serverWod = !!tick.wod_overlay.active;
            if (typeof window._wodOverlayActive !== 'undefined' && window._wodOverlayActive !== serverWod) {
                window._wodOverlayActive = serverWod;
                const btn = document.getElementById('btn-wod-overlay');
                if (btn) {
                    btn.style.background = serverWod ? 'var(--gf-accent)' : '';
                    btn.style.color = serverWod ? '#000' : '';
                    btn.style.borderColor = serverWod ? 'var(--gf-accent)' : '';
                }
            }
        }

        // Sync Clock Mode button with server state
        if (tick.clock_mode !== undefined) {
            const serverClock = !!tick.clock_mode.active;
            if (typeof window._clockModeActive !== 'undefined' && window._clockModeActive !== serverClock) {
                window._clockModeActive = serverClock;
                _syncClockBtn(serverClock);
            }
        }

        if (tick.status === 'finished') {
            // Stop music when session ends — must happen before isPlaying turns false,
            // otherwise the if(isPlaying) guard below would skip it.
            if (_lastAutoPlayUri) spotifyFadeAndPause(2);
            showToast('Sesión finalizada', 'info');
        }

        updateBlockDisplay();
        updateControls();
        renderClock();

        // Stickman sync
        _updateStickman();

        // Spotify: on block change, play new track or stop if no track assigned
        if (blockChanged) {
            _cancelFade();
            clearTimeout(GFLive._blockPauseTimer);  // cancel any deferred pause from prior tick
            if (isPlaying) {
                const b = blocks[currentIdx];
                if (b?.spotify_uri) {
                    spotifySetVolume(100);
                    autoPlayBlockSpotify(b);
                } else {
                    // New block has no track — fade out and stop
                    _lastAutoPlayUri = null;
                    spotifyFadeAndPause(2);
                }
            } else {
                // Session paused on block change (manual mode).
                // ⚠️ Race guard: if the instructor hits Play immediately after selecting
                // a block, the goto tick arrives with status=paused BEFORE the play tick.
                // Pausing Spotify immediately would cause a brief cut-then-restart glitch.
                // We defer 600 ms — if the session is still not playing by then, fade out.
                if (_lastAutoPlayUri) {
                    clearTimeout(GFLive._blockPauseTimer);
                    GFLive._blockPauseTimer = setTimeout(() => {
                        if (!isPlaying) spotifyFadeAndPause(2);
                    }, 800);
                }
            }
        } else if (_lastAutoPlayUri && isPlaying && !_fadingOut) {
            // Block-end fade: use server ticks (≈1Hz) as the fade timer.
            // In the last 3 seconds, reduce volume proportionally — 1 API call/tick,
            // 3 calls total. No setInterval needed, no rate-limit risk.
            const b = blocks[currentIdx];
            const dur = b ? blockDuration(b) : 0;
            const remaining = dur > 0 ? Math.max(0, dur - elapsed) : 999;
            if (remaining > 0 && remaining <= 3) {
                // remaining=3→vol67, remaining=2→vol33, remaining=1→vol0
                const vol = Math.round(((remaining - 1) / 3) * 100);
                spotifySetVolume(Math.max(0, vol));
            }
        }
        // Hook for external consumers (e.g. clock controller overlay)
        if (typeof window._onLiveTick === 'function') window._onLiveTick(tick);
    }

    function _updateStickman() {
        if (!stickMini) return;
        const b = blocks[currentIdx];
        if (!b) return;
        const exs = b.exercises || [];
        let exIdx = 0;
        if ((b.type === 'tabata' || b.type === 'interval') && exs.length > 1) {
            const cfg = b.config || {};
            const rd = (cfg.work || 20) + (cfg.rest || 10);
            exIdx = Math.floor(elapsed / rd) % exs.length;
        }
        const exObj = exs[exIdx];
        const exName = (exObj && exObj.name) ? exObj.name : (typeof exObj === 'string' ? exObj : (b.name || ''));
        let smPhase = 'work';
        if (b.type === 'rest') smPhase = 'rest';
        else if (b.type === 'tabata' || b.type === 'interval') {
            var cfg2 = b.config || {};
            var cycleSec2 = (cfg2.work || 20) + (cfg2.rest || 10);
            smPhase = (elapsed % cycleSec2) < (cfg2.work || 20) ? 'work' : 'rest';
        }
        stickMini.update(exName, smPhase);
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
        // NOTE: Spotify auto-play is handled by applyTick's blockChanged branch.
        // Do NOT call autoPlayBlockSpotify here — it fires on every tick and
        // causes double-play when a block changes.
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
            // PAUSE — fade out then pause
            prepRemaining = 0;
            spotifyFadeAndPause(2);
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
                        .then((res) => {
                            if (typeof spCheckStatus === 'function') spCheckStatus(res, 'resume');
                            if (typeof spRefreshNow === 'function') setTimeout(spRefreshNow, 1200);
                        })
                        .catch(() => { });
                }
            } else {
                // FRESH START
                // Priority: block preset (spotify_intro) > global widget (_gfPrepTime)
                _lastAutoPlayUri = null;
                const blockPrep = (b?.spotify_intro > 0) ? (b.spotify_intro | 0) : null;
                const prep = blockPrep !== null ? blockPrep : (window._gfPrepTime || 0);
                emit('control:play', { prep_remaining: prep });
                autoPlayBlockSpotify(b);
            }
        }
        updateControls();
    }

    async function skipForward() {
        const prevBlock = blocks[currentIdx];
        emit('control:skip');
        // Spotify: if next block has no track, fade out and stop
        const nextBlock = blocks[currentIdx + 1];
        if (prevBlock?.spotify_uri && !nextBlock?.spotify_uri) {
            _cancelFade();
            spotifyFadeAndPause(2);
        }
    }

    async function stopSession() {
        _cancelFade();
        spotifyFadeAndPause(3);
        emit('control:stop');
        showToast('Sesión finalizada', 'success');
        if (session) session.status = 'finished';
        updateControls();
    }

    async function jumpToBlock(idx) {
        const b = blocks[idx];
        // Priority: block preset (spotify_intro) > global widget (_gfPrepTime)
        const blockPrep = (b?.spotify_intro > 0) ? (b.spotify_intro | 0) : null;
        const prep = blockPrep !== null ? blockPrep : (window._gfPrepTime || 0);
        emit('control:goto', { index: idx, prep_remaining: prep });

        // In manual mode, briefly disable Play so a rapid click can't race the goto tick
        if (!_autoPlay) {
            const btn = document.getElementById('btn-play');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('preparing');
                setTimeout(() => {
                    btn.disabled = false;
                    btn.classList.remove('preparing');
                }, 3000);
            }
        }
    }

    async function setSala(salaId) {
        // set_sala is still a PHP operation (not real-time)
        const salaIdVal = (salaId && salaId != '0') ? parseInt(salaId) : null;
        try {
            await GF.post(`${window.GF_BASE}/api/sessions.php?id=${session.id}&action=set_sala`, { sala_id: salaIdVal });
            session.sala_id = salaIdVal;
            // Reconnect socket with new sala
            if (socket?.connected) {
                socket.emit('join:session', { session_id: session.id, sala_id: salaIdVal });
            }
            if (!salaIdVal) {
                showToast('Sala desacoplada', 'info');
                const displayLink = document.querySelector('a[href*="/display/"]');
                if (displayLink) displayLink.style.display = 'none';
            } else {
                const salaName = document.getElementById('live-sala-select')?.selectedOptions[0]?.text || '';
                showToast(`Sala asignada: ${salaName}`, 'success');
                const salas = window.SALAS || [];
                const sala = salas.find(s => s.id == salaId);
                if (sala) {
                    const displayLink = document.querySelector('a[href*="/display/"]');
                    if (displayLink) { displayLink.href = `${window.GF_BASE}/pages/display/sala.php?code=${sala.display_code}`; displayLink.style.display = ''; }
                }
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
        spotifyRepeat('off'); // clear repeat before stopping
        GF.post(window.GF_BASE + '/api/spotify.php?action=pause', {}).catch(() => { });
    }
    function spotifySetVolume(vol) {
        GF.post(window.GF_BASE + '/api/spotify.php?action=volume&vol=' + vol, {}).catch(() => { });
    }
    function spotifyRepeat(state) {
        // state: 'off' | 'track' | 'context'
        GF.post(window.GF_BASE + '/api/spotify.php?action=repeat&state=' + state, {}).catch(() => { });
    }

    // ── Volume Fade ──────────────────────────────────────────────────────────
    // Spotify's API has NO native fade parameter — you can only set an instant volume level.
    // To avoid rate-limit bans, we use the minimum possible calls:
    //   1 × setVolume(0)  → silence immediately
    //   1 × pause         → stop playback after `durationSec` ms
    //   1 × setVolume(100) → restore for the next track
    // The "fade" is instant-to-zero, but inaudible since silence precedes the pause.
    function spotifyFadeAndPause(durationSec = 2) {
        if (_fadingOut) return;
        _fadingOut = true;

        spotifySetVolume(0); // ← single volume API call
        spotifyRepeat('off'); // clear repeat so next block starts fresh

        _fadePauseTimeout = setTimeout(() => {
            _fadePauseTimeout = null;
            _fadingOut = false;
            spotifyPause();
            setTimeout(() => spotifySetVolume(100), 500); // restore for next track
        }, durationSec * 1000);
    }

    function _cancelFade() {
        if (_fadeInterval) {
            clearInterval(_fadeInterval);
            _fadeInterval = null;
            spotifySetVolume(100); // Restore immediately so next track starts at full volume
        }
        if (_fadePauseTimeout) {
            clearTimeout(_fadePauseTimeout);
            _fadePauseTimeout = null;
        }
        _fadingOut = false;
    }
    async function autoPlayBlockSpotify(block) {
        if (!block?.spotify_uri) return;
        // NOTE: do NOT check isPlaying here — when called from togglePlay's FRESH START
        // path, the server tick hasn't arrived yet so isPlaying is still false.
        // Callsites that care (applyTick) already guard with their own isPlaying check.
        if (block.spotify_uri === _lastAutoPlayUri) return;
        _lastAutoPlayUri = block.spotify_uri;
        try {
            spotifySetVolume(100); // always restore volume before playing
            const isPlaylist = block.spotify_uri.startsWith('spotify:playlist:') || block.spotify_uri.startsWith('spotify:album:');
            const body = isPlaylist ? { context_uri: block.spotify_uri } : { uris: [block.spotify_uri] };
            const res = await GF.post(window.GF_BASE + '/api/spotify.php?action=play', body);
            if (typeof spCheckStatus === 'function') spCheckStatus(res, 'auto-play');
            if (typeof spRefreshNow === 'function') spRefreshNow();
            // For single tracks: enable repeat=track so the song loops until the block ends.
            // For playlists/albums: context_uri auto-advances naturally — no repeat needed.
            if (!isPlaylist) spotifyRepeat('track');
        } catch (e) { /* Spotify not available */ }
    }

    function blockTypeColor(type) {
        const colors = { interval: '#00f5d4', tabata: '#ff6b35', amrap: '#7c3aed', emom: '#0ea5e9', fortime: '#f59e0b', series: '#ec4899', circuit: '#10b981', rest: '#3d5afe', briefing: '#6b7280' };
        return colors[type] || '#888';
    }


    async function skipBackward() {
        emit('control:prev');
    }

    // ── Autoplay toggle ───────────────────────────────────────────────────────
    function setAutoPlay(enabled) {
        _autoPlay = !!enabled;
        emit('control:set_autoplay', { enabled: _autoPlay });
        // Persist preference across page reloads (per-browser)
        localStorage.setItem('gf_autoplay', _autoPlay ? '1' : '0');
        _syncAutoPlaySwitch();
    }

    function _syncAutoPlaySwitch() {
        const sw = document.getElementById('autoplay-switch');
        if (sw && sw.checked !== _autoPlay) sw.checked = _autoPlay;
        const lbl = document.getElementById('autoplay-label');
        if (lbl) lbl.textContent = _autoPlay ? 'Continuo' : 'Manual';
    }

    function getSocket() { return socket; }

    function emitWodOverlay(active, wodBlocks) {
        if (socket?.connected) {
            socket.emit('control:wod_overlay', { active, blocks: active ? (wodBlocks || blocks) : [] });
        }
    }

    /** Toggle or configure the display clock panel.
     *  @param {boolean} active  - whether to show the clock
     *  @param {string}  mode    - 'session' (default) | 'countdown' | 'countup'
     *  @param {object}  config  - optional {work, rest, rounds, duration, ...}
     */
    function emitClockMode(active, mode = 'session', config = {}) {
        if (socket?.connected) {
            socket.emit('control:clock_mode', { active, mode, config });
        }
    }

    function emitClockFs(active) {
        if (socket?.connected) {
            socket.emit('control:clock_fs', { active: !!active });
        }
    }

    function clockTimerPlay() {
        if (socket?.connected) socket.emit('control:clock_timer_play');
    }
    function clockTimerStop() {
        if (socket?.connected) socket.emit('control:clock_timer_stop');
    }
    function clockTimerReset() {
        if (socket?.connected) socket.emit('control:clock_timer_reset');
    }
    function clockTimerCfg(mode, duration, prep, work, rest, rounds) {
        if (socket?.connected) socket.emit('control:clock_timer_cfg', { mode, duration, prep, work, rest, rounds });
    }

    function _syncClockBtn(active) {
        const btn = document.getElementById('btn-clock-mode');
        if (!btn) return;
        btn.style.background = active ? 'var(--gf-accent)' : '';
        btn.style.color = active ? '#000' : '';
        btn.style.borderColor = active ? 'var(--gf-accent)' : '';
        btn.title = active ? 'Ocultar reloj en pantalla' : 'Mostrar reloj en pantalla';
    }

    return { init, togglePlay, skipForward, skipBackward, stopSession, jumpToBlock, setSala, setAutoPlay, getSocket, emitWodOverlay, emitClockMode, emitClockFs, clockTimerPlay, clockTimerStop, clockTimerReset, clockTimerCfg };
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
window.setAutoPlay = enabled => GFLive.setAutoPlay(enabled);
