// GymFlow — Display Sync Engine (Socket.IO Client)
(function () {
    let currentState = null;
    let localElapsed = 0;  // filled by server ticks — no local ticker needed
    let stickWidget = null;  // StickmanWidget instance
    let _previewBlock = null; // set by session:block_change while paused
    let _hasSession = false;  // true once first session:state arrives
    let _pollerTimer = null;  // presence poll interval handle
    let _finishedTimer = null; // auto-return-to-waiting timer after finished

    // Sub-second interpolation for the clock panel only.
    // The server sends ticks at ~1Hz. We animate the clock at 60fps by
    // tracking how many ms have passed since the last tick arrived.
    let _lastTickAt = 0;      // performance.now() when last tick arrived
    let _rafId = null;        // requestAnimationFrame handle
    let _clockActive = false; // mirrors clock_mode.active for rAF guard

    // Beep state tracking
    let _lastClockSec = -1;   // last integer second shown on clock (for 3-2-1 pips)
    let _lastClockPhase = ''; // last phase label — detects work↔rest transitions
    let _audioCtx = null;     // Web Audio context (created on first use)

    function init() {
        connectSocket();
        startPresencePoller();
        // Init stickman widget (deferred so scripts load first)
        setTimeout(() => {
            const el = document.getElementById('stickman-container');
            if (el && typeof StickmanWidget !== 'undefined') {
                stickWidget = new StickmanWidget(el, { size: 'normal' });
            }
        }, 200);

        // Unlock Web Audio on first gesture (browser autoplay policy)
        const _unlockAudio = () => {
            _getAudioCtx();
            document.removeEventListener('click', _unlockAudio);
            document.removeEventListener('touchstart', _unlockAudio);
        };
        document.addEventListener('click', _unlockAudio, { once: true });
        document.addEventListener('touchstart', _unlockAudio, { once: true });
    }

    // ── Waiting screen management ─────────────────────────────────────────────
    function _hideWaitingScreen() {
        const ws = document.getElementById('waiting-screen');
        if (ws) ws.style.display = 'none';
    }
    function _showWaitingScreen() {
        const ws = document.getElementById('waiting-screen');
        if (ws) ws.style.display = 'flex';
        // Also ensure idle/live/finished screens are hidden
        const ids = ['idle-screen', 'live-screen', 'finished-screen'];
        ids.forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
        const po = document.getElementById('paused-overlay');
        if (po) po.classList.remove('visible');
        document.body.className = 'state-idle'; // reset body classes
    }

    // ── Presence poller (fallback when socket misses coupling event) ──────────
    // Polls GET /api/salas.php?code=X every 20s while no session received.
    // When a session is attached, reloads the page so socket joins the right room.
    function startPresencePoller() {
        if (!window.DISPLAY_CODE && typeof DISPLAY_CODE === 'undefined') return;
        const code = typeof DISPLAY_CODE !== 'undefined' ? DISPLAY_CODE : window.DISPLAY_CODE;
        if (!code) return;

        _pollerTimer = setInterval(async () => {
            if (_hasSession) { clearInterval(_pollerTimer); return; }
            try {
                const r = await fetch(`${BASE}/api/salas.php?code=${encodeURIComponent(code)}`, { credentials: 'include' });
                if (!r.ok) return;
                const d = await r.json();
                if (d?.current_session_id) {
                    clearInterval(_pollerTimer);
                    location.reload();
                }
            } catch (e) { /* silently ignore network errors */ }
        }, 20000);
    }

    // ── Socket.IO Connection ─────────────────────────────────────────────────
    function connectSocket() {
        const url = window.GF_SOCKET_URL || 'http://localhost:3001';
        const socket = io(url, { transports: ['websocket', 'polling'] });

        socket.on('connect', () => {
            console.log('[DisplaySync] Socket connected:', socket.id);
            socket.emit('join:sala', { sala_id: SALA_ID });
        });

        socket.on('session:state', (tick) => applyState(tick));
        // Ticks only processed after we've received an initial session:state.
        // This prevents instructor-side broadcasts from flickering the waiting screen.
        socket.on('session:tick', (tick) => { if (_hasSession) applyState(tick); });

        // When instructor jumps to a block while paused → show "¡PREPARATE! [exercise]"
        // instead of leaving the PAUSA screen up. _previewBlock keeps this alive across ticks.
        // Guard: ignore if display hasn't received a session:state yet (still on waiting screen).
        socket.on('session:block_change', ({ block }) => {
            if (!block || !_hasSession) return;
            _previewBlock = block;
            _showPreviewOverlay(block);
        });

        socket.on('disconnect', () => {
            console.warn('[DisplaySync] Disconnected, reconnecting...');
        });

        // ── Sala desacoplada — el instructor desvinculó esta sala de la sesión ──
        // Volvemos al waiting screen y reiniciamos el presence poller.
        socket.on('session:detach', () => {
            console.log('[DisplaySync] session:detach — volviendo a waiting screen');
            _hasSession = false;
            currentState = null;
            _previewBlock = null;
            _clockActive = false;
            if (_rafId) { cancelAnimationFrame(_rafId); _rafId = null; }
            if (_finishedTimer) { clearTimeout(_finishedTimer); _finishedTimer = null; }
            _showWaitingScreen();
            startPresencePoller();
            document.dispatchEvent(new CustomEvent('gf:session:detach'));
        });

        // ── Sala renombrada por el admin — actualiza el nombre en pantalla ──
        socket.on('sala:renamed', ({ name }) => {
            console.log('[DisplaySync] sala:renamed →', name);
            ['sala-name-waiting', 'sala-name-idle', 'sala-name-live'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = name;
            });
        });

        // ── WOD Summary Overlay ──────────────────────────────────────────────
        socket.on('display:wod_overlay', ({ active, blocks }) => {
            _renderWodOverlay(active, blocks);
        });

        // ── Fullscreen clock command from instructor ──────────────────────────
        socket.on('clock:fs', ({ active }) => {
            document.body.classList.toggle('clock-fs', !!active);
        });
    }   // ← closes connectSocket()

    // Shared WOD overlay renderer (called from socket event & applyState reconnect)
    function _renderWodOverlay(active, blocks) {
        const overlay = document.getElementById('wod-overlay');
        if (!overlay) return;
        if (!active) {
            overlay.style.display = 'none';
            document.dispatchEvent(new CustomEvent('gf:wod:overlay', { detail: { active: false } }));
            return;
        }

        // Dispatch so sala.php QR can appear on top of the overlay
        document.dispatchEvent(new CustomEvent('gf:wod:overlay', {
            detail: { active: true, session_id: currentState?.session_id ?? null }
        }));

        // Populate session title from current state
        const titleEl = document.getElementById('wod-session-title');
        if (titleEl && currentState?.session_name) titleEl.textContent = currentState.session_name;

        // Type colors (same palette as live.php)
        const typeColors = {
            interval: '#00f5d4', tabata: '#ff6b35', amrap: '#7c3aed',
            emom: '#0ea5e9', fortime: '#f59e0b', series: '#ec4899',
            circuit: '#10b981', rest: '#3d5afe', briefing: '#6b7280'
        };

        const listEl = document.getElementById('wod-block-list');
        if (listEl) {
            // Reset scale before measuring so DOM reflects natural size
            listEl.style.transform = '';
            listEl.style.transformOrigin = 'top center';

            listEl.innerHTML = blocks.map((b, i) => {
                const col = typeColors[b.type] || '#888';
                const exs = b.exercises || [];
                const exLine = exs.length
                    ? exs.map(e => {
                        const n = e?.name || (typeof e === 'string' ? e : '');
                        // Isometric exercises have duration instead of reps
                        const r = e?.reps ? `${e.reps}×` : (e?.duration ? `${e.duration}s` : '');
                        return r ? `${r} ${n}` : n;
                    }).filter(Boolean).join('  ·  ')
                    : '';
                const cfg = b.config || {};
                const metaParts = [];
                if (cfg.rounds) metaParts.push(`${cfg.rounds} rondas`);
                if (cfg.sets) metaParts.push(`${cfg.sets} series`);
                if (cfg.work) metaParts.push(`${cfg.work}s / ${cfg.rest || 0}s`);
                if (cfg.duration && !cfg.work) metaParts.push(formatDuration(cfg.duration));
                const meta = metaParts.join(' · ');

                return `<div style="
                    display:flex;align-items:center;gap:16px;
                    padding:clamp(10px,1.5vh,18px) clamp(12px,1.8vw,22px);
                    background:rgba(255,255,255,0.04);
                    border:1px solid rgba(255,255,255,0.08);
                    border-left:4px solid ${col};
                    border-radius:10px;
                ">
                    <div style="flex-shrink:0;width:clamp(22px,2.2vw,34px);height:clamp(22px,2.2vw,34px);
                        border-radius:50%;background:${col}22;
                        display:flex;align-items:center;justify-content:center;
                        font-family:'Bebas Neue',sans-serif;font-size:clamp(11px,1.2vw,17px);color:${col}">
                        ${i + 1}
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:clamp(8px,0.8vw,11px);font-weight:700;letter-spacing:.12em;
                            text-transform:uppercase;color:${col};margin-bottom:3px">
                            ${(b.type || '').toUpperCase()}
                        </div>
                        <div style="font-size:clamp(14px,1.8vw,24px);font-weight:700;color:#fff;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            ${b.name || '—'}
                        </div>
                        ${exLine ? `<div style="font-size:clamp(10px,1vw,14px);color:rgba(255,255,255,0.5);margin-top:2px;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${exLine}</div>` : ''}
                    </div>
                    ${meta ? `<div style="flex-shrink:0;font-size:clamp(10px,1vw,13px);
                        color:rgba(255,255,255,0.4);font-weight:600;text-align:right">${meta}</div>` : ''}
                </div>`;
            }).join('');

            // ── Auto-scale to fit the screen ───────────────────────────────────────
            // Available height = viewport minus the header area (title + subtitle + padding)
            requestAnimationFrame(() => {
                const headerEl = overlay.querySelector('#wod-overlay-header') || overlay.firstElementChild;
                const headerH = headerEl ? headerEl.offsetHeight : 120;
                const gap = 24;  // gap between header and list in px
                const availH = window.innerHeight - headerH - gap * 2;
                const naturalH = listEl.scrollHeight;

                if (naturalH > availH && availH > 0) {
                    const scale = Math.max(0.35, availH / naturalH);
                    listEl.style.transform = `scale(${scale})`;
                    listEl.style.transformOrigin = 'top center';
                    // Collapse the wrapper height to avoid blank area below scaled list
                    listEl.style.marginBottom = `${-(naturalH * (1 - scale))}px`;
                } else {
                    listEl.style.transform = '';
                    listEl.style.marginBottom = '';
                }
            });
        }
        overlay.style.display = 'flex';
    }


    function _showPreviewOverlay(block) {
        // Show the idle screen with the exercise name — matches the "PREPARÁNDOSE" state
        // the instructor sees before starting a session.
        const idleScreen = document.getElementById('idle-screen');
        const liveScreen = document.getElementById('live-screen');
        const prepOverlay = document.getElementById('prep-overlay');
        const pausedOverlay = document.getElementById('paused-overlay');
        const ambientRings = document.getElementById('ambient-rings');
        const idleLabel = document.getElementById('idle-class-label');

        const exs = block.exercises || [];
        const exList = exs.length
            ? exs.map(ex => ex?.name || (typeof ex === 'string' ? ex : '')).filter(Boolean).join(' · ') || block.name
            : block.name;
        const typeLabels = { interval: 'WORK', tabata: 'TABATA', amrap: 'AMRAP', emom: 'EMOM', fortime: 'FOR TIME', series: 'SERIES', circuit: 'CIRCUITO', rest: 'DESCANSO', briefing: 'BRIEFING' };
        const typeStr = typeLabels[block.type] || (block.type || '').toUpperCase();

        if (idleLabel) idleLabel.innerHTML =
            `<span style="display:block;font-size:.45em;letter-spacing:.25em;color:var(--d-accent,#cbf73f);margin-bottom:.15em">${typeStr}</span>${exList}`;
        if (liveScreen) liveScreen.style.display = 'none';
        if (prepOverlay) prepOverlay.style.display = 'none';
        if (pausedOverlay) pausedOverlay.classList.remove('visible');
        if (ambientRings) ambientRings.style.display = 'block';
        if (idleScreen) idleScreen.style.display = 'flex';
    }

    function applyState(state) {
        // First state received — hide waiting screen, stop poller
        if (!_hasSession) {
            _hasSession = true;
            clearInterval(_pollerTimer);
            _hideWaitingScreen();
        }
        // Cancel any pending return-to-waiting timer on new state
        if (_finishedTimer) { clearTimeout(_finishedTimer); _finishedTimer = null; }

        const prev = currentState;
        currentState = state;
        // Notify external modules (e.g. RM QR overlay)
        document.dispatchEvent(new CustomEvent('gf:session:tick', { detail: state }));

        const status = state.status || 'idle';
        const blockIdx = state.current_block_index || 0;
        const block = state.current_block;
        const nextBlock = state.next_block;
        const totalBlocks = state.total_blocks || 0;

        // Server is the ONLY source of elapsed — adopt it directly
        localElapsed = state.elapsed || 0;

        // Detect block change for flash transition
        const blockChanged = !prev || prev.current_block_index !== blockIdx;
        if (blockChanged && prev) flashTransition();

        // Update body class — preserve clock-active and other non-state classes
        // (using full className replacement would nuke 'clock-active' on every tick)
        document.body.classList.remove('state-idle', 'state-playing', 'state-paused', 'state-finished', 'state-rest');
        document.body.classList.add(`state-${status}`);

        const idleScreen = document.getElementById('idle-screen');
        const liveScreen = document.getElementById('live-screen');
        const pausedOverlay = document.getElementById('paused-overlay');
        const finishedScreen = document.getElementById('finished-screen');
        const ambientRings = document.getElementById('ambient-rings');

        if (status === 'idle') {
            if (idleScreen) { idleScreen.style.display = 'flex'; ambientRings && (ambientRings.style.display = 'block'); }
            if (liveScreen) liveScreen.style.display = 'none';
            if (finishedScreen) finishedScreen.style.display = 'none';
            if (pausedOverlay) pausedOverlay.classList.remove('visible');
            // Show current block name on idle screen if one is loaded
            const idleLabel = document.getElementById('idle-class-label');
            if (idleLabel && block) idleLabel.textContent = block.name || '';
            // Show instructor name (universal block below won't run due to return)
            const instrElIdle = document.getElementById('idle-instructor-name');
            if (instrElIdle && state.instructor_name) {
                instrElIdle.textContent = '🏋️ ' + state.instructor_name;
                instrElIdle.style.display = 'block';
            }
            _applyClockMode(false);
            _clockActive = false;
            if (_rafId) { cancelAnimationFrame(_rafId); _rafId = null; }
            return;
        }

        if (status === 'finished') {
            if (idleScreen) idleScreen.style.display = 'none';
            if (liveScreen) liveScreen.style.display = 'none';
            if (finishedScreen) finishedScreen.style.display = 'flex';
            if (pausedOverlay) pausedOverlay.classList.remove('visible');
            _applyClockMode(false); // close clock when session ends
            stopTicker();
            document.dispatchEvent(new CustomEvent('gf:session:finished', {
                detail: { session_id: state.session_id ?? null }
            }));
            // After 45 s, return to waiting-for-session screen
            _finishedTimer = setTimeout(() => {
                _hasSession = false;
                _showWaitingScreen();
                startPresencePoller();
            }, 45000);
            return;
        }

        // PREPARATE overlay (Spotify intro countdown only)
        const prepOverlay = document.getElementById('prep-overlay');
        const prepCountdown = document.getElementById('prep-countdown');
        const prepBlockName = document.getElementById('prep-block-name');
        const prepRemaining = state.prep_remaining || 0;

        if (status === 'playing') {
            // Clear block-jump preview as soon as playing starts
            _previewBlock = null;
            if (idleScreen) idleScreen.style.display = 'none';
            if (ambientRings) ambientRings.style.display = 'none';
            if (liveScreen) liveScreen.style.display = 'grid';
            if (finishedScreen) finishedScreen.style.display = 'none';
            if (pausedOverlay) pausedOverlay.classList.remove('visible');
            if (prepOverlay) {
                if (prepRemaining > 0) {
                    // Spotify intro countdown
                    if (prepCountdown) { prepCountdown.style.display = ''; prepCountdown.textContent = prepRemaining; }
                    if (prepBlockName && block) {
                        const exs = block.exercises || [];
                        const firstName = exs.length
                            ? (exs[0]?.name || (typeof exs[0] === 'string' ? exs[0] : null) || block.name)
                            : block.name;
                        prepBlockName.textContent = firstName || '';
                    }
                    prepOverlay.style.display = 'flex';
                } else {
                    prepOverlay.style.display = 'none';
                }
            }
        } else if (status === 'paused') {
            if (_previewBlock) {
                // Block-jump: show idle screen with exercise name + PREPARÁNDOSE
                _showPreviewOverlay(_previewBlock);
            } else {
                // Normal mid-session pause: show live screen + PAUSA overlay
                if (idleScreen) idleScreen.style.display = 'none';
                if (ambientRings) ambientRings.style.display = 'none';
                if (liveScreen) liveScreen.style.display = 'grid';
                if (finishedScreen) finishedScreen.style.display = 'none';
                if (prepOverlay) prepOverlay.style.display = 'none';
                if (pausedOverlay) pausedOverlay.classList.add('visible');
            }
        }

        // Session name
        const sessionNameEl = document.getElementById('display-session-name');
        if (sessionNameEl && state.session_name) sessionNameEl.textContent = state.session_name;

        // Instructor name — update both the idle screen element and the live corner widget
        const _instrName = state.instructor_name || null;
        ['idle-instructor-name', 'display-instructor-name'].forEach(elId => {
            const el = document.getElementById(elId);
            if (!el) return;
            if (_instrName) {
                el.textContent = '🏋️  ' + _instrName;
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        });

        // WOD overlay: apply from tick so reconnecting displays get correct state
        if (state.wod_overlay !== undefined) {
            const wo = state.wod_overlay;
            const overlay = document.getElementById('wod-overlay');
            if (overlay) {
                if (wo.active && wo.blocks?.length) {
                    // Only re-render if currently hidden (avoid flicker on every tick)
                    if (overlay.style.display === 'none') {
                        _renderWodOverlay(true, wo.blocks);
                    }
                } else {
                    overlay.style.display = 'none';
                }
            }
        }

        // Update block display
        updateBlockDisplay(block, nextBlock, blockIdx, totalBlocks);

        // Render clock directly (no local ticker — server drives time)
        renderClock(block, localElapsed);

        // Total progress
        updateTotalProgress(state);

        // Block dots
        updateBlockDots(blockIdx, totalBlocks);

        // Clock Mode — toggle panel + WOD area resize
        _applyClockMode(state);
    }

    // ── Clock Mode ─────────────────────────────────────────────────────────
    // Fullscreen toggle — called from HTML onclick
    window.toggleClockFs = function () {
        document.body.classList.toggle('clock-fs');
    };

    function _applyClockMode(state) {
        // Accept either a full state object or a bare false/falsy to deactivate
        const cm = (state && typeof state === 'object') ? state.clock_mode : null;
        const active = !!(cm && cm.active);
        const wasActive = _clockActive;
        _clockActive = active;

        // Toggle body class (drives CSS: WOD shrinks to 75%, panel appears)
        document.body.classList.toggle('clock-active', active);

        if (!active) {
            // Stop interpolation rAF, exit fullscreen, clear panel
            if (_rafId) { cancelAnimationFrame(_rafId); _rafId = null; }
            document.body.classList.remove('clock-fs'); // exit fullscreen too
            return;
        }

        // Record tick arrival for sub-second interpolation
        _lastTickAt = performance.now();

        // Render immediately with server elapsed
        renderClockPanel(state, localElapsed);

        // Start/continue smooth rAF loop only if just became active
        if (!wasActive) _startClockRaf(state);
    }

    function _startClockRaf(state) {
        if (_rafId) cancelAnimationFrame(_rafId);

        function tick() {
            if (!_clockActive) { _rafId = null; return; }
            // ms elapsed since last server tick (capped at 1200ms to avoid drift)
            const msSinceTick = Math.min(performance.now() - _lastTickAt, 1200);
            const interpolated = localElapsed + msSinceTick / 1000;
            renderClockPanel(currentState, interpolated);
            _rafId = requestAnimationFrame(tick);
        }

        _rafId = requestAnimationFrame(tick);
    }

    // Renders the clock panel from session tick data.
    // elapsed may be fractional (from sub-second rAF interpolation).
    function renderClockPanel(state, elapsed) {
        if (!state) return;
        const block = state.current_block;
        const status = state.status || 'idle';
        const clockMode = state.clock_mode || {};
        const ct = state.clock_timer || {};

        const digitsEl = document.getElementById('clock-digits');
        const phaseEl = document.getElementById('clock-phase-label');
        const subEl = document.getElementById('clock-sub');
        const fillEl = document.getElementById('clock-progress-fill');
        const lblEl = document.getElementById('clock-progress-label');
        if (!digitsEl) return;

        // ── STANDALONE CLOCK MODE (not tied to the WOD session) ───────────────
        // SMART FALLTHROUGH: if the standalone timer is idle (never started)
        // but the WOD session has an active block → mirror the WOD timer instead.
        const standaloneMode = clockMode.mode === 'countdown' || clockMode.mode === 'countup' || clockMode.mode === 'tabata';
        const ct_running = (state.clock_timer || {}).running || false;
        const ct_elapsed = (state.clock_timer || {}).elapsed || 0;

        const useStandalone = standaloneMode && (ct_running || ct_elapsed > 0);

        if (useStandalone) {
            const ctElapsed = ct.elapsed || 0;
            const ctDuration = ct.duration || 300;
            const ctRunning = ct.running || false;
            const ctPhase = ct.phase || 'idle';
            const ctPrep = ct.prep || 0;
            const ctPrepElapsed = ct.prepElapsed || 0;
            const ctWork = ct.work || 20;
            const ctRest = ct.rest || 10;
            const ctRounds = ct.rounds || 8;
            const ctRound = (ct.currentRound || 0) + 1;
            const ctPhaseElap = ct.phaseElapsed || 0;

            let displaySec, phaseLabel, subText;
            const mode = clockMode.mode;

            // ── PREP phase ────────────────────────────────────────────────
            if (ctPhase === 'prep' || (ctPrep > 0 && ctPrepElapsed < ctPrep)) {
                displaySec = Math.max(0, ctPrep - ctPrepElapsed);
                phaseLabel = 'PREP';
                subText = 'Preparate…';
                document.body.classList.remove('state-rest');

                // ── DONE ──────────────────────────────────────────────────────
            } else if (ctPhase === 'done') {
                displaySec = 0;
                phaseLabel = mode === 'tabata' ? 'FIN' : 'FIN';
                subText = '¡Tiempo!';
                document.body.classList.remove('state-rest');

                // ── TABATA: WORK phase ─────────────────────────────────────────
            } else if (mode === 'tabata' && ctPhase === 'work') {
                displaySec = Math.max(0, ctWork - ctPhaseElap);
                phaseLabel = 'WORK';
                subText = `Ronda ${ctRound} / ${ctRounds}`;
                document.body.classList.remove('state-rest');

                // ── TABATA: REST phase ─────────────────────────────────────────
            } else if (mode === 'tabata' && ctPhase === 'rest') {
                displaySec = Math.max(0, ctRest - ctPhaseElap);
                phaseLabel = 'DESCANSO';
                subText = `Ronda ${ctRound} / ${ctRounds}`;
                document.body.classList.add('state-rest');

                // ── COUNTDOWN ─────────────────────────────────────────────────
            } else if (mode === 'countdown') {
                displaySec = Math.max(0, ctDuration - ctElapsed);
                phaseLabel = 'CD';
                subText = ctRunning ? 'EN MARCHA' : (ctElapsed > 0 ? 'PAUSADO' : 'LISTO');
                document.body.classList.remove('state-rest');

                // ── COUNT-UP ──────────────────────────────────────────────────
            } else {
                displaySec = ctElapsed;
                phaseLabel = 'CU';
                subText = ctRunning ? 'EN MARCHA' : (ctElapsed > 0 ? 'PAUSADO' : 'LISTO');
                document.body.classList.remove('state-rest');
            }

            const secFormatted = _formatClockMs(displaySec);
            const intSec = Math.floor(displaySec);

            // Beeps
            if (intSec !== _lastClockSec) {
                if (intSec >= 1 && intSec <= 3) _beepCountdown();
                if (intSec === 0 && _lastClockSec === 1) _beepEnd();
                if (_lastClockPhase !== phaseLabel && phaseLabel === 'WORK') _beepToWork?.();
                if (_lastClockPhase !== phaseLabel && phaseLabel === 'DESCANSO') _beepToRest?.();
                _lastClockSec = intSec;
            }
            _lastClockPhase = phaseLabel;

            if (digitsEl && digitsEl.textContent !== secFormatted) digitsEl.textContent = secFormatted;
            if (phaseEl) phaseEl.textContent = phaseLabel;
            if (subEl) subEl.textContent = subText;

            const digitsWrapper = document.getElementById('clock-digits-wrapper');
            if (digitsWrapper) digitsWrapper.dataset.ghost = '8'.repeat(secFormatted.replace(':', '').length).split('').join('').replace(/(.{1})(.{2})$/, '$1:$2');
            const phaseWrapper = document.getElementById('clock-phase-wrapper');
            if (phaseWrapper) phaseWrapper.dataset.ghost = '8888';

            // Mirror to fullscreen
            const fsDigitsEl = document.getElementById('clock-fs-digits');
            const fsPhaseEl = document.getElementById('clock-fs-phase');
            const fsSubEl = document.getElementById('clock-fs-sub');
            const fsDW = document.getElementById('clock-fs-digits-wrapper');
            const fsPW = document.getElementById('clock-fs-phase-wrapper');
            if (fsDigitsEl && fsDigitsEl.textContent !== secFormatted) fsDigitsEl.textContent = secFormatted;
            if (fsPhaseEl) fsPhaseEl.textContent = phaseLabel;
            if (fsSubEl) fsSubEl.textContent = subText;
            if (fsDW) fsDW.dataset.ghost = digitsWrapper ? digitsWrapper.dataset.ghost : '8:88';
            if (fsPW) fsPW.dataset.ghost = '8888';
            return; // ← skip session-based rendering below
        }
        // ── END STANDALONE MODE ───────────────────────────────────────────────

        // If session is paused, show frozen time with PAUSA label
        if (status === 'paused') {
            if (phaseEl) phaseEl.textContent = 'PAUSA';
            return; // keep digits frozen at last known value
        }

        if (!block) return;
        const cfg = block.config || {};
        const dur = computeBlockDuration(block);
        const blockType = block.type || '';

        let displaySec, phaseLabel, subText, pct;

        if (blockType === 'tabata' || blockType === 'interval') {
            // Timed intervals: show phase clock (work/rest countdown)
            const workSecs = cfg.work || (blockType === 'tabata' ? 20 : 40);
            const restSecs = cfg.rest || (blockType === 'tabata' ? 10 : 20);
            const totalRounds = cfg.rounds || (blockType === 'tabata' ? 8 : 1);
            const cycleSec = workSecs + restSecs;
            const phaseElapsed = elapsed % cycleSec;
            const inWork = phaseElapsed < workSecs;
            const currentRound = Math.floor(elapsed / cycleSec); // 0-based
            const isLastRound = currentRound >= totalRounds - 1;

            if (inWork) {
                displaySec = workSecs - phaseElapsed;  // countdown within work
                phaseLabel = 'WORK';
            } else {
                const restElapsed = phaseElapsed - workSecs;
                displaySec = restSecs - restElapsed;   // countdown within rest
                phaseLabel = 'DESCANSO';
            }

            const shownRound = Math.min(currentRound + 1, totalRounds);
            subText = `Ronda ${shownRound} / ${totalRounds}`;
            pct = Math.min(100, (elapsed / dur) * 100);
            if (lblEl) lblEl.textContent = block.name || blockType.toUpperCase();

        } else if (blockType === 'amrap' || blockType === 'emom' || blockType === 'fortime') {
            // Countdown from total duration
            displaySec = Math.max(0, dur - elapsed);
            phaseLabel = { amrap: 'AMRAP', emom: 'EMOM', fortime: 'FOR TIME' }[blockType] || blockType.toUpperCase();
            subText = block.name || '';
            pct = Math.min(100, (elapsed / dur) * 100);
            if (lblEl) lblEl.textContent = formatDuration(dur);

        } else if (blockType === 'rest') {
            displaySec = Math.max(0, dur - elapsed);
            phaseLabel = 'DESCANSO';
            subText = 'Recuperación';
            pct = Math.min(100, (elapsed / dur) * 100);
            if (lblEl) lblEl.textContent = formatDuration(dur);

        } else {
            // Series, circuit, briefing, default: simple countdown
            displaySec = Math.max(0, dur - elapsed);
            phaseLabel = blockType.toUpperCase() || 'TIEMPO';
            subText = block.name || '';
            pct = dur > 0 ? Math.min(100, (elapsed / dur) * 100) : 0;
            if (lblEl) lblEl.textContent = formatDuration(dur);
        }

        // Update elements — hardware layout
        const secFormatted = _formatClockMs(displaySec);
        const intSec = Math.floor(displaySec);

        // ── BEEP LOGIC ──────────────────────────────────────────────────────────
        // Phase transition: detect WORK↔DESCANSO change
        if (_lastClockPhase && _lastClockPhase !== phaseLabel) {
            if (phaseLabel === 'DESCANSO') _beepToRest();
            else _beepToWork();
        }
        _lastClockPhase = phaseLabel;

        // 3-2-1 countdown pips (fires once per integer second change)
        if (intSec !== _lastClockSec) {
            if (intSec >= 1 && intSec <= 3) _beepCountdown();
            if (intSec === 0 && _lastClockSec === 1) _beepEnd();
            _lastClockSec = intSec;
        }
        // ── END BEEP LOGIC ──────────────────────────────────────────────────────────
        // Short hardware-style phase labels (DSEG7 renders uppercase well)
        const HW_LABEL = {
            'WORK': 'WORK',
            'DESCANSO': 'REST',
            'AMRAP': 'AMRAP',
            'EMOM': 'EMOM',
            'FOR TIME': 'FT',
            'PAUSA': 'STOP',
            'PREP': 'PREP',
        };
        const hwPhase = HW_LABEL[phaseLabel] || phaseLabel.slice(0, 5);

        // Update digits
        if (digitsEl && digitsEl.textContent !== secFormatted) digitsEl.textContent = secFormatted;

        // Keep ghost data-attr length matching digit length for proper ghost alignment
        const digitsWrapper = document.getElementById('clock-digits-wrapper');
        if (digitsWrapper) digitsWrapper.dataset.ghost = '8'.repeat(secFormatted.replace(':', '').length)
            .split('').join('').replace(/(.{1})(.{2})$/, '$1:$2'); // e.g. "8:88" or "88:88"

        // Phase label  
        if (phaseEl) phaseEl.textContent = hwPhase;
        const phaseWrapper = document.getElementById('clock-phase-wrapper');
        if (phaseWrapper) phaseWrapper.dataset.ghost = '8'.repeat(hwPhase.length);

        // Left info: sub text (round counter, block name)
        if (subEl) subEl.textContent = subText;
        if (lblEl) lblEl.textContent = ''; // keep empty — used only for progress label

        // Drive REST body class for clock color (blue vs red)
        const isRestPhase = phaseLabel === 'DESCANSO' || blockType === 'rest';
        document.body.classList.toggle('state-rest', isRestPhase);

        // ── Mirror to fullscreen elements ────────────────────────────────────────
        const fsDigitsEl = document.getElementById('clock-fs-digits');
        const fsPhaseEl = document.getElementById('clock-fs-phase');
        const fsSubEl = document.getElementById('clock-fs-sub');
        const fsDW = document.getElementById('clock-fs-digits-wrapper');
        const fsPW = document.getElementById('clock-fs-phase-wrapper');
        if (fsDigitsEl && fsDigitsEl.textContent !== secFormatted) fsDigitsEl.textContent = secFormatted;
        if (fsPhaseEl) fsPhaseEl.textContent = hwPhase;
        if (fsSubEl) fsSubEl.textContent = subText;
        if (fsDW) fsDW.dataset.ghost = digitsWrapper ? digitsWrapper.dataset.ghost : secFormatted.replace(/\d/g, '8');
        if (fsPW) fsPW.dataset.ghost = phaseWrapper ? phaseWrapper.dataset.ghost : '88';
        // ── End fullscreen mirror ────────────────────────────────────────────────
    }

    // Format seconds to M:SS (integer version) or M:SS.t for sub-second
    // The clock panel shows integer seconds (same as a real hardware timer).
    function _formatClockMs(sec) {
        sec = Math.max(0, sec);
        const intSec = Math.floor(sec);
        const m = Math.floor(intSec / 60);
        const s = intSec % 60;
        return `${m}:${String(s).padStart(2, '0')}`;
    }


    // ── Beep Engine (Web Audio API) ───────────────────────────────────────────────
    // Display screen (sala.php): no instructor interaction expected.
    // Strategy: attempt AudioContext creation immediately on load — browsers often
    // allow this when the page is opened directly (navigation gesture counts).
    // If suspended, retry on every lifecycle event until it resumes.
    let _audioUnlocked = false;

    function _tryUnlockAudio() {
        if (_audioUnlocked) return;
        try {
            if (!_audioCtx) {
                _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (_audioCtx.state === 'suspended') {
                _audioCtx.resume().then(() => {
                    if (_audioCtx.state === 'running') _audioUnlocked = true;
                }).catch(() => { });
            } else if (_audioCtx.state === 'running') {
                _audioUnlocked = true;
            }
        } catch (e) { /* navegador muy restrictivo — silencioso */ }
    }

    // Intento inmediato al cargar (funciona si la página fue abierta por el usuario)
    _tryUnlockAudio();

    // Reintento en cualquier interacción del sistema (cambio de tab, foco, etc.)
    document.addEventListener('visibilitychange', _tryUnlockAudio);
    window.addEventListener('focus', _tryUnlockAudio);
    // Fallback legacy: click/touch por si el gym tiene teclado/mouse en la sala
    document.addEventListener('click', _tryUnlockAudio, { once: true });
    document.addEventListener('touchstart', _tryUnlockAudio, { once: true });

    function _getAudioCtx() {
        if (!_audioUnlocked) {
            _tryUnlockAudio();   // reintento lazy en cada beep
            if (!_audioCtx || _audioCtx.state !== 'running') return null;
        }
        return _audioCtx;
    }

    // Core beep primitive: freq (Hz), dur (s), wave type, volume, start delay (s)
    function _beep(freq, dur, type = 'square', vol = 0.4, delay = 0) {
        const ctx = _getAudioCtx();
        if (!ctx) return; // not unlocked yet — silent
        try {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = type;
            osc.frequency.setValueAtTime(freq, ctx.currentTime + delay);
            gain.gain.setValueAtTime(0, ctx.currentTime + delay);
            gain.gain.linearRampToValueAtTime(vol, ctx.currentTime + delay + 0.005);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + dur);
            osc.start(ctx.currentTime + delay);
            osc.stop(ctx.currentTime + delay + dur + 0.05);
        } catch (e) { /* silently ignore if audio unavailable */ }
    }

    // ── Named sound events ──────────────────────────────────────────────────────

    // 3-2-1 countdown: pip corto cuadrado
    function _beepCountdown() {
        _beep(880, 0.08, 'square', 0.45);
    }

    // Work → REST: two descending notes ("it’s over, cool down")
    function _beepToRest() {
        _beep(880, 0.10, 'square', 0.45, 0.00);
        _beep(660, 0.20, 'square', 0.40, 0.13);
    }

    // REST → WORK: doble beep rapido 2.2 kHz GO!
    function _beepToWork() {
        _beep(660, 0.07, 'square', 0.40, 0.00);
        _beep(880, 0.07, 'square', 0.45, 0.09);
        _beep(1100, 0.20, 'square', 0.55, 0.18);
    }

    // Fin de bloque: tres notas descendentes
    function _beepEnd() {
        _beep(880, 0.30, 'square', 0.50, 0.00);
        _beep(660, 0.30, 'square', 0.50, 0.38);
        _beep(440, 0.60, 'square', 0.50, 0.76);
    }

    // ── Self-contained block duration calculator (mirrors server logic)
    function computeBlockDuration(block) {
        if (!block) return 300;
        const c = block.config || {};
        switch (block.type) {
            case 'interval': {
                const r = c.rounds || 1, w = c.work || 40, re = c.rest || 20;
                return r * w + (r - 1) * re;
            }
            case 'tabata': {
                const r = c.rounds || 8, w = c.work || 20, re = c.rest || 10;
                return r * w + (r - 1) * re;
            }
            case 'amrap': case 'emom': case 'fortime': return c.duration || 600;
            case 'rest': case 'briefing': return c.duration || 60;
            case 'series': return (c.sets || 3) * ((c.rest || 60) + 30);
            case 'circuit': return (block.exercises?.length || 0) * (c.station_time || 40) * (c.rounds || 1);
            default: return 300;
        }
    }

    // Format seconds as M:SS
    function formatDuration(sec) {
        sec = Math.max(0, Math.floor(sec));
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}:${String(s).padStart(2, '0')}`;
    }

    function updateBlockDisplay(block, nextBlock, blockIdx, totalBlocks) {
        if (!block) return;

        // Status label
        const statusLabel = document.getElementById('status-label');
        if (statusLabel) {
            const labels = { interval: 'WORK', tabata: 'WORK', amrap: 'AMRAP', emom: 'EMOM', fortime: 'FOR TIME', series: 'SERIES', circuit: 'CIRCUITO', rest: 'DESCANSO', briefing: 'BRIEFING' };
            statusLabel.textContent = labels[block.type] || (block.type || '').toUpperCase();
        }

        // Exercise name (main display text)
        const exEl = document.getElementById('display-exercise-name');
        if (exEl) {
            const exs = block.exercises || [];
            if (block.type === 'rest') {
                exEl.textContent = 'RECUPERACIÓN';
            } else if (block.type === 'briefing') {
                exEl.textContent = block.config?.title || block.name || 'BRIEFING';
            } else if (exs.length) {
                let exIdx = 0;
                if ((block.type === 'tabata' || block.type === 'interval') && exs.length > 0) {
                    // Alternating exercise per timed round (works for 1 or more exercises)
                    const cfg = block.config || {};
                    const roundDur = (cfg.work || 20) + (cfg.rest || 10);
                    exIdx = Math.floor(localElapsed / roundDur) % exs.length;
                    exEl.dataset.roundIdx = exIdx;
                    const ex = exs[exIdx];
                    exEl.textContent = ex?.name || (typeof ex === 'string' ? ex : null) || block.name;
                    exEl.style.animation = 'none';
                    requestAnimationFrame(() => exEl.style.animation = '');
                } else if (['amrap', 'emom', 'fortime', 'series'].includes(block.type)) {
                    // All exercises are done together — show block name, not one exercise
                    exEl.textContent = block.name || block.type.toUpperCase();
                } else if (block.type === 'circuit' && exs.length > 0) {
                    // Circuit: rotate station based on elapsed / station_time
                    const stationSec = block.config?.station_time || 40;
                    exIdx = Math.floor(localElapsed / stationSec) % exs.length;
                    exEl.dataset.roundIdx = exIdx;
                    const ex = exs[exIdx];
                    exEl.textContent = ex?.name || (typeof ex === 'string' ? ex : null) || block.name;
                    exEl.style.animation = 'none';
                    requestAnimationFrame(() => exEl.style.animation = '');
                } else {
                    // Single exercise
                    exEl.dataset.roundIdx = 0;
                    const ex = exs[0];
                    exEl.textContent = ex?.name || (typeof ex === 'string' ? ex : null) || block.name;
                    exEl.style.animation = 'none';
                    requestAnimationFrame(() => exEl.style.animation = '');
                }
            } else {
                exEl.textContent = block.name || '—';
            }
        }

        // Block meta
        const metaEl = document.getElementById('block-meta');
        if (metaEl) {
            const cfg = block.config || {};
            const metas = [];
            if (cfg.rounds) metas.push(`${cfg.rounds} rondas`);
            if (cfg.sets) metas.push(`${cfg.sets} series × ${cfg.reps || '?'} reps`);
            if (cfg.work) metas.push(`${cfg.work}s trabajo / ${cfg.rest || 0}s descanso`);
            metaEl.textContent = metas.join(' · ') || block.name || '';
        }

        // Exercise list chips
        updateExerciseList(block);

        // Stickman widget
        if (stickWidget) {
            const exs = block.exercises || [];
            const exIdx = (() => {
                if ((block.type === 'tabata' || block.type === 'interval') && exs.length > 1) {
                    const cfg = block.config || {};
                    const roundDur = (cfg.work || 20) + (cfg.rest || 10);
                    return Math.floor(localElapsed / roundDur) % exs.length;
                }
                if (block.type === 'circuit' && exs.length > 1) {
                    const stationSec = block.config?.station_time || 40;
                    return Math.floor(localElapsed / stationSec) % exs.length;
                }
                if (['amrap', 'emom', 'fortime', 'series'].includes(block.type) && exs.length > 1) {
                    return Math.floor(localElapsed / 20) % exs.length;
                }
                return 0;
            })();
            const exObj = exs[exIdx];
            const exName = (exObj && exObj.name) ? exObj.name : (typeof exObj === 'string' ? exObj : (block.name || ''));
            // Determine phase: tabata has work/rest sub-phases
            let smPhase = 'work';
            if (block.type === 'rest') {
                smPhase = 'rest';
            } else if (block.type === 'tabata' || block.type === 'interval') {
                const cfg = block.config || {};
                const workSecs = cfg.work || 20;
                const restSecs = cfg.rest || 10;
                const cycleSec = workSecs + restSecs;
                smPhase = (localElapsed % cycleSec) < workSecs ? 'work' : 'rest';
            }
            stickWidget.update(exName, smPhase);
        }

        // Block type label & total time
        const typeLabel = document.getElementById('block-type-label');
        if (typeLabel) typeLabel.textContent = (block.type || '').toUpperCase();
        const blockTimeTotal = document.getElementById('block-time-total');
        if (blockTimeTotal) blockTimeTotal.textContent = formatDuration(computeBlockDuration(block));

        // Block counter
        const counterEl = document.getElementById('block-counter');
        if (counterEl) counterEl.textContent = `${blockIdx + 1} / ${totalBlocks}`;

        // Next block
        const nextTypeEl = document.getElementById('next-block-type');
        const nextNameEl = document.getElementById('next-block-name');
        const nextDurEl = document.getElementById('next-block-duration');
        if (nextBlock) {
            if (nextTypeEl) nextTypeEl.textContent = (nextBlock.type || '').toUpperCase();
            if (nextNameEl) nextNameEl.textContent = nextBlock.name || '—';
            if (nextDurEl) nextDurEl.textContent = formatDuration(computeBlockDuration(nextBlock));
        } else {
            if (nextTypeEl) nextTypeEl.textContent = '';
            if (nextNameEl) nextNameEl.textContent = 'Fin de sesión';
            if (nextDurEl) nextDurEl.textContent = '';
        }

        // Rounds widget
        const roundsWidget = document.getElementById('rounds-widget');
        if (roundsWidget) {
            const showRounds = ['interval', 'tabata', 'circuit', 'fortime'].includes(block.type);
            roundsWidget.style.display = showRounds ? 'block' : 'none';
            if (showRounds) {
                const cfg = block.config || {};
                const totalR = cfg.rounds || (block.type === 'tabata' ? 8 : '?');
                const roundDur = (cfg.work || 40) + (cfg.rest || 20);
                const curRound = Math.min(Math.floor(localElapsed / (roundDur || 60)) + 1, totalR);
                document.getElementById('current-round').textContent = curRound;
                document.getElementById('total-rounds').textContent = totalR;
            }
        }
    }

    // ── Exercise List Chips ────────────────────────────────────────────────────
    function updateExerciseList(block) {
        const container = document.getElementById('exercise-list');
        if (!container) return;

        const exs = block?.exercises || [];
        const rotatingTypes = ['tabata', 'interval', 'circuit', 'amrap', 'emom', 'fortime', 'series'];
        const showList = exs.length >= 2 && rotatingTypes.includes(block.type);

        if (!showList) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        // Determine which exercise is currently highlighted (-1 = none)
        let activeIdx = -1;
        if (block.type === 'tabata' || block.type === 'interval') {
            // Advance highlight per timed round
            const cfg = block.config || {};
            const roundDur = (cfg.work || 20) + (cfg.rest || 10);
            activeIdx = Math.floor(localElapsed / roundDur) % exs.length;
        } else if (block.type === 'circuit') {
            // Station-based: highlight per station time
            const cfg = block.config || {};
            const stationTime = cfg.station_time || 40;
            activeIdx = Math.floor(localElapsed / stationTime) % exs.length;
        }
        // amrap / fortime / series / emom: activeIdx stays -1 (all equal)

        container.style.display = 'flex';

        // Responsive scaling: divide ~75vh by number of exercises to fit all on screen.
        // font-size ≈ 50% of per-chip budget, clamped to a readable range.
        const count = exs.length;
        const budgetVh = 75 / count;               // vh available per chip
        const fontVh = Math.min(4.5, Math.max(1.0, budgetVh * 0.5));
        const padV = Math.min(10, Math.max(2, budgetVh * 0.12));
        const padH = Math.min(32, Math.max(8, fontVh * 5));
        const fontSize = `${fontVh}vh`;
        const padding = `${padV}px ${padH}px`;

        container.innerHTML = exs.map((ex, i) => {
            const exName = ex?.name || (typeof ex === 'string' ? ex : '?');
            // Show reps prefix for list-based blocks (amrap/fortime/series/emom)
            const showReps = ['amrap', 'emom', 'fortime', 'series'].includes(block.type);
            const repsPrefix = (showReps && ex?.reps) ? `${ex.reps} × ` : '';
            const name = repsPrefix + exName;
            const isActive = i === activeIdx;
            return `<span style="
            display:inline-flex;align-items:center;
            padding:${padding};
            border-radius:999px;
            font-size:${fontSize};
            font-weight:${isActive ? '800' : '500'};
            letter-spacing:.08em;
            text-transform:uppercase;
            transition:all .4s ease;
            border:2px solid ${isActive ? 'var(--d-accent,#ff6b35)' : 'rgba(255,255,255,0.18)'};
            background:${isActive ? 'rgba(255,107,53,0.18)' : 'rgba(255,255,255,0.05)'};
            color:${isActive ? 'var(--d-accent,#ff6b35)' : 'rgba(255,255,255,0.45)'};
            box-shadow:${isActive ? '0 0 24px rgba(255,107,53,0.35)' : 'none'};
        ">${name}</span>`;
        }).join('');
    }

    // No local ticker — server drives elapsed via Socket.IO ticks every 1s

    function renderClock(block, elapsed) {
        if (!block) return;
        const dur = computeBlockDuration(block);
        const remaining = Math.max(0, dur - elapsed);
        const clockEl = document.getElementById('display-clock');
        if (clockEl) clockEl.textContent = formatDuration(remaining);

        // Block progress
        const pct = dur > 0 ? Math.min(100, (elapsed / dur) * 100) : 0;
        const fill = document.getElementById('block-progress-fill');
        if (fill) fill.style.width = pct + '%';

        // REST overlay for tabata/interval blocks
        const restOverlay = document.getElementById('rest-overlay');
        const restCountdown = document.getElementById('rest-countdown');

        if (block.type === 'rest') {
            document.body.classList.add('state-rest');
            document.body.classList.remove('state-work');
            if (restOverlay) restOverlay.style.display = 'none';
        } else if (block.type === 'interval' || block.type === 'tabata') {
            const cfg = block.config || {};
            const workSecs = cfg.work || 40;
            const restSecs = cfg.rest || (block.type === 'tabata' ? 10 : 20);
            const totalRounds = cfg.rounds || (block.type === 'tabata' ? 8 : 1);
            const roundDur = workSecs + restSecs;
            const phaseElapsed = elapsed % roundDur;
            const currentRound = Math.floor(elapsed / roundDur); // 0-indexed
            const isLastRound = currentRound >= totalRounds - 1;
            const inWork = phaseElapsed < workSecs;

            // After the last work phase ends, treat as finished (no final rest)
            const lastWorkEnded = isLastRound && !inWork;

            document.body.className = (inWork || lastWorkEnded) ? 'state-work' : 'state-rest';

            if (restOverlay) {
                // Only show REST overlay if NOT in PREPARATE phase and NOT after last work
                const prepVisible = document.getElementById('prep-overlay')?.style.display === 'flex';
                if (!inWork && !lastWorkEnded && !prepVisible) {
                    const restRemaining = roundDur - phaseElapsed;
                    restOverlay.style.display = 'flex';
                    if (restCountdown) restCountdown.textContent = restRemaining;

                    // Show next exercise name so participants can prepare
                    const nextExEl = document.getElementById('rest-next-exercise');
                    if (nextExEl) {
                        const exs = block.exercises || [];
                        if (exs.length > 1) {
                            // Next exercise in the rotation
                            const curRoundIdx = Math.floor(elapsed / roundDur);
                            const nextExIdx = (curRoundIdx + 1) % exs.length;
                            const nextEx = exs[nextExIdx];
                            nextExEl.textContent = nextEx?.name || (typeof nextEx === 'string' ? nextEx : '');
                        } else if (exs.length === 1) {
                            // Single exercise: same exercise again
                            const ex = exs[0];
                            nextExEl.textContent = ex?.name || (typeof ex === 'string' ? ex : '');
                        } else {
                            nextExEl.textContent = block.name || '';
                        }
                    }
                } else {
                    restOverlay.style.display = 'none';
                }
            }

            // Cycle exercise per round
            const exs = block.exercises || [];
            if (exs.length > 0 && inWork) {
                const roundIndex = Math.floor(elapsed / roundDur);
                const exEl = document.getElementById('display-exercise-name');
                if (exEl) {
                    const newIdx = roundIndex % exs.length;
                    if (exEl.dataset.roundIdx !== String(newIdx)) {
                        exEl.dataset.roundIdx = newIdx;
                        const ex = exs[newIdx];
                        exEl.textContent = ex?.name || (typeof ex === 'string' ? ex : null) || block.name;
                        exEl.style.animation = 'none';
                        requestAnimationFrame(() => exEl.style.animation = '');
                    }
                }
            }
        } else {
            if (restOverlay) restOverlay.style.display = 'none';
        }
    }


    function updateTotalProgress(state) {
        const totalPercent = state.total_duration > 0 ?
            Math.min(100, ((state.current_block_index || 0) / (state.total_blocks || 1)) * 100) : 0;
        const fill = document.getElementById('total-progress-fill');
        if (fill) fill.style.width = totalPercent + '%';
    }

    function updateBlockDots(currIdx, total) {
        const container = document.getElementById('block-dots');
        if (!container || total === 0) return;
        container.innerHTML = Array.from({ length: total }, (_, i) => {
            const cls = i < currIdx ? 'done' : (i === currIdx ? 'current' : '');
            return `<div class="block-dot ${cls}"></div>`;
        }).join('');
    }

    function flashTransition() {
        const el = document.getElementById('transition-flash');
        if (!el) return;
        el.style.display = 'block';
        el.style.animation = 'none';
        requestAnimationFrame(() => {
            el.style.animation = 'flashTransition 0.5s ease forwards';
        });
        setTimeout(() => { el.style.display = 'none'; }, 600);
    }

    function computeBlockDuration(block) {
        if (!block) return 300;
        const cfg = block.config || {};
        switch (block.type) {
            case 'interval': {
                const rounds = cfg.rounds || 1;
                const work = cfg.work || 40;
                const rest = cfg.rest || 20;
                // Total = all rounds of work + (rounds-1) rests (no trailing rest after last round)
                return rounds * work + (rounds - 1) * rest;
            }
            case 'tabata': {
                const rounds = cfg.rounds || 8;
                const work = cfg.work || 20;
                const rest = cfg.rest || 10;
                // No trailing rest after last round
                return rounds * work + (rounds - 1) * rest;
            }
            case 'amrap': case 'emom': case 'fortime': return cfg.duration || 600;
            case 'rest': case 'briefing': return cfg.duration || 60;
            case 'series': return (cfg.sets || 3) * ((cfg.rest || 60) + 30);
            case 'circuit': return (block.exercises?.length || 0) * (cfg.station_time || 40) * (cfg.rounds || 1);
            default: return 300;
        }
    }

    function formatDuration(sec) {
        sec = Math.max(0, Math.floor(sec));
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}:${String(s).padStart(2, '0')}`;
    }

    document.addEventListener('DOMContentLoaded', init);
})();
