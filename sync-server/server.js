/**
 * GymFlow Sync Server — Socket.IO Real-Time Session Brain
 * The ONLY clock in the system. Clients receive ticks, they never count.
 */
const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '.env.local') });

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const mysql = require('mysql2/promise');

// ─── Config ────────────────────────────────────────────────────────────────
const PORT = parseInt(process.env.PORT) || 3001;
const DB = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'gymflow',
};

// ─── Setup ─────────────────────────────────────────────────────────────────
const ALLOWED_ORIGINS = [
    'https://sistema.gymflow.com.ar',
    'https://training.access.ly',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
    ...(process.env.EXTRA_ORIGINS ? process.env.EXTRA_ORIGINS.split(',') : []),
];

const app = express();
const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: (origin, cb) => {
            // Allow no-origin (same-host curl/PHP) and whitelisted origins
            if (!origin || ALLOWED_ORIGINS.includes(origin)) return cb(null, true);
            cb(new Error('Socket CORS: origin not allowed — ' + origin));
        },
        methods: ['GET', 'POST'],
        credentials: true,
    }
});
app.use(express.json());

// ─── Security: /internal/* only reachable from loopback ──────────────────────
app.use('/internal', (req, res, next) => {
    const ip = req.socket.remoteAddress;
    if (ip === '127.0.0.1' || ip === '::1' || ip === '::ffff:127.0.0.1') return next();
    console.warn(`[Security] Blocked /internal request from ${ip}`);
    return res.status(403).json({ ok: false, error: 'Forbidden' });
});

// ─── DB Pool ───────────────────────────────────────────────────────────────
const pool = mysql.createPool({ ...DB, waitForConnections: true, connectionLimit: 10 });

// ─── In-Memory State ───────────────────────────────────────────────────────
// sessionStates: Map<salaId, sessionState>
const sessionStates = new Map();
// timers: Map<salaId, intervalId>  (WOD ticker)
const timers = new Map();
// clockTimers: Map<salaId, intervalId>  (standalone clock ticker)
const clockTimers = new Map();
// graceTimers: Map<salaId, timeoutId>  (instructor disconnect grace period)
const graceTimers = new Map();

const INSTRUCTOR_GRACE_MS = 30_000; // 30 s before auto-detach

// Helper: detach a sala (clear state + notify display + update DB)
async function _autoDetachSala(salaId, sessionId, reason) {
    console.log(`[AutoDetach] Sala ${salaId} — ${reason}`);
    stopTimer(salaId);
    _stopClockTimer(salaId);
    sessionStates.delete(salaId);
    graceTimers.delete(salaId);
    io.to(`sala:${salaId}`).emit('session:detach');
    mon('auto-detach', `🔌 Sala ${salaId} auto-desacoplada (${reason})`, { salaId, sessionId });
    // Update DB: set sala_id = NULL on the session
    try {
        await pool.execute('UPDATE gym_sessions SET sala_id = NULL WHERE id = ? AND sala_id = ?', [sessionId, salaId]);
        await pool.execute('UPDATE salas SET current_session_id = NULL WHERE id = ?', [salaId]);
    } catch (e) {
        console.error('[AutoDetach] DB error:', e.message);
    }
}

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
            const prevPhase = ct.phase;

            if (ct.phase === 'work') {
                if (ct.phaseElapsed >= work) {
                    const doneRound = (ct.currentRound || 0) + 1;
                    if (doneRound >= total) {
                        // All rounds done
                        ct.running = false;
                        ct.phase = 'done';
                        _stopClockTimer(salaId);
                        mon('phase', `✅ Tabata sala ${salaId} TERMINADO (${total} rondas)`, { sala_id: salaId, rounds: total });
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

            // Emit phase-change log only on transitions (not every tick)
            if (ct.phase !== prevPhase && ct.phase !== 'done') {
                if (ct.phase === 'work') {
                    mon('phase', `💪 WORK  sala ${salaId} · ronda ${(ct.currentRound || 0) + 1}/${total} (${work}s)`, { sala_id: salaId, round: ct.currentRound, work });
                } else if (ct.phase === 'rest') {
                    mon('phase', `😴 REST  sala ${salaId} · ronda ${ct.currentRound}/${total} (${rest}s)`, { sala_id: salaId, round: ct.currentRound, rest });
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
        instructor_name: st.instructorName || null,
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
        `SELECT gs.*, s.id as sala_id_val, u.name as instructor_name
         FROM gym_sessions gs
         LEFT JOIN salas s ON gs.sala_id = s.id
         LEFT JOIN users u ON gs.instructor_id = u.id
         WHERE gs.id = ?`,
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
        instructorName: row.instructor_name || null,
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

            // ── Phase tracking for tabata/interval blocks ───────────────────
            if (block && (block.type === 'tabata' || block.type === 'interval')) {
                const c = block.config || {};
                const work = c.work || (block.type === 'tabata' ? 20 : 40);
                const rest = c.rest || (block.type === 'tabata' ? 10 : 20);
                const cycle = work + rest;
                const posInCycle = st.elapsed % cycle;
                const currentPhase = posInCycle < work ? 'work' : 'rest';
                const rounds = c.rounds || (block.type === 'tabata' ? 8 : 1);
                const currentRound = Math.floor(st.elapsed / cycle) + 1;

                if (currentPhase !== st._blockPhase) {
                    st._blockPhase = currentPhase;
                    const blkLabel = `"${block.name || block.type}"`;
                    if (currentPhase === 'work') {
                        mon('phase', `💪 WORK  sala ${salaId} · ${blkLabel} · ronda ${currentRound}/${rounds} (${work}s)`, { sala_id: salaId, round: currentRound, work });
                    } else {
                        mon('phase', `😴 REST  sala ${salaId} · ${blkLabel} · ronda ${currentRound - 1}/${rounds} (${rest}s)`, { sala_id: salaId, round: currentRound - 1, rest });
                    }
                }
            } else {
                // Reset phase state when on a non-tabata block
                st._blockPhase = null;
            }
            // ───────────────────────────────────────────────────────────────

            if (st.elapsed >= dur) {
                if (st.autoPlay !== false) {
                    // ── AUTO-PLAY MODE: advance immediately and keep going ─────
                    if (st.currentBlockIndex < st.blocks.length - 1) {
                        const prevBlock = st.blocks[st.currentBlockIndex];
                        st.currentBlockIndex++;
                        st.elapsed = 0;
                        st.prepRemaining = 0;
                        persistState(st, 'block');
                        io.to(`sala:${salaId}`).emit('session:block_change', {
                            index: st.currentBlockIndex,
                            block: st.blocks[st.currentBlockIndex],
                            next_block: st.blocks[st.currentBlockIndex + 1] || null,
                        });
                        const nextBlk = st.blocks[st.currentBlockIndex];
                        const nextLabel = nextBlk ? `"${nextBlk.name || nextBlk.type}" (${nextBlk.type})` : `bloque ${st.currentBlockIndex + 1}`;
                        const ctx = st.gymName ? ` · ${st.gymName} / ${st.salaName || 'sala ' + salaId}` : '';
                        mon('autoplay', `⏩ AUTO sala ${salaId}: "${prevBlock?.name || prevBlock?.type}" finalizó → ${nextLabel} [${st.currentBlockIndex + 1}/${st.blocks.length}]${ctx}`, { sala_id: salaId, block: st.currentBlockIndex });
                    } else {
                        // Session finished
                        st.status = 'finished';
                        stopTimer(salaId);
                        persistState(st, 'stop');
                        const ctxF = st.gymName ? ` · ${st.gymName} / ${st.salaName || 'sala ' + salaId}` : '';
                        mon('stop', `⏹ SESIÓN TERMINADA sala ${salaId} (auto)${ctxF}`, { sala_id: salaId });
                    }
                } else {
                    // ── MANUAL MODE: pause at end of block, wait for Play ───
                    if (st.currentBlockIndex < st.blocks.length - 1) {
                        const prevBlock = st.blocks[st.currentBlockIndex];
                        st.currentBlockIndex++;
                        st.elapsed = 0;
                        st.prepRemaining = 0;
                        st.status = 'paused';
                        stopTimer(salaId);
                        persistState(st, 'block');
                        persistState(st, 'pause');
                        broadcast(salaId);
                        io.to(`sala:${salaId}`).emit('session:block_change', {
                            index: st.currentBlockIndex,
                            block: st.blocks[st.currentBlockIndex],
                            next_block: st.blocks[st.currentBlockIndex + 1] || null,
                        });
                        io.to(`sala:${salaId}`).emit('session:block_held', {
                            index: st.currentBlockIndex,
                            block: st.blocks[st.currentBlockIndex],
                        });
                        const nextBlkM = st.blocks[st.currentBlockIndex];
                        const nextLabelM = nextBlkM ? `"${nextBlkM.name || nextBlkM.type}" (${nextBlkM.type})` : `bloque ${st.currentBlockIndex + 1}`;
                        const ctxM = st.gymName ? ` · ${st.gymName} / ${st.salaName || 'sala ' + salaId}` : '';
                        mon('autoplay', `⏸ MANUAL sala ${salaId}: "${prevBlock?.name || prevBlock?.type}" finalizó → ${nextLabelM} [${st.currentBlockIndex + 1}/${st.blocks.length}] — esperando PLAY${ctxM}`, { sala_id: salaId, block: st.currentBlockIndex });
                    } else {
                        // Session finished
                        st.status = 'finished';
                        stopTimer(salaId);
                        persistState(st, 'stop');
                        const ctxFM = st.gymName ? ` · ${st.gymName} / ${st.salaName || 'sala ' + salaId}` : '';
                        mon('stop', `⏹ SESIÓN TERMINADA sala ${salaId} (manual)${ctxFM}`, { sala_id: salaId });
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

// ─── Monitor broadcast helper ──────────────────────────────────────────────
// Emits a structured log entry to all sockets in the 'monitor' room.
function mon(type, msg, meta = {}) {
    const entry = { type, msg, meta, ts: Date.now() };
    io.to('monitor').emit('monitor:log', entry);
}

// ─── Socket.IO Events ──────────────────────────────────────────────────────
io.on('connection', (socket) => {
    const totalClients = io.sockets.sockets.size;
    console.log(`[Socket] Connected: ${socket.id}`);
    mon('connect', `Nueva conexión: ${socket.id}`, { id: socket.id, total: totalClients });

    // ── Join as MONITOR (superadmin console) ───────────────────────────────
    socket.on('join:monitor', () => {
        socket.join('monitor');
        socket.data.role = 'monitor';
        const totalClients = io.sockets.sockets.size;
        const activeSalas = sessionStates.size;
        console.log(`[Monitor] Console connected: ${socket.id}`);
        // Send a snapshot on connect
        socket.emit('monitor:log', { type: 'system', msg: `Monitor conectado. ${totalClients} conexiones activas, ${activeSalas} salas en memoria.`, ts: Date.now(), meta: {} });
    });

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

            // Cancel any pending auto-detach grace timer for this sala
            if (graceTimers.has(sala_id)) {
                clearTimeout(graceTimers.get(sala_id));
                graceTimers.delete(sala_id);
                console.log(`[AutoDetach] Grace timer cancelled — instructor reconnected to sala ${sala_id}`);
            }

            // Fetch instructor + gym + sala name for monitor logs
            try {
                const [info] = await pool.execute(
                    `SELECT u.name AS uname, g.name AS gname, sal.name AS sname
                     FROM gym_sessions gs
                     JOIN salas sal ON sal.id = gs.sala_id
                     JOIN gyms g ON g.id = sal.gym_id
                     JOIN users u ON u.id = gs.instructor_id
                     WHERE gs.id = ?`,
                    [session_id]
                );
                if (info.length) {
                    socket.data.userName = info[0].uname;
                    socket.data.gymName = info[0].gname;
                    socket.data.salaName = info[0].sname;
                    // Persist on st so timer-driven logs (auto-advance, stop) have context
                    st.gymName = info[0].gname;
                    st.salaName = info[0].sname;
                    st.instructorName = info[0].uname; // available in every buildTick
                }
            } catch (_) { /* non-critical */ }

            // Resume timer if was already playing on load
            if (st.status === 'playing' && !timers.has(sala_id)) {
                startTimer(sala_id);
            }
            socket.emit('session:state', buildTick(st));
            // Also broadcast to all display clients already connected to this sala room,
            // so sala.php transitions from waiting → idle immediately (no 20s poll needed).
            socket.to(`sala:${sala_id}`).emit('session:state', buildTick(st));
            mon('instructor', `Instructor ${socket.data.userName || '?'} [${socket.data.gymName || '?'}] → sala ${sala_id}`, { sala_id, session_id, socketId: socket.id });
            console.log(`[Socket] Instructor joined sala ${sala_id}, session ${session_id}`);
        } catch (e) {
            mon('error', `Error join:session: ${e.message}`, { socketId: socket.id });
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
        mon('display', `Pantalla conectada a sala ${sala_id}`, { sala_id, socketId: socket.id });
        console.log(`[Socket] Display joined sala ${sala_id}`);
    });

    // ── Join as SYSTEM (admin/instructor dashboard pages) ───────────────────
    // No sala room — used only to receive system:broadcast notifications.
    socket.on('join:system', ({ role }) => {
        socket.data.role = role || 'admin'; // admin | instructor | superadmin
        mon('system', `Dashboard conectado como ${socket.data.role}`, { role: socket.data.role, socketId: socket.id });
        console.log(`[Socket] System join: ${socket.id} as ${socket.data.role}`);
    });

    // ── Join as AGENDA DISPLAY ───────────────────────────────────────────────
    socket.on('join:agenda', ({ gym_id }) => {
        if (!gym_id) return;
        socket.join(`agenda:${gym_id}`);
        socket.data.role = 'agenda';
        socket.data.gymId = gym_id;
        console.log(`[Socket] Agenda display joined gym ${gym_id}`);
        mon('display', `Cartelera conectada · gym ${gym_id}`, { gym_id, socketId: socket.id });
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
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        const blk = st.blocks[st.currentBlockIndex];
        const blkLabel = blk ? `"${blk.name || blk.type}" (${blk.type})` : `bloque ${st.currentBlockIndex + 1}`;
        mon('play', `▶ PLAY  sala ${sala_id} · ${blkLabel} [${st.currentBlockIndex + 1}/${st.blocks.length}] · ${who}`, { sala_id, block: st.currentBlockIndex, user: socket.data.userName, gym: socket.data.gymName });
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
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        mon('pause', `⏸ PAUSE sala ${sala_id} · ${who}`, { sala_id, user: socket.data.userName, gym: socket.data.gymName });
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
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        mon('stop', `⏹ STOP  sala ${sala_id} · sesión finalizada · ${who}`, { sala_id, user: socket.data.userName, gym: socket.data.gymName });
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
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        const blk = st.blocks[st.currentBlockIndex];
        const blkLabel = blk ? `"${blk.name || blk.type}" (${blk.type})` : `bloque ${st.currentBlockIndex + 1}`;
        mon('skip', `⏭ SKIP  sala ${sala_id} → ${blkLabel} [${st.currentBlockIndex + 1}/${st.blocks.length}] · ${who}`, { sala_id, block: st.currentBlockIndex, user: socket.data.userName, gym: socket.data.gymName });
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
        if (st.status === 'finished') st.status = 'paused';
        await persistState(st, 'block');
        io.to(`sala:${sala_id}`).emit('session:block_change', {
            index: st.currentBlockIndex,
            block: st.blocks[st.currentBlockIndex],
            next_block: st.blocks[st.currentBlockIndex + 1] || null,
        });
        if (wasPlaying) startTimer(sala_id);
        broadcast(sala_id);
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        const blk = st.blocks[st.currentBlockIndex];
        const blkLabel = blk ? `"${blk.name || blk.type}" (${blk.type})` : `bloque ${st.currentBlockIndex + 1}`;
        mon('nav', `⏮ PREV  sala ${sala_id} → ${blkLabel} [${st.currentBlockIndex + 1}/${st.blocks.length}] · ${who}`, { sala_id, block: st.currentBlockIndex, user: socket.data.userName, gym: socket.data.gymName });
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
        if (st.status === 'finished' || st.autoPlay === false) st.status = 'paused';
        await persistState(st, 'block');
        if (st.status === 'paused') await persistState(st, 'pause');
        if (wasPlaying && st.autoPlay !== false) {
            st.status = 'playing';
            await persistState(st, 'play');
            startTimer(sala_id);
        }
        broadcast(sala_id);
        io.to(`sala:${sala_id}`).emit('session:block_change', {
            index: st.currentBlockIndex,
            block: st.blocks[st.currentBlockIndex],
            next_block: st.blocks[st.currentBlockIndex + 1] || null,
        });
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        const blk = st.blocks[st.currentBlockIndex];
        const blkLabel = blk ? `"${blk.name || blk.type}" (${blk.type})` : `bloque ${st.currentBlockIndex + 1}`;
        mon('nav', `➡ GOTO  sala ${sala_id} → ${blkLabel} [${st.currentBlockIndex + 1}/${st.blocks.length}] · ${who}`, { sala_id, block: st.currentBlockIndex, user: socket.data.userName, gym: socket.data.gymName });
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
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        const blk = st.blocks[st.currentBlockIndex];
        const blkLabel = blk ? `"${blk.name || blk.type}"` : `bloque ${st.currentBlockIndex + 1}`;
        mon('extend', `⏰ +${seconds}s sala ${sala_id} · ${blkLabel} [${st.currentBlockIndex + 1}] · ${who}`, { sala_id, seconds, user: socket.data.userName, gym: socket.data.gymName });
    });

    // ── Control: SET AUTOPLAY ─────────────────────────────────────────────────
    socket.on('control:set_autoplay', ({ enabled }) => {
        const sala_id = socket.data.salaId;
        const st = sessionStates.get(sala_id);
        if (!st) return;
        st.autoPlay = !!enabled;
        console.log(`[Socket] Sala ${sala_id} autoPlay → ${st.autoPlay}`);
        broadcast(sala_id);
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        mon('config', `⚙ AutoPlay sala ${sala_id} → ${enabled ? 'ON' : 'OFF'} · ${who}`, { sala_id, enabled, user: socket.data.userName, gym: socket.data.gymName });
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
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        mon('wod', `📊 WOD sala ${sala_id} → ${active ? 'ABIERTO' : 'CERRADO'} · ${who}`, { sala_id, active, user: socket.data.userName, gym: socket.data.gymName });
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
        broadcast(sala_id);
        console.log(`[Socket] Sala ${sala_id} clock_mode → active=${active} mode=${mode}`);
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        mon('clock', `🕑 Reloj sala ${sala_id} → ${active ? `${mode || 'session'} ON` : 'OFF'} · ${who}`, { sala_id, active, mode, user: socket.data.userName, gym: socket.data.gymName });
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
        const who = socket.data.userName ? `${socket.data.userName} [${socket.data.gymName}]` : socket.id;
        mon('clock', `🔲 FullScreen sala ${sala_id} → ${active ? 'ON' : 'OFF'} · ${who}`, { sala_id, active, user: socket.data.userName, gym: socket.data.gymName });
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

    // ── Disconnect ─────────────────────────────────────────────────────────────────
    socket.on('disconnect', () => {
        const role = socket.data.role || '?';
        const desc = socket.data.userName
            ? `${socket.data.userName} [${socket.data.gymName}] (${role})`
            : `${socket.id} (${role})`;
        console.log(`[Socket] Disconnected: ${socket.id} (${role})`);
        if (role !== 'monitor') mon('disconnect', `🔚 ${desc}`, { id: socket.id, role, user: socket.data.userName, gym: socket.data.gymName });

        // ── Auto-detach: if instructor disconnects, start grace period ──────────────
        if (role === 'instructor' && socket.data.salaId && socket.data.sessionId) {
            const salaId = socket.data.salaId;
            const sessionId = socket.data.sessionId;

            // Only if no other instructor socket is still connected to this sala
            const othersInSala = [...io.sockets.sockets.values()].some(
                s => s.id !== socket.id && s.data.salaId === salaId && s.data.role === 'instructor'
            );

            if (!othersInSala && !graceTimers.has(salaId)) {
                mon('grace', `⏳ Instructor desconectado — sala ${salaId} desacopla en ${INSTRUCTOR_GRACE_MS / 1000}s si no reconecta`, { salaId, sessionId });
                const tid = setTimeout(() => _autoDetachSala(salaId, sessionId, 'instructor timeout'), INSTRUCTOR_GRACE_MS);
                graceTimers.set(salaId, tid);
            }
        }
    });
});

// ─── HTTP: Detach Sala ────────────────────────────────────────────────────
// Called by PHP when a session is uncoupled from a sala.
// Clears in-memory state and notifies display clients to return to waiting.
app.get('/internal/detach-sala', (req, res) => {
    const sala_id = parseInt(req.query.sala_id);
    if (!sala_id) return res.json({ ok: false, error: 'Missing sala_id' });
    stopTimer(sala_id);
    _stopClockTimer(sala_id);
    sessionStates.delete(sala_id);
    io.to(`sala:${sala_id}`).emit('session:detach');
    mon('detach', `🔌 Sala ${sala_id} desacoplada — display de vuelta a espera`, { sala_id });
    console.log(`[HTTP] Sala ${sala_id} detached — display notified`);
    res.json({ ok: true });
});

// ─── HTTP: Sala Renamed ───────────────────────────────────────────────────
// Called by PHP after a sala name change to update the display screen live.
app.get('/internal/sala-renamed', (req, res) => {
    const sala_id = parseInt(req.query.sala_id);
    const name = (req.query.name || '').trim();
    if (!sala_id || !name) return res.json({ ok: false, error: 'Missing sala_id or name' });
    // Update in-memory state name too if there's an active session
    const st = sessionStates.get(sala_id);
    if (st) st.salaName = name;
    io.to(`sala:${sala_id}`).emit('sala:renamed', { name });
    console.log(`[HTTP] Sala ${sala_id} renamed → "${name}"`);
    res.json({ ok: true });
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
    mon('broadcast', `📢 BROADCAST [${type}]: "${message.slice(0, 80)}" → ${count} receptores`, { type, message, count });
    res.json({ ok: true, recipients: count });
});

// ─── HTTP: Schedule Updated ─────────────────────────────────────────────────
// Called by PHP api/schedules.php after a slot is created or deleted.
// Emits fresh schedule data to all agenda display screens for this gym.
app.get('/internal/schedule-updated', async (req, res) => {
    const gym_id = parseInt(req.query.gym_id);
    if (!gym_id) return res.json({ ok: false, error: 'Missing gym_id' });
    try {
        const [slots] = await pool.execute(
            `SELECT ss.*, ss.label AS class_name, s.name AS sala_name,
                    s.accent_color, s.bg_color
             FROM   schedule_slots ss
             LEFT JOIN salas s ON ss.sala_id = s.id
             WHERE  ss.gym_id = ?
             ORDER  BY ss.day_of_week, ss.start_time`,
            [gym_id]
        );
        io.to(`agenda:${gym_id}`).emit('schedule:updated', { gym_id, slots, ts: Date.now() });
        console.log(`[Agenda] schedule:updated → gym ${gym_id} (${slots.length} slots)`);
        res.json({ ok: true, slots: slots.length });
    } catch (e) {
        console.error('[Agenda] schedule-updated error:', e.message);
        res.json({ ok: false, error: e.message });
    }
});

app.get('/health', (_, res) => res.json({ ok: true }));

app.get('/status', async (req, res) => {
    res.set('Access-Control-Allow-Origin', '*');
    res.set('Cache-Control', 'no-store');

    let dbOk = false;
    try { await pool.execute('SELECT 1'); dbOk = true; } catch (_) { }

    res.json({
        ok: true,
        version: '1.0',
        uptime_s: Math.floor(process.uptime()),
        db: dbOk,
        ts: Date.now(),
    });
});

// ─── Support / Helpdesk Real-Time ─────────────────────────────────────────
//
// Rooms:
//   ticket:{id}      — both parties for a specific ticket thread
//   support:admins   — all connected superadmin windows (for badge / list updates)
//
// Events client → server:
//   support:join           { ticket_id, role }
//   support:message        { ticket_id, user_id, role, name, message, is_internal }
//   support:typing         { ticket_id, name }
//   support:status_change  { ticket_id, status, user_id, role }
//
// Events server → client:
//   support:new_message       full message object
//   support:typing            { name }
//   support:status_changed    { ticket_id, status }
//   support:new_ticket_message { ticket_id }  → support:admins room only

io.on('connection', (socket) => {

    socket.on('support:join', ({ ticket_id, role } = {}) => {
        if (!ticket_id) return;
        socket.join(`ticket:${ticket_id}`);
        if (role === 'superadmin') socket.join('support:admins');
        console.log(`[Support] ${socket.id} joined ticket:${ticket_id} as ${role}`);
    });

    socket.on('support:message', async ({ ticket_id, user_id, role, name, message, is_internal } = {}) => {
        if (!ticket_id || !user_id || !message) return;
        const internal = role === 'superadmin' ? (is_internal ? 1 : 0) : 0;
        try {
            const [result] = await pool.execute(
                'INSERT INTO support_messages (ticket_id, user_id, message, is_internal) VALUES (?,?,?,?)',
                [ticket_id, user_id, message, internal]
            );
            if (role === 'superadmin') {
                await pool.execute(
                    "UPDATE support_tickets SET status='in_progress' WHERE id=? AND status='open'",
                    [ticket_id]
                );
            }
            const payload = {
                id: result.insertId, ticket_id, user_id,
                author_name: name || 'Usuario', author_role: role,
                message, is_internal: internal,
                created_at: new Date().toISOString(),
            };
            io.to(`ticket:${ticket_id}`).emit('support:new_message', payload);
            io.to('support:admins').emit('support:new_ticket_message', { ticket_id });
            mon('support', `💬 Ticket #${ticket_id} — ${name}: ${message.slice(0, 60)}`, { ticket_id, user_id, role });
        } catch (e) {
            console.error('[Support] message error:', e.message);
        }
    });

    socket.on('support:typing', ({ ticket_id, name } = {}) => {
        if (!ticket_id) return;
        socket.to(`ticket:${ticket_id}`).emit('support:typing', { name });
    });

    socket.on('support:status_change', async ({ ticket_id, status, user_id, role } = {}) => {
        const allowed = role === 'superadmin'
            ? ['open', 'in_progress', 'resolved', 'closed']
            : ['closed'];
        if (!allowed.includes(status)) return;
        try {
            await pool.execute('UPDATE support_tickets SET status=? WHERE id=?', [status, ticket_id]);
            io.to(`ticket:${ticket_id}`).emit('support:status_changed', { ticket_id, status });
            io.to('support:admins').emit('support:new_ticket_message', { ticket_id });
            mon('support', `🏷 Ticket #${ticket_id} → ${status}`, { ticket_id, user_id, role });
        } catch (e) {
            console.error('[Support] status_change error:', e.message);
        }
    });
});

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
