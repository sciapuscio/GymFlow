<?php
/**
 * web/estado.php
 * Estado del sistema — lee SOCKET_URL del config para funcionar en cualquier entorno.
 */
require_once __DIR__ . '/../config/app.php';

// Determinar la URL del status endpoint
// SOCKET_URL incluye protocolo y host (ej: http://localhost:3001)
$socketStatusUrl = rtrim(SOCKET_URL, '/') . '/status';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Estado del Sistema – GymFlow</title>
    <meta name="description" content="Estado en tiempo real de los servidores y servicios de GymFlow." />
    <link rel="icon" type="image/x-icon" href="favicon.ico" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&family=Inter:wght@300;400;500&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --bg: #0a0b0f;
            --surface: #1a1d27;
            --border: rgba(255, 255, 255, .07);
            --primary: #00f5d4;
            --green: #22c55e;
            --yellow: #f59e0b;
            --red: #ef4444;
            --text: #ffffff;
            --muted: rgba(255, 255, 255, .55);
            --dim: rgba(255, 255, 255, .25);
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(10, 11, 15, .9);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-family: 'Outfit', sans-serif;
            font-weight: 900;
            font-size: 1.25rem;
            text-decoration: none;
        }

        .logo-gym {
            color: #fff;
        }

        .logo-flow {
            color: var(--primary);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .85rem;
            color: var(--muted);
            text-decoration: none;
            transition: color .2s;
        }

        .back-btn:hover {
            color: #fff;
        }

        .status-hero {
            padding: 72px 24px 48px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }

        .overall-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-radius: 100px;
            padding: 12px 28px;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .overall-badge.ok {
            background: rgba(34, 197, 94, .12);
            border: 1px solid rgba(34, 197, 94, .3);
            color: var(--green);
        }

        .overall-badge.degraded {
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .3);
            color: var(--yellow);
        }

        .overall-badge.down {
            background: rgba(239, 68, 68, .12);
            border: 1px solid rgba(239, 68, 68, .3);
            color: var(--red);
        }

        .overall-badge.loading {
            background: rgba(255, 255, 255, .04);
            border: 1px solid var(--border);
            color: var(--muted);
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .dot.ok {
            background: var(--green);
            animation: pulsate 2s infinite;
        }

        .dot.degraded {
            background: var(--yellow);
            animation: pulsate 2s infinite;
        }

        .dot.down {
            background: var(--red);
        }

        .dot.loading {
            background: var(--muted);
        }

        @keyframes pulsate {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .4;
            }
        }

        .status-hero p {
            color: var(--muted);
            font-size: 1rem;
        }

        .refresh-line {
            margin-top: 14px;
            font-size: .78rem;
            color: var(--dim);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .refresh-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 6px;
            padding: 4px 12px;
            font-size: .78rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            transition: all .2s;
        }

        .refresh-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .refresh-btn:disabled {
            opacity: .35;
            cursor: default;
        }

        .status-grid {
            max-width: 740px;
            margin: 0 auto;
            padding: 48px 24px 80px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .section-title {
            font-family: 'Outfit', sans-serif;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--primary);
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 4px;
        }

        .service-row {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: border-color .3s;
        }

        .service-row.ok {
            border-left: 3px solid var(--green);
        }

        .service-row.degraded {
            border-left: 3px solid var(--yellow);
        }

        .service-row.down {
            border-left: 3px solid var(--red);
        }

        .service-row.loading {
            border-left: 3px solid rgba(255, 255, 255, .12);
        }

        .svc-icon {
            font-size: 1.3rem;
            flex-shrink: 0;
            width: 36px;
            text-align: center;
        }

        .svc-body {
            flex: 1;
            min-width: 0;
        }

        .svc-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: .95rem;
        }

        .svc-desc {
            font-size: .8rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .svc-meta {
            font-size: .75rem;
            color: var(--dim);
            margin-top: 4px;
        }

        .svc-badge {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 100px;
            padding: 4px 12px;
            font-size: .75rem;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
        }

        .svc-badge.ok {
            background: rgba(34, 197, 94, .1);
            color: var(--green);
        }

        .svc-badge.degraded {
            background: rgba(245, 158, 11, .1);
            color: var(--yellow);
        }

        .svc-badge.down {
            background: rgba(239, 68, 68, .1);
            color: var(--red);
        }

        .svc-badge.loading {
            background: rgba(255, 255, 255, .05);
            color: var(--muted);
        }

        .pg-footer {
            border-top: 1px solid var(--border);
            padding: 24px;
        }

        .pg-footer-inner {
            max-width: 740px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pg-footer p {
            font-size: .8rem;
            color: var(--dim);
        }

        .pg-footer nav {
            display: flex;
            gap: 20px;
        }

        .pg-footer a {
            font-size: .8rem;
            color: var(--muted);
            text-decoration: none;
            transition: color .2s;
        }

        .pg-footer a:hover {
            color: var(--primary);
        }

        @media (max-width: 520px) {
            .service-row {
                flex-wrap: wrap;
            }

            .pg-footer-inner {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>

    <header class="site-header">
        <a href="<?= BASE_URL ?>/" class="logo"><span class="logo-gym">Gym</span><span class="logo-flow">Flow</span></a>
        <a href="<?= BASE_URL ?>/" class="back-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6" />
            </svg>
            Volver al inicio
        </a>
    </header>

    <div class="status-hero">
        <div class="overall-badge loading" id="overallBadge">
            <span class="dot loading" id="overallDot"></span>
            <span id="overallText">Verificando servicios…</span>
        </div>
        <p id="heroSub">Consultando el estado de GymFlow en tiempo real.</p>
        <div class="refresh-line">
            <span id="lastChecked">—</span>
            <button class="refresh-btn" id="refreshBtn" onclick="runChecks()">↻ Actualizar</button>
        </div>
    </div>

    <div class="status-grid">
        <p class="section-title">Servicios</p>

        <div class="service-row loading" id="row-sync">
            <div class="svc-icon">⚡</div>
            <div class="svc-body">
                <div class="svc-name">Sincronización en tiempo real</div>
                <div class="svc-desc">Sesiones de entrenamiento, WODs y Cartelera TV</div>
                <div class="svc-meta" id="meta-sync">—</div>
            </div>
            <span class="svc-badge loading" id="badge-sync">Verificando</span>
        </div>

        <div class="service-row loading" id="row-db">
            <div class="svc-icon">🗄️</div>
            <div class="svc-body">
                <div class="svc-name">Base de datos</div>
                <div class="svc-desc">Sesiones, horarios, ejercicios y usuarios</div>
                <div class="svc-meta" id="meta-db">—</div>
            </div>
            <span class="svc-badge loading" id="badge-db">Verificando</span>
        </div>

        <div class="service-row loading" id="row-web">
            <div class="svc-icon">🌐</div>
            <div class="svc-body">
                <div class="svc-name">Plataforma web</div>
                <div class="svc-desc">Panel de instructor, builder, agenda y display</div>
                <div class="svc-meta" id="meta-web">—</div>
            </div>
            <span class="svc-badge loading" id="badge-web">Verificando</span>
        </div>
    </div>

    <footer class="pg-footer">
        <div class="pg-footer-inner">
            <p>© 2026 GymFlow · Estado actualizado automáticamente</p>
            <nav>
                <a href="privacidad.html">Privacidad</a>
                <a href="terminos.html">Términos</a>
                <a href="<?= BASE_URL ?>/">Inicio</a>
            </nav>
        </div>
    </footer>

    <script>
        // URL inyectada por PHP desde config/local.php (o app.php si no existe local.php)
        const SYNC_STATUS_URL = <?= json_encode($socketStatusUrl) ?>;
        const REFRESH_S = 30;

        function setRow(id, state, badgeText, meta) {
            document.getElementById('row-' + id).className = 'service-row ' + state;
            const b = document.getElementById('badge-' + id);
            b.className = 'svc-badge ' + state;
            b.textContent = badgeText;
            document.getElementById('meta-' + id).textContent = meta || '';
        }

        function fmtUptime(s) {
            if (!s && s !== 0) return 'desconocido';
            if (s < 60) return `${s}s`;
            if (s < 3600) return `${Math.floor(s / 60)}m`;
            return `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m`;
        }

        async function checkSync() {
            const t0 = Date.now();
            try {
                const r = await fetch(SYNC_STATUS_URL, { signal: AbortSignal.timeout(6000) });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                const ms = Date.now() - t0;

                setRow('sync', 'ok', 'Operativo', `Respuesta ${ms}ms · activo hace ${fmtUptime(d.uptime_s)}`);
                setRow('db',
                    d.db ? 'ok' : 'down',
                    d.db ? 'Operativo' : 'Sin conexión',
                    d.db ? 'Conexión MySQL activa' : 'Base de datos no responde'
                );
                return true;
            } catch (e) {
                setRow('sync', 'down', 'Sin respuesta', 'El servidor no está respondiendo');
                setRow('db', 'degraded', 'No verificable', 'No se pudo contactar el servidor');
                return false;
            }
        }

        async function checkWeb() {
            // fetch con no-cors para evitar bloqueos; si llegamos a esta página, el web server funciona
            setRow('web', 'ok', 'Operativo', 'Servidor web activo');
        }

        async function runChecks() {
            const btn = document.getElementById('refreshBtn');
            btn.disabled = true;

            ['sync', 'db', 'web'].forEach(id => setRow(id, 'loading', 'Verificando', '—'));
            const badge = document.getElementById('overallBadge');
            badge.className = 'overall-badge loading';
            document.getElementById('overallDot').className = 'dot loading';
            document.getElementById('overallText').textContent = 'Verificando servicios…';

            const [syncOk] = await Promise.all([checkSync(), checkWeb()]);

            if (syncOk) {
                badge.className = 'overall-badge ok';
                document.getElementById('overallDot').className = 'dot ok';
                document.getElementById('overallText').textContent = 'Todos los sistemas operativos';
                document.getElementById('heroSub').textContent = 'GymFlow está funcionando correctamente.';
            } else {
                badge.className = 'overall-badge down';
                document.getElementById('overallDot').className = 'dot down';
                document.getElementById('overallText').textContent = 'Servicio degradado';
                document.getElementById('heroSub').textContent = 'Algunos servicios no están respondiendo. Podés escribirnos por WhatsApp.';
            }

            const now = new Date();
            document.getElementById('lastChecked').textContent =
                'Última verificación: ' + now.toLocaleTimeString('es-AR') + ' · actualizando cada 30s';

            btn.disabled = false;
        }

        runChecks();
        setInterval(runChecks, REFRESH_S * 1000);
    </script>
</body>

</html>