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
// timers: Map<salaId, intervalId>
const timers = new Map();

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
                // Auto-advance to next block
                if (st.currentBlockIndex < st.blocks.length - 1) {
                    st.currentBlockIndex++;
                    st.elapsed = 0;
                    st.prepRemaining = 0;
                    persistState(st, 'block');
                    // Notify instructor of block change for Spotify
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
        if (st.currentBlockIndex >= st.blocks.length - 1 && !wasPlaying) {
            st.status = 'finished';
        }
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
        stopTimer(sala_id);
        st.currentBlockIndex = Math.max(0, Math.min(index, st.blocks.length - 1));
        st.elapsed = 0;
        st.prepRemaining = prep_remaining; // honour prep sent by instructor
        await persistState(st, 'block');
        io.to(`sala:${sala_id}`).emit('session:block_change', {
            index: st.currentBlockIndex,
            block: st.blocks[st.currentBlockIndex],
            next_block: st.blocks[st.currentBlockIndex + 1] || null,
        });
        broadcast(sala_id);
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
