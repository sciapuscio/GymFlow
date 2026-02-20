// GymFlow — API Helper
const GF = {
    async request(method, url, body = null) {
        const opts = {
            method,
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
        };
        if (body) opts.body = JSON.stringify(body);
        try {
            const r = await fetch(url, opts);
            if (!r.ok) {
                const err = await r.json().catch(() => ({ error: r.statusText }));
                throw new Error(err.error || r.statusText);
            }
            return await r.json().catch(() => null);
        } catch (e) {
            console.error('[GF API]', method, url, e.message);
            throw e;
        }
    },
    get: (url) => GF.request('GET', url),
    post: (url, body) => GF.request('POST', url, body),
    put: (url, body) => GF.request('PUT', url, body),
    delete: (url) => GF.request('DELETE', url),
};

function formatDuration(sec) {
    sec = Math.max(0, Math.floor(sec));
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    return `${m}:${String(s).padStart(2, '0')}`;
}

function computeBlockDuration(block) {
    const cfg = block.config || {};
    switch (block.type) {
        case 'interval': {
            const rounds = cfg.rounds || 1;
            const work = cfg.work || 40;
            const rest = cfg.rest || 20;
            // No trailing rest after last round
            return rounds * work + (rounds - 1) * rest;
        }
        case 'tabata': {
            const rounds = cfg.rounds || 8;
            const work = cfg.work || 20;
            const rest = cfg.rest || 10;
            // No trailing rest after last round
            return rounds * work + (rounds - 1) * rest;
        }
        case 'amrap':
        case 'emom':
        case 'fortime': return cfg.duration || 600;
        case 'rest':
        case 'briefing': return cfg.duration || 60;
        case 'series': return (cfg.sets || 3) * ((cfg.rest || 60) + 30);
        case 'circuit': return (block.exercises?.length || 0) * (cfg.station_time || 40) * (cfg.rounds || 1);
        default: return 300;
    }
}
