// GymFlow — Display Sync Engine (SSE Client)
(function () {
    let currentState = null;
    let sseSource = null;
    let clockTicker = null;
    let localElapsed = 0;
    let localPhase = 'work';

    function init() {
        connectSSE();
        // Fallback: poll every 3s if SSE fails
        setTimeout(() => {
            if (!currentState) pollFallback();
        }, 5000);
    }

    function connectSSE() {
        const url = `${BASE}/api/sync.php?sala_id=${SALA_ID}`;
        try {
            sseSource = new EventSource(url);
            sseSource.addEventListener('sync', e => {
                try {
                    const state = JSON.parse(e.data);
                    applyState(state);
                } catch (err) { }
            });
            sseSource.onerror = () => {
                sseSource?.close();
                sseSource = null;
                setTimeout(connectSSE, 3000); // auto-reconnect
            };
        } catch (e) {
            pollFallback();
        }
    }

    function pollFallback() {
        setInterval(async () => {
            try {
                const r = await fetch(`${BASE}/api/sync.php?sala_id=${SALA_ID}&poll=1`, { signal: AbortSignal.timeout(2000) });
                if (r.ok) {
                    const state = await r.json();
                    if (state?.state_json) applyState(JSON.parse(state.state_json));
                }
            } catch (e) { }
        }, 2000);
    }

    function applyState(state) {
        const prev = currentState;
        currentState = state;

        const status = state.status || 'idle';
        const blockIdx = state.current_block_index || 0;
        const block = state.current_block;
        const nextBlock = state.next_block;
        const totalBlocks = state.total_blocks || 0;
        const serverElapsed = state.elapsed || 0;

        // Detect block change
        const blockChanged = !prev || prev.current_block_index !== blockIdx;
        if (blockChanged && prev) flashTransition();

        // Reset local timer on block change
        if (blockChanged || Math.abs(localElapsed - serverElapsed) > 2) {
            localElapsed = serverElapsed;
        }

        // Update body class
        document.body.className = `state-${status}`;

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
            stopTicker();
            return;
        }

        if (status === 'finished') {
            if (idleScreen) idleScreen.style.display = 'none';
            if (liveScreen) liveScreen.style.display = 'none';
            if (finishedScreen) finishedScreen.style.display = 'flex';
            if (pausedOverlay) pausedOverlay.classList.remove('visible');
            stopTicker();
            return;
        }

        // Live or paused
        if (idleScreen) idleScreen.style.display = 'none';
        if (ambientRings) ambientRings.style.display = 'none';
        if (liveScreen) liveScreen.style.display = 'grid';
        if (finishedScreen) finishedScreen.style.display = 'none';
        if (pausedOverlay) pausedOverlay.classList.toggle('visible', status === 'paused');

        // PREPARATE overlay (Spotify intro countdown)
        const prepOverlay = document.getElementById('prep-overlay');
        const prepCountdown = document.getElementById('prep-countdown');
        const prepBlockName = document.getElementById('prep-block-name');
        const prepRemaining = state.prep_remaining || 0;
        if (prepOverlay) {
            if (prepRemaining > 0 && status === 'playing') {
                prepOverlay.style.display = 'flex';
                if (prepCountdown) prepCountdown.textContent = prepRemaining;
                if (prepBlockName && block) prepBlockName.textContent = block.name || '';
            } else {
                prepOverlay.style.display = 'none';
            }
        }

        // Session name
        const sessionNameEl = document.getElementById('display-session-name');
        if (sessionNameEl && state.session_name) sessionNameEl.textContent = state.session_name;

        // Update block display
        updateBlockDisplay(block, nextBlock, blockIdx, totalBlocks);

        // Ticker
        if (status === 'playing') {
            startTicker(block);
        } else {
            stopTicker();
            renderClock(block, localElapsed);
        }

        // Total progress
        updateTotalProgress(state);

        // Block dots
        updateBlockDots(blockIdx, totalBlocks);
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
                exEl.textContent = exs[0]?.name || exs[0] || block.name;
                exEl.style.animation = 'none';
                requestAnimationFrame(() => exEl.style.animation = '');
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

    function startTicker(block) {
        if (clockTicker) clearInterval(clockTicker);
        clockTicker = setInterval(() => {
            localElapsed++;
            renderClock(block || currentState?.current_block, localElapsed);
        }, 1000);
    }

    function stopTicker() {
        if (clockTicker) { clearInterval(clockTicker); clockTicker = null; }
    }

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
            const roundDur = workSecs + restSecs;
            const phaseElapsed = elapsed % roundDur;
            const inWork = phaseElapsed < workSecs;

            document.body.className = inWork ? 'state-work' : 'state-rest';

            if (restOverlay) {
                // Only show REST overlay if NOT in PREPARATE phase
                const prepVisible = document.getElementById('prep-overlay')?.style.display === 'flex';
                if (!inWork && !prepVisible) {
                    const restRemaining = roundDur - phaseElapsed;
                    restOverlay.style.display = 'flex';
                    if (restCountdown) restCountdown.textContent = restRemaining;
                } else {
                    restOverlay.style.display = 'none';
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
            case 'interval': return (cfg.rounds || 1) * ((cfg.work || 40) + (cfg.rest || 20));
            case 'tabata': return (cfg.rounds || 8) * 30;
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
