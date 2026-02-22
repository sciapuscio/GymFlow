/**
 * GymFlow Sync Server — Socket.IO Real-Time Session Brain
 * The ONLY clock in the system. Clients receive ticks, they never count.
 * Port: 3001
 */
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');

// ─── Config ────────────────────────────────────────────────────────────────
const PORT = 3001;
const DB = { host: 'localhost', user: 'root', password: '', database: 'gymflow' };

// ─── Setup ─────────────────────────────────────────────────────────────────
const app = express();
const server = http.createServer(app);
const io = new Server(server, { cors: { origin: '*', methods: ['GET', 'POST'] } });
app.use(express.json());

// ─── DB Pool ───────────────────────────────────────────────────────────────
const pool = mysql.createPool({ ...DB, waitForConnections: true, connectionLimit: 10 });

// ─── In-Memory State ───────────────────────────────────────────────────────
// sessionStates: Map<salaId, sessionState>
const sessionStates = new Map();
// timers: Map<salaId, intervalId>  (WOD ticker)
const timers = new Map();
// clockTimers: Map<salaId, intervalId>  (standalone clock ticker)
const clockTimers = new Map();

function _startClockTimer(salaId) {
    _stopClockTimer(salaId);
    const id = setInterval(() => {
        const st = sessionStates.get(salaId);
        if (!st || !st.clockTimer) { clearInterval(id); return; }
        const ct = st.clockTimer;
        if (!ct.running) return;

        // ── PREP PHASE ─────────────────────────────────────────────────────
        if (ct.prep > 0 && ct.prepElapsed < ct.prep) {
            ct.prepElapsed++;
            if (ct.prepElapsed >= ct.prep) {
                ct.phase = ct.mode === 'tabata' ? 'work' : 'main';
            } else {
                ct.phase = 'prep';
            }
            broadcast(salaId);
            return;
        }
        ct.phase = ct.phase || 'main';

        // ── TABATA MODE ────────────────────────────────────────────────────
        if (ct.mode === 'tabata') {
            const work = ct.work || 20;
            const rest = ct.rest || 10;
            const total = ct.rounds || 8;
            ct.phaseElapsed = (ct.phaseElapsed || 0) + 1;

            if (ct.phase === 'work') {
                if (ct.phaseElapsed >= work) {
                    const doneRound = (ct.currentRound || 0) + 1;
                    if (doneRound >= total) {
                        // All rounds done
                        ct.running = false;
                        ct.phase = 'done';
                        _stopClockTimer(salaId);
                    } else {
                        ct.currentRound = doneRound;
                        ct.phase = 'rest';
                        ct.phaseElapsed = 0;
                    }
                }
            } else if (ct.phase === 'rest') {
                if (ct.phaseElapsed >= rest) {
                    ct.phase = 'work';
                    ct.phaseElapsed = 0;
                }
            }
            ct.elapsed++;
            broadcast(salaId);
            return;
        }

        // ── COUNTDOWN MODE ─────────────────────────────────────────────────
        if (ct.mode === 'countdown') {
            ct.elapsed = Math.min(ct.duration, ct.elapsed + 1);
            if (ct.elapsed >= ct.duration) {
                ct.running = false;
                ct.phase = 'done';
                _stopClockTimer(salaId);
            }
            broadcast(salaId);
            return;
        }

        // ── COUNT-UP MODE (default) ────────────────────────────────────────
        ct.elapsed++;
        if (ct.duration > 0 && ct.elapsed >= ct.duration) {
            ct.running = false;
            ct.phase = 'done';
            _stopClockTimer(salaId);
        }
        broadcast(salaId);
    }, 1000);
    clockTimers.set(salaId, id);
}
function _stopClockTimer(salaId) {
    const id = clockTimers.get(salaId);
    if (id) { clearInterval(id); clockTimers.delete(salaId); }
}
function _ensureClockTimer(st) {
    if (!st.clockTimer) {
        st.clockTimer = {
            mode: 'countdown', duration: 300, elapsed: 0, running: false,
            prep: 10, prepElapsed: 0, phase: 'idle',
            work: 20, rest: 10, rounds: 8, currentRound: 0, phaseElapsed: 0,
        };
    }
}

// ─── Helpers ───────────────────────────────────────────────────────────────
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

function buildTick(st) {
    const blocks = st.blocks || [];
    const ci = st.currentBlockIndex;
    return {
        session_id: st.sessionId,
        session_name: st.sessionName,
        status: st.status,
        current_block_index: ci,
        current_block: blocks[ci] || null,
        next_block: blocks[ci + 1] || null,
        total_blocks: blocks.length,
        elapsed: st.elapsed,
        prep_remaining: st.prepRemaining,
        total_duration: st.totalDuration,
        auto_play: st.autoPlay !== false,
        wod_overlay: st.wodOverlay || { active: false, blocks: [] },
        clock_mode: st.clockMode || { active: false, mode: 'session', config: {} },
        clock_timer: st.clockTimer || { mode: 'countdown', duration: 300, elapsed: 0, running: false },
        server_ts: Date.now(),
    };
}

function broadcast(salaId) {
    const st = sessionStates.get(salaId);
    if (!st) return;
    io.to(`sala:${salaId}`).emit('session:tick', buildTick(st));
}

// ─── Persist to MySQL ──────────────────────────────────────────────────────
async function persistState(st, action) {
    try {
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
        if (action === 'play') {
            await pool.execute(
                "UPDATE gym_sessions SET status='playing', block_resumed_at=?, current_block_elapsed=?, started_at=COALESCE(started_at,?), updated_at=? WHERE id=?",
                [now, st.elapsed, now, now, st.sessionId]
            );
        } else if (action === 'pause') {
            await pool.execute(
                "UPDATE gym_sessions SET status='paused', current_block_elapsed=?, block_resumed_at=NULL, updated_at=? WHERE id=?",
                [st.elapsed, now, st.sessionId]
            );
        } else if (action === 'stop') {
            await pool.execute(
                "UPDATE gym_sessions SET status='finished', current_block_elapsed=?, block_resumed_at=NULL, finished_at=?, updated_at=? WHERE id=?",
                [st.elapsed, now, now, st.sessionId]
            );
        } else if (action === 'block') {
            await pool.execute(
                "UPDATE gym_sessions SET current_block_index=?, current_block_elapsed=0, block_resumed_at=?, updated_at=? WHERE id=?",
                [st.currentBlockIndex, st.status === 'playing' ? now : null, now, st.sessionId]
            );
        }
        // Push sync_state for SSE fallback compatibility
        const tick = buildTick(st);
        await pool.execute(
            "INSERT INTO sync_state (sala_id, session_id, state_json, updated_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE session_id=VALUES(session_id), state_json=VALUES(state_json), updated_at=VALUES(updated_at)",
            [st.salaId, st.sessionId, JSON.stringify(tick), now]
        );
    } catch (e) {
        console.error('[DB] Persist error:', e.message);
    }
}

// ─── Load Session from DB ──────────────────────────────────────────────────
async function loadSession(sessionId) {
    const [rows] = await pool.execute(
        "SELECT gs.*, s.id as sala_id_val FROM gym_sessions gs LEFT JOIN salas s ON gs.sala_id = s.id WHERE gs.id = ?",
        [sessionId]
    );
    if (!rows.length) return null;
    const row = rows[0];

    let elapsed = parseInt(row.current_block_elapsed) || 0;
    // If was playing when server restarted, calculate real elapsed
    if (row.status === 'playing' && row.block_resumed_at) {
        const resumedTs = new Date(row.block_resumed_at).getTime();
        elapsed += Math.floor((Date.now() - resumedTs) / 1000);
    }

    return {
        sessionId: parseInt(row.id),
        salaId: parseInt(row.sala_id),
        sessionName: row.name,
        // On fresh server load, never auto-resume 'playing' — require explicit control:play
        status: row.status === 'playing' ? 'paused' : (row.status || 'idle'),
        blocks: JSON.parse(row.blocks_json || '[]'),
        currentBlockIndex: parseInt(row.current_block_index) || 0,
        elapsed,
        prepRemaining: 0,
        totalDuration: parseInt(row.total_duration) || 0,
        autoPlay: true,  // true = auto-advance blocks; false = pause at end of each block
        clockMode: { active: false, mode: 'session', config: {} },
    };
}

// ─── Timer Logic ───────────────────────────────────────────────────────────
function startTimer(salaId) {
    stopTimer(salaId); // clear any existing
    const intervalId = setInterval(() => {
        const st = sessionStates.get(salaId);
        if (!st || st.status !== 'playing') { stopTimer(salaId); return; }

        if (st.prepRemaining > 0) {
            st.prepRemaining--;
        } else {
            st.elapsed++;
            const block = st.blocks[st.currentBlockIndex];
            const dur = computeBlockDuration(block);

            if (st.elapsed >= dur) {
                if (st.autoPlay !== false) {
                    // ── AUTO-PLAY MODE: advance immediately and keep going ─────
                    if (st.currentBlockIndex < st.blocks.length - 1) {
                        st.currentBlockIndex++;
                        st.elapsed = 0;
                        st.prepRemaining = 0;
                        persistState(st, 'block');
                        io.to(`sala:${salaId}`).emit('session:block_change', {
                            index: st.currentBlockIndex,
                            block: st.blocks[st.currentBlockIndex],
                            next_block: st.blocks[st.currentBlockIndex + 1] || null,
                        });
                    } else {
                        // Session finished
                        st.status = 'finished';
                        stopTimer(salaId);
                        persistState(st, 'stop');
                    }
                } else {
                    // ── MANUAL MODE: pause at end of block, wait for Play ─────
                    if (st.currentBlockIndex < st.blocks.length - 1) {
                        st.currentBlockIndex++;
                        st.elapsed = 0;
                        st.prepRemaining = 0;
                        st.status = 'paused';
                        stopTimer(salaId);
                        persistState(st, 'block');
                        persistState(st, 'pause');
                        // Broadcast paused state FIRST so clients update isPlaying
                        // before processing block_change (avoids spurious Spotify auto-play)
                        broadcast(salaId);
                        io.to(`sala:${salaId}`).emit('session:block_change', {
                            index: st.currentBlockIndex,
                            block: st.blocks[st.currentBlockIndex],
                            next_block: st.blocks[st.currentBlockIndex + 1] || null,
                        });
                        // Notify instructor that a block ended waiting for play
                        io.to(`sala:${salaId}`).emit('session:block_held', {
                            index: st.currentBlockIndex,
                            block: st.blocks[st.currentBlockIndex],
                        });
                    } else {
                        // Session finished
                        st.status = 'finished';
                        stopTimer(salaId);
                        persistState(st, 'stop');
                    }
                }
            }
        }

        broadcast(salaId);
    }, 1000);

    timers.set(salaId, intervalId);
}

function stopTimer(salaId) {
    const t = timers.get(salaId);
    if (t) { clearInterval(t); timers.delete(salaId); }
}

// ─── Socket.IO Events ──────────────────────────────────────────────────────
io.on('connection', (socket) => {
    console.log(`[Socket] Connected: ${socket.id}`);

    // ── Join as INSTRUCTOR ──────────────────────────────────────────────────
    socket.on('join:session', async ({ session_id, sala_id }) => {
        try {
            let st = sessionStates.get(sala_id);
            if (!st || st.sessionId !== session_id) {
                // No cached state or different session — full load
                st = await loadSession(session_id);
                if (!st) { socket.emit('error', 'Session not found'); return; }
                sessionStates.set(sala_id, st);
            } else {
                // State exists — always refresh blocks from DB so builder edits (reps, etc.) are visible
                const fresh = await loadSession(session_id);
                if (fresh) st.blocks = fresh.blocks;
            }
            socket.join(`sala:${sala_id}`);
            socket.data.salaId = sala_id;
            socket.data.role = 'instructor';
            socket.data.sessionId = session_id;
            // Resume timer if was already playing on load
            if (st.status === 'playing' && !timers.has(sala_id)) {
                startTimer(sala_id);
            }
            socket.emit('session:state', buildTick(st));
            console.log(`[Socket] Instructor joined sala ${sala_id}, session ${session_id}`);
        } catch (e) {
            console.error('[Socket] join:session error:', e.message);
            socket.emit('error', e.message);
        }
    });

    // ── Join as DISPLAY ─────────────────────────────────────────────────────
    socket.on('join:sala', ({ sala_id }) => {
        socket.join(`sala:${sala_id}`);
        socket.data.salaId = sala_id;
        socket.data.role = 'display';
        const st = sessionStates.get(sala_id);
        if (st) socket.emit('session:state', buildTick(st));
        console.log(`[Socket] Display joined sala ${sala_id}`);
    });

    // ── Join as SYSTEM (admin/instructor dashboard pages) ───────────────────
    // No sala room — used only to receive system:broadcast notifications.
    socket.on('join:system', ({ role }) => {
        socket.data.role = role || 'admin'; // admin | instructor | superadmin
        console.log(`[Socket] System join: ${socket.id} as ${socket.data.role}`);
    });

    // ── Control: PLAY ───────────────────────────────────────────────────────
    socket.on('control:play', async ({ prep_remaining = 0 } = {}) => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        st.status = 'playing';
        st.prepRemaining = prep_remaining;
        startTimer(sala_id);
        await persistState(st, 'play');
        broadcast(sala_id);
    });

    // ── Control: PAUSE ──────────────────────────────────────────────────────
    socket.on('control:pause', async () => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        st.status = 'paused';
        st.prepRemaining = 0;
        stopTimer(sala_id);
        await persistState(st, 'pause');
        broadcast(sala_id);
    });

    // ── Control: STOP ───────────────────────────────────────────────────────
    socket.on('control:stop', async () => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        st.status = 'finished';
        stopTimer(sala_id);
        await persistState(st, 'stop');
        broadcast(sala_id);
    });

    // ── Control: SKIP ───────────────────────────────────────────────────────
    socket.on('control:skip', async () => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        const wasPlaying = st.status === 'playing';
        stopTimer(sala_id);
        st.currentBlockIndex = Math.min(st.currentBlockIndex + 1, st.blocks.length - 1);
        st.elapsed = 0;
        st.prepRemaining = 0;
        // If finished + not playing, reset to paused so display shows preview
        if (st.status === 'finished') st.status = 'paused';
        await persistState(st, 'block');
        io.to(`sala:${sala_id}`).emit('session:block_change', {
            index: st.currentBlockIndex,
            block: st.blocks[st.currentBlockIndex],
            next_block: st.blocks[st.currentBlockIndex + 1] || null,
        });
        if (wasPlaying) startTimer(sala_id);
        broadcast(sala_id);
    });

    // ── Control: PREV ───────────────────────────────────────────────────────
    socket.on('control:prev', async () => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        const wasPlaying = st.status === 'playing';
        stopTimer(sala_id);
        st.currentBlockIndex = Math.max(st.currentBlockIndex - 1, 0);
        st.elapsed = 0;
        st.prepRemaining = 0;
        // If navigating back from finished, reset to paused so display shows preview
        if (st.status === 'finished') st.status = 'paused';
        await persistState(st, 'block');
        io.to(`sala:${sala_id}`).emit('session:block_change', {
            index: st.currentBlockIndex,
            block: st.blocks[st.currentBlockIndex],
            next_block: st.blocks[st.currentBlockIndex + 1] || null,
        });
        if (wasPlaying) startTimer(sala_id);
        broadcast(sala_id);
    });

    // ── Control: GOTO ───────────────────────────────────────────────────────
    socket.on('control:goto', async ({ index, prep_remaining = 0 }) => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        const wasPlaying = st.status === 'playing';
        stopTimer(sala_id);
        st.currentBlockIndex = Math.max(0, Math.min(index, st.blocks.length - 1));
        st.elapsed = 0;
        st.prepRemaining = prep_remaining;
        // If jumping from a finished session, or in manual mode, go to paused.
        if (st.status === 'finished' || st.autoPlay === false) st.status = 'paused';
        await persistState(st, 'block');
        if (st.status === 'paused') await persistState(st, 'pause');
        // If a block was already playing when the instructor jumped:
        // - AUTO mode: restart immediately with prep countdown.
        // - MANUAL mode: stays paused — instructor must press Play.
        if (wasPlaying && st.autoPlay !== false) {
            st.status = 'playing';
            await persistState(st, 'play');
            startTimer(sala_id);
        }
        // Broadcast AFTER timer starts so clients get status=playing from the first tick
        broadcast(sala_id);
        io.to(`sala:${sala_id}`).emit('session:block_change', {
            index: st.currentBlockIndex,
            block: st.blocks[st.currentBlockIndex],
            next_block: st.blocks[st.currentBlockIndex + 1] || null,
        });
    });

    // ── Control: EXTEND ─────────────────────────────────────────────────────
    socket.on('control:extend', ({ seconds = 30 }) => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        const block = st.blocks[st.currentBlockIndex];
        if (block?.config) {
            block.config.duration = (block.config.duration || computeBlockDuration(block)) + seconds;
        }
        broadcast(sala_id);
    });

    // ── Control: SET AUTOPLAY ─────────────────────────────────────────────────
    socket.on('control:set_autoplay', ({ enabled }) => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        st.autoPlay = !!enabled;
        console.log(`[Socket] Sala ${sala_id} autoPlay → ${st.autoPlay}`);
        broadcast(sala_id);  // let display know too
    });

    // ── Control: WOD OVERLAY ──────────────────────────────────────────────────
    socket.on('control:wod_overlay', ({ active, blocks }) => {
        const sala_id = socket.data.salaId;
        if (!sala_id) return;
        // Persist state so reconnecting instructor/display gets correct status
        const st = sessionStates.get(sala_id);
        if (st) st.wodOverlay = { active: !!active, blocks: active ? (blocks || []) : [] };
        io.to(`sala:${sala_id}`).emit('display:wod_overlay', { active: !!active, blocks: blocks || [] });
        console.log(`[Socket] Sala ${sala_id} WOD overlay → ${active}`);
    });

    // ── Control: CLOCK MODE ───────────────────────────────────────────────────
    // Instructor sends { active, mode, config } to toggle/configure the display clock.
    // mode: 'session' | 'countdown' | 'countup'
    // config: { work, rest, rounds, duration, ... } (same schema as block config)
    socket.on('control:clock_mode', ({ active, mode, config } = {}) => {
        const sala_id = socket.data.salaId;
        if (!sala_id) return;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        st.clockMode = {
            active: !!active,
            mode: mode || 'session',
            config: config || {},
        };
        broadcast(sala_id);  // full tick so display gets clock_mode in the proper payload
        console.log(`[Socket] Sala ${sala_id} clock_mode → active=${active} mode=${mode}`);
    });

    // ── Control: CLOCK FULLSCREEN ───────────────────────────────────────────────
    // Instructor toggles the display clock into fullscreen mode.
    // No state stored — just re-broadcast the command to display sockets.
    socket.on('control:clock_fs', ({ active } = {}) => {
        const sala_id = socket.data.salaId;
        if (!sala_id) return;
        // Emit directly to all sockets in this sala's room
        io.to(`sala:${sala_id}`).emit('clock:fs', { active: !!active });
        console.log(`[Socket] Sala ${sala_id} clock_fs → active=${active}`);
    });

    // ── Control: STANDALONE CLOCK TIMER ─────────────────────────────────────
    socket.on('control:clock_timer_play', () => {
        const sala_id = socket.data.salaId;
        if (!sala_id) return;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        _ensureClockTimer(st);
        st.clockTimer.running = !st.clockTimer.running;
        if (st.clockTimer.running) _startClockTimer(sala_id);
        else _stopClockTimer(sala_id);
        broadcast(sala_id);
        console.log(`[Socket] Sala ${sala_id} clock_timer → running=${st.clockTimer.running}`);
    });

    socket.on('control:clock_timer_stop', () => {
        const sala_id = socket.data.salaId;
        if (!sala_id) return;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        _ensureClockTimer(st);
        st.clockTimer.running = false;
        _stopClockTimer(sala_id);
        broadcast(sala_id);
    });

    socket.on('control:clock_timer_reset', () => {
        const sala_id = socket.data.salaId;
        if (!sala_id) return;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        _ensureClockTimer(st);
        st.clockTimer.elapsed = 0;
        st.clockTimer.running = false;
        _stopClockTimer(sala_id);
        broadcast(sala_id);
    });

    socket.on('control:clock_timer_cfg', ({ mode, duration, prep, work, rest, rounds } = {}) => {
        const sala_id = socket.data.salaId;
        if (!sala_id) return;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        _ensureClockTimer(st);
        const ct = st.clockTimer;
        if (mode !== undefined) ct.mode = mode;
        if (duration !== undefined) ct.duration = Math.max(5, parseInt(duration) || 300);
        if (prep !== undefined) ct.prep = Math.max(0, parseInt(prep) || 0);
        if (work !== undefined) ct.work = Math.max(1, parseInt(work) || 20);
        if (rest !== undefined) ct.rest = Math.max(0, parseInt(rest) || 10);
        if (rounds !== undefined) ct.rounds = Math.max(1, parseInt(rounds) || 8);
        // Full reset
        ct.elapsed = 0; ct.prepElapsed = 0; ct.phase = 'idle';
        ct.currentRound = 0; ct.phaseElapsed = 0; ct.running = false;
        _stopClockTimer(sala_id);
        broadcast(sala_id);
        console.log(`[Socket] Sala ${sala_id} clock_timer_cfg → mode=${ct.mode} dur=${ct.duration} prep=${ct.prep}`);
    });

    // ── Disconnect ──────────────────────────────────────────────────────────
    socket.on('disconnect', () => {
        console.log(`[Socket] Disconnected: ${socket.id} (${socket.data.role || '?'})`);
    });
});

// ─── HTTP: PHP Notification Endpoint ─────────────────────────────────────
// Called by PHP after CRUD operations to reload session into memory
app.get('/internal/reload', async (req, res) => {
    const session_id = parseInt(req.query.session_id);
    if (!session_id) return res.json({ ok: false, error: 'Missing session_id' });
    try {
        const st = await loadSession(session_id);
        if (!st) return res.json({ ok: false, error: 'Session not found' });
        const salaId = st.salaId;
        if (!salaId) return res.json({ ok: true, note: 'No sala assigned' });
        // Merge: keep running elapsed if timer is already going
        const existing = sessionStates.get(salaId);
        if (existing && existing.sessionId === session_id && timers.has(salaId)) {
            // Don't reset elapsed — server is already counting
            st.elapsed = existing.elapsed;
            st.prepRemaining = existing.prepRemaining;
        }
        sessionStates.set(salaId, st);
        if (st.status === 'playing' && !timers.has(salaId)) startTimer(salaId);
        if (st.status !== 'playing') stopTimer(salaId);
        broadcast(salaId);
        res.json({ ok: true });
    } catch (e) {
        console.error('[HTTP] reload error:', e.message);
        res.json({ ok: false, error: e.message });
    }
});

// ─── HTTP: Superadmin Broadcast ───────────────────────────────────────────
// Called by PHP /api/broadcast.php after superadmin sends a system message.
// Emits 'system:broadcast' to every connected socket EXCEPT display roles.
app.post('/internal/broadcast', (req, res) => {
    const message = (req.body.message || '').trim();
    const type = ['info', 'warning', 'error'].includes(req.body.type) ? req.body.type : 'info';
    if (!message) return res.json({ ok: false, error: 'Empty message' });

    let count = 0;
    for (const [, socket] of io.sockets.sockets) {
        if (socket.data.role !== 'display') {
            socket.emit('system:broadcast', { message, type, ts: Date.now() });
            count++;
        }
    }
    console.log(`[Broadcast] Sent to ${count} sockets: "${message.slice(0, 60)}"`);
    res.json({ ok: true, recipients: count });
});

app.get('/health', (_, res) => res.json({ ok: true, sessions: sessionStates.size }));

// ─── Start ─────────────────────────────────────────────────────────────────
server.listen(PORT, () => {
    console.log(`\n╔═══════════════════════════════════════╗`);
    console.log(`║   GymFlow Sync Server on :${PORT}      ║`);
    console.log(`╚═══════════════════════════════════════╝\n`);
    pool.getConnection().then(c => {
        console.log(`[DB] Connected to MySQL: ${DB.database}`);
        c.release();
    }).catch(e => console.error('[DB] Connection failed:', e.message));
});
