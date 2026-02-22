// GymFlow — Display Sync Engine (Socket.IO Client)
(function () {
    let currentState = null;
    let localElapsed = 0;  // filled by server ticks — no local ticker needed
    let stickWidget = null;  // StickmanWidget instance
    let _previewBlock = null; // set by session:block_change while paused

    function init() {
        connectSocket();
        // Init stickman widget (deferred so scripts load first)
        setTimeout(() => {
            const el = document.getElementById('stickman-container');
            if (el && typeof StickmanWidget !== 'undefined') {
                stickWidget = new StickmanWidget(el, { size: 'normal' });
            }
        }, 200);
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
        socket.on('session:tick', (tick) => applyState(tick));

        // When instructor jumps to a block while paused → show "¡PREPARATE! [exercise]"
        // instead of leaving the PAUSA screen up. _previewBlock keeps this alive across ticks.
        socket.on('session:block_change', ({ block }) => {
            if (!block) return;
            _previewBlock = block;
            _showPreviewOverlay(block);
        });

        socket.on('disconnect', () => {
            console.warn('[DisplaySync] Disconnected, reconnecting...');
        });

        // ── WOD Summary Overlay ──────────────────────────────────────────────
        socket.on('display:wod_overlay', ({ active, blocks }) => {
            _renderWodOverlay(active, blocks);
        });
    }   // ← closes connectSocket()

    // Shared WOD overlay renderer (called from socket event & applyState reconnect)
    function _renderWodOverlay(active, blocks) {
        const overlay = document.getElementById('wod-overlay');
        if (!overlay) return;
        if (!active) { overlay.style.display = 'none'; return; }

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
            listEl.innerHTML = blocks.map((b, i) => {
                const col = typeColors[b.type] || '#888';
                const exs = b.exercises || [];
                const exLine = exs.length
                    ? exs.map(e => {
                        const n = e?.name || (typeof e === 'string' ? e : '');
                        const r = e?.reps ? `${e.reps}×` : '';
                        return r + n;
                    }).filter(Boolean).join('  ·  ')
                    : '';
                const cfg = b.config || {};
                const metaParts = [];
                if (cfg.rounds) metaParts.push(`${cfg.rounds} rondas`);
                if (cfg.sets) metaParts.push(`${cfg.sets} series × ${cfg.reps || '?'} reps`);
                if (cfg.work) metaParts.push(`${cfg.work}s / ${cfg.rest || 0}s`);
                if (cfg.duration) metaParts.push(formatDuration(cfg.duration));
                const meta = metaParts.join(' · ');
                return `<div style="
                    display:flex;align-items:center;gap:16px;
                    padding:clamp(12px,1.8vh,20px) clamp(14px,2vw,24px);
                    background:rgba(255,255,255,0.04);
                    border:1px solid rgba(255,255,255,0.08);
                    border-left:4px solid ${col};
                    border-radius:10px;
                ">
                    <div style="flex-shrink:0;width:clamp(24px,2.5vw,36px);height:clamp(24px,2.5vw,36px);
                        border-radius:50%;background:${col}22;
                        display:flex;align-items:center;justify-content:center;
                        font-family:'Bebas Neue',sans-serif;font-size:clamp(12px,1.3vw,18px);color:${col}">
                        ${i + 1}
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:clamp(9px,0.9vw,12px);font-weight:700;letter-spacing:.12em;
                            text-transform:uppercase;color:${col};margin-bottom:4px">
                            ${(b.type || '').toUpperCase()}
                        </div>
                        <div style="font-size:clamp(16px,2vw,26px);font-weight:700;color:#fff;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                            ${b.name || '—'}
                        </div>
                        ${exLine ? `<div style="font-size:clamp(11px,1.1vw,15px);color:rgba(255,255,255,0.5);margin-top:3px;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${exLine}</div>` : ''}
                    </div>
                    ${meta ? `<div style="flex-shrink:0;font-size:clamp(11px,1.1vw,14px);
                        color:rgba(255,255,255,0.4);font-weight:600;text-align:right">${meta}</div>` : ''}
                </div>`;
            }).join('');
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
        const prev = currentState;
        currentState = state;

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
            // Show current block name on idle screen if one is loaded
            const idleLabel = document.getElementById('idle-class-label');
            if (idleLabel && block) idleLabel.textContent = block.name || '';
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
                } else {
                    // Single exercise or circuit station
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
        container.innerHTML = exs.map((ex, i) => {
            const exName = ex?.name || (typeof ex === 'string' ? ex : '?');
            // Show reps prefix for list-based blocks (amrap/fortime/series/emom)
            const showReps = ['amrap', 'emom', 'fortime', 'series'].includes(block.type);
            const repsPrefix = (showReps && ex?.reps) ? `${ex.reps} × ` : '';
            const name = repsPrefix + exName;
            const isActive = i === activeIdx;
            return `<span style="
            display:inline-flex;align-items:center;
            padding:10px 32px;
            border-radius:999px;
            font-size:clamp(20px,2.8vw,38px);
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
