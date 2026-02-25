<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');
$role = $user['role'];

$iconBook = '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>';

layout_header('Documentación', 'docs', $user);

// Nav based on role
if ($role === 'instructor') {
    nav_section('Instructor');
    nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'docs');
    nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'docs');
    nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'docs');
    nav_item(BASE_URL . '/pages/docs.php', 'Ayuda', $iconBook, 'docs', 'docs');
} elseif ($role === 'admin') {
    nav_section('Administrador');
    nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'docs');
    nav_item(BASE_URL . '/pages/docs.php', 'Ayuda', $iconBook, 'docs', 'docs');
} else {
    nav_section('Sistema');
    nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'docs');
    nav_item(BASE_URL . '/pages/docs.php', 'Ayuda', $iconBook, 'docs', 'docs');
}

layout_footer($user);
?>

<style>
    .docs-wrap {
        display: grid;
        grid-template-columns: 260px 1fr;
        gap: 0;
        min-height: calc(100vh - 64px);
    }

    .docs-sidebar {
        border-right: 1px solid var(--border);
        padding: 24px 0;
        position: sticky;
        top: 64px;
        height: calc(100vh - 64px);
        overflow-y: auto;
        background: var(--surface);
    }

    .docs-sidebar-search {
        padding: 0 16px 16px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 8px;
    }

    .docs-sidebar-search input {
        width: 100%;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 8px 12px 8px 36px;
        color: var(--text);
        font-size: 13px;
        outline: none;
        box-sizing: border-box;
        transition: border-color .2s;
    }

    .docs-sidebar-search input:focus {
        border-color: var(--primary);
    }

    .docs-sidebar-search-wrap {
        position: relative;
    }

    .docs-sidebar-search-wrap svg {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
    }

    .docs-sidebar-section {
        padding: 16px 16px 4px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .docs-nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 16px;
        margin: 2px 8px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        color: var(--text-secondary);
        transition: background .15s, color .15s;
        border: none;
        background: none;
        width: calc(100% - 16px);
        text-align: left;
    }

    .docs-nav-item:hover {
        background: var(--hover);
        color: var(--text);
    }

    .docs-nav-item.active {
        background: rgba(99, 102, 241, .12);
        color: var(--primary);
        font-weight: 600;
    }

    .docs-nav-item .nav-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        opacity: .4;
        flex-shrink: 0;
    }

    .docs-nav-item.active .nav-dot {
        opacity: 1;
    }

    /* Content */
    .docs-content {
        padding: 40px 48px;
        max-width: 820px;
    }

    .docs-article {
        display: none;
    }

    .docs-article.visible {
        display: block;
    }

    .docs-article h1 {
        font-size: 26px;
        font-weight: 800;
        margin: 0 0 8px;
    }

    .docs-article .docs-meta {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 28px;
        display: flex;
        gap: 16px;
        align-items: center;
    }

    .docs-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(99, 102, 241, .15);
        color: var(--primary);
    }

    .docs-article h2 {
        font-size: 18px;
        font-weight: 700;
        margin: 32px 0 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border);
    }

    .docs-article h3 {
        font-size: 15px;
        font-weight: 700;
        margin: 24px 0 8px;
    }

    .docs-article p {
        color: var(--text-secondary);
        line-height: 1.75;
        margin: 0 0 14px;
    }

    .docs-article ul,
    .docs-article ol {
        color: var(--text-secondary);
        line-height: 1.75;
        padding-left: 20px;
        margin: 0 0 14px;
    }

    .docs-article li {
        margin-bottom: 6px;
    }

    .docs-tip {
        background: rgba(99, 102, 241, .08);
        border: 1px solid rgba(99, 102, 241, .2);
        border-left: 3px solid var(--primary);
        border-radius: 8px;
        padding: 14px 16px;
        margin: 20px 0;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .docs-tip strong {
        color: var(--primary);
    }

    .docs-warn {
        background: rgba(245, 158, 11, .08);
        border: 1px solid rgba(245, 158, 11, .2);
        border-left: 3px solid #f59e0b;
        border-radius: 8px;
        padding: 14px 16px;
        margin: 20px 0;
        font-size: 13px;
        color: var(--text-secondary);
    }

    .docs-warn strong {
        color: #f59e0b;
    }

    .docs-steps {
        counter-reset: step;
        list-style: none;
        padding: 0;
    }

    .docs-steps li {
        counter-increment: step;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .docs-steps li::before {
        content: counter(step);
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--primary);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }

    /* FAQ */
    .faq-item {
        border-bottom: 1px solid var(--border);
    }

    .faq-question {
        width: 100%;
        background: none;
        border: none;
        text-align: left;
        padding: 16px 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 15px;
        font-weight: 600;
        color: var(--text);
    }

    .faq-question svg {
        transition: transform .25s;
        flex-shrink: 0;
    }

    .faq-item.open .faq-question svg {
        transform: rotate(180deg);
    }

    .faq-answer {
        display: none;
        padding: 0 0 16px;
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.7;
    }

    .faq-item.open .faq-answer {
        display: block;
    }

    /* Search highlight */
    mark {
        background: rgba(99, 102, 241, .25);
        color: var(--text);
        border-radius: 2px;
        padding: 0 2px;
    }

    @media (max-width: 768px) {
        .docs-wrap {
            grid-template-columns: 1fr;
        }

        .docs-sidebar {
            position: static;
            height: auto;
            border-right: none;
            border-bottom: 1px solid var(--border);
        }

        .docs-content {
            padding: 24px 20px;
        }
    }
</style>

<div class="docs-wrap">
    <!-- SIDEBAR -->
    <aside class="docs-sidebar">
        <div class="docs-sidebar-search">
            <div class="docs-sidebar-search-wrap">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                </svg>
                <input type="text" id="docs-search" placeholder="Buscar en la docs…" autocomplete="off">
            </div>
        </div>

        <div class="docs-sidebar-section">Primeros pasos</div>
        <button class="docs-nav-item active" onclick="showArticle('intro')" data-article="intro">
            <span class="nav-dot"></span> ¿Qué es GymFlow?
        </button>
        <button class="docs-nav-item" onclick="showArticle('roles')" data-article="roles">
            <span class="nav-dot"></span> Roles y permisos
        </button>

        <div class="docs-sidebar-section">Builder de Sesión</div>
        <button class="docs-nav-item" onclick="showArticle('builder-intro')" data-article="builder-intro">
            <span class="nav-dot"></span> Crear una sesión
        </button>
        <button class="docs-nav-item" onclick="showArticle('builder-blocks')" data-article="builder-blocks">
            <span class="nav-dot"></span> Tipos de bloques
        </button>
        <button class="docs-nav-item" onclick="showArticle('builder-templates')" data-article="builder-templates">
            <span class="nav-dot"></span> Plantillas
        </button>

        <div class="docs-sidebar-section">Sesión en Vivo</div>
        <button class="docs-nav-item" onclick="showArticle('live-instructor')" data-article="live-instructor">
            <span class="nav-dot"></span> Panel del instructor
        </button>
        <button class="docs-nav-item" onclick="showArticle('live-sala')" data-article="live-sala">
            <span class="nav-dot"></span> Pantalla de sala
        </button>
        <button class="docs-nav-item" onclick="showArticle('live-reloj')" data-article="live-reloj">
            <span class="nav-dot"></span> Reloj de hardware
        </button>

        <div class="docs-sidebar-section">Integraciones</div>
        <button class="docs-nav-item" onclick="showArticle('spotify')" data-article="spotify">
            <span class="nav-dot"></span> Spotify
        </button>
        <button class="docs-nav-item" onclick="showArticle('wod')" data-article="wod">
            <span class="nav-dot"></span> Generador WOD (IA)
        </button>

        <?php if ($role === 'superadmin'): ?>
            <div class="docs-sidebar-section">Sistema</div>
            <button class="docs-nav-item" onclick="showArticle('sys-salas')" data-article="sys-salas">
                <span class="nav-dot"></span> Gestión de salas
            </button>
            <button class="docs-nav-item" onclick="showArticle('sys-socket')" data-article="sys-socket">
                <span class="nav-dot"></span> Servidor Socket.IO
            </button>
        <?php endif; ?>

        <div class="docs-sidebar-section">FAQ</div>
        <button class="docs-nav-item" onclick="showArticle('faq')" data-article="faq">
            <span class="nav-dot"></span> Preguntas frecuentes
        </button>
    </aside>

    <!-- CONTENT -->
    <main class="docs-content" id="docs-content">

        <!-- INTRO -->
        <article class="docs-article visible" id="art-intro">
            <h1>¿Qué es GymFlow?</h1>
            <div class="docs-meta">
                <span class="docs-badge">Primeros pasos</span>
                <span>Lectura: ~2 min</span>
            </div>
            <p>GymFlow es una plataforma profesional para instructores de entrenamiento funcional, HIIT y alta
                intensidad. Permite planificar sesiones de ejercicio, ejecutarlas en vivo con sincronización en tiempo
                real para la pantalla de sala, integrar música vía Spotify y generar WODs con inteligencia artificial.
            </p>
            <h2>Flujo de trabajo</h2>
            <ol class="docs-steps">
                <li><strong>Diseñó tu sesión</strong> — Usá el Builder para armar bloques: Interval, Tabata, AMRAP,
                    Series, Circuit, etc.</li>
                <li><strong>Asignala a una sala</strong> — Antes de iniciar, vinculá la sesión a la sala donde estás
                    dictando la clase.</li>
                <li><strong>Ejecutala en vivo</strong> — Desde el panel Live controlás el timer, los bloques y la
                    música.</li>
                <li><strong>La sala se sincroniza</strong> — La pantalla de sala muestra ejercicios, timers y el WOD
                    automáticamente.</li>
            </ol>
            <div class="docs-tip">
                <strong>💡 Tip:</strong> Abrí la pantalla de sala en un TV o proyector antes de empezar la clase. El
                código QR de cada sala ya apunta a la URL correcta.
            </div>
        </article>

        <!-- ROLES -->
        <article class="docs-article" id="art-roles">
            <h1>Roles y permisos</h1>
            <div class="docs-meta"><span class="docs-badge">Primeros pasos</span></div>
            <h2>Instructor</h2>
            <p>Puede crear y editar sesiones, ejecutarlas en vivo, gestionar su biblioteca de ejercicios y conectar
                Spotify personal. No puede gestionar usuarios ni ver datos de facturación.</p>
            <h2>Admin</h2>
            <p>Tiene todo lo del instructor más la gestión de usuarios, salas e instructores de su gym. Puede ver el
                panel de facturación y configurar el plan.</p>
            <h2>Superadmin</h2>
            <p>Acceso total al sistema. Gestión de gymflows, planes, usuarios globales y monitoreo del servidor en
                tiempo real.</p>
            <div class="docs-warn">
                <strong>⚠️ Atención:</strong> Los instructores <em>no</em> pueden ver datos de otros instructores del
                mismo gym.
            </div>
        </article>

        <!-- BUILDER INTRO -->
        <article class="docs-article" id="art-builder-intro">
            <h1>Crear una sesión</h1>
            <div class="docs-meta"><span class="docs-badge">Builder</span></div>
            <p>Desde el Builder podés armar la estructura completa de tu clase antes de dictarla. Cada sesión se compone
                de uno o más <strong>bloques</strong> secuenciales.</p>
            <h2>Pasos básicos</h2>
            <ol class="docs-steps">
                <li>Ir a <strong>Builder → Nueva sesión</strong> y ponerle un nombre.</li>
                <li>Agregar bloques con el botón <strong>"+ Agregar bloque"</strong>.</li>
                <li>Configurar cada bloque: tipo, duración, ejercicios, series/rondas.</li>
                <li>Guardar y asignar a una sala antes de ejecutar.</li>
            </ol>
            <div class="docs-tip">
                <strong>💡 Tip:</strong> Usá la vista de "WOD" para ver el resumen completo de la sesión antes de
                empezar, igual que lo verán tus alumnos.
            </div>
        </article>

        <!-- BUILDER BLOCKS -->
        <article class="docs-article" id="art-builder-blocks">
            <h1>Tipos de bloques</h1>
            <div class="docs-meta"><span class="docs-badge">Builder</span></div>
            <h2>Interval</h2>
            <p>Trabajo por intervalos con fases de trabajo (Work) y descanso (Rest) configurables. Definís duración de
                work, rest y cantidad de rondas.</p>
            <h2>Tabata</h2>
            <p>Variante de interval con 20s work / 10s rest por defecto (8 rondas). Se puede personalizar.</p>
            <h2>AMRAP</h2>
            <p><em>As Many Rounds As Possible.</em> Timer de cuenta regresiva fijo; el alumno hace la mayor cantidad de
                rondas posible.</p>
            <h2>EMOM</h2>
            <p><em>Every Minute On the Minute.</em> Timer de cuenta regresiva; el alumno completa los ejercicios al
                inicio de cada minuto.</p>
            <h2>For Time</h2>
            <p>El alumno completa los ejercicios a la mayor velocidad posible; el timer cuenta hacia arriba.</p>
            <h2>Series</h2>
            <p>Bloques de fuerza: N series con descanso entre ellas. El timer refleja el tiempo de descanso.</p>
            <h2>Circuit</h2>
            <p>Circuito de estaciones con tiempo fijo por estación y rotación automática.</p>
            <h2>Briefing / Descanso</h2>
            <p>Bloques especiales para explicaciones pre-clase o pausas activas pautadas.</p>
        </article>

        <!-- BUILDER TEMPLATES -->
        <article class="docs-article" id="art-builder-templates">
            <h1>Plantillas</h1>
            <div class="docs-meta"><span class="docs-badge">Builder</span></div>
            <p>Las plantillas te permiten guardar una sesión como modelo reutilizable. Podés crear una plantilla desde
                cualquier sesión existente o desde el Builder directamente.</p>
            <h2>Plantillas compartidas</h2>
            <p>Los admins pueden marcar plantillas como <strong>compartidas</strong> para que todos los instructores del
                gym puedan usarlas como punto de partida.</p>
        </article>

        <!-- LIVE INSTRUCTOR -->
        <article class="docs-article" id="art-live-instructor">
            <h1>Panel del instructor (Live)</h1>
            <div class="docs-meta"><span class="docs-badge">Sesión en Vivo</span></div>
            <p>El panel Live es el centro de control durante la clase. Desde acá podés:</p>
            <ul>
                <li><strong>Play / Pause / Stop</strong> — Controlar el estado del timer.</li>
                <li><strong>Siguiente / Anterior</strong> — Navegar entre bloques manualmente.</li>
                <li><strong>Extender</strong> — Agregar 30 segundos al bloque actual si los alumnos necesitan más
                    tiempo.</li>
                <li><strong>WOD Overlay</strong> — Mostrar el resumen completo de la sesión en la pantalla de sala.</li>
                <li><strong>Reloj independiente</strong> — Activar un timer de sala desvinculado de la sesión
                    (countdown, countup, tabata).</li>
                <li><strong>Spotify</strong> — Buscar y reproducir música sincronizada con la clase.</li>
                <li><strong>Tiempo de preparación</strong> — Configurar una cuenta regresiva de "¡Preparate!" antes de
                    iniciar cada bloque.</li>
            </ul>
            <div class="docs-tip">
                <strong>💡 Tip:</strong> Si cerrás accidentalmente el panel, podés volver a abrirlo desde Sesiones →
                Iniciar. El estado se persiste en el servidor.
            </div>
        </article>

        <!-- LIVE SALA -->
        <article class="docs-article" id="art-live-sala">
            <h1>Pantalla de sala</h1>
            <div class="docs-meta"><span class="docs-badge">Sesión en Vivo</span></div>
            <p>La pantalla de sala (sala.php) es la vista que se proyecta en el TV o monitor de la sala de
                entrenamiento. Se conecta automáticamente mediante el código de display único de cada sala.</p>
            <h2>Estados de la pantalla</h2>
            <ul>
                <li><strong>Sala libre / Esperando clase</strong> — No hay sesión activa asignada.</li>
                <li><strong>Preparándose</strong> — El instructor cargó la sesión pero aún no la inició.</li>
                <li><strong>En vivo</strong> — Muestra el timer, el ejercicio actual, bloques siguientes y la música.
                </li>
                <li><strong>Pausa</strong> — Overlay de PAUSA visible.</li>
                <li><strong>¡Excelente!</strong> — Pantalla de finalización de sesión.</li>
            </ul>
            <h2>Acceder a la pantalla</h2>
            <p>La URL es <code>/?page=display/sala&code=XXXX</code> donde <code>XXXX</code> es el código único de la
                sala. Cada sala tiene su QR disponible en el panel de administración.</p>
        </article>

        <!-- RELOJ -->
        <article class="docs-article" id="art-live-reloj">
            <h1>Reloj de hardware</h1>
            <div class="docs-meta"><span class="docs-badge">Sesión en Vivo</span></div>
            <p>El reloj de hardware es un panel físico estilo LED que aparece en la parte inferior de la pantalla de
                sala. Puede operar de dos modos:</p>
            <h2>Modo sesión</h2>
            <p>Refleja el timer del bloque actual de la sesión en vivo. Muestra la fase (WORK/REST/AMRAP…) y la cuenta
                regresiva.</p>
            <h2>Modo independiente</h2>
            <p>Timer desvinculado de la sesión, controlado desde el panel del instructor. Útil para series de fuerza o
                clases sin estructura de bloques fija. Soporta countdown, countup y tabata propio.</p>
            <p>Activalo desde el panel Live → botón <strong>"Reloj"</strong>. También podés enviarlo a pantalla completa
                desde el mismo panel.</p>
        </article>

        <!-- SPOTIFY -->
        <article class="docs-article" id="art-spotify">
            <h1>Integración Spotify</h1>
            <div class="docs-meta"><span class="docs-badge">Integraciones</span></div>
            <p>GymFlow se integra con Spotify para que puedas controlar la música directamente desde el panel Live, sin
                cambiar de pestaña.</p>
            <h2>Conexión inicial</h2>
            <ol class="docs-steps">
                <li>Ir a <strong>Mi Perfil → Conectar Spotify</strong>.</li>
                <li>Autorizar el acceso en la ventana de Spotify.</li>
                <li>Volver al panel Live — el control de música estará disponible.</li>
            </ol>
            <h2>Funciones disponibles</h2>
            <ul>
                <li>Buscar canciones o playlists por nombre.</li>
                <li>Reproducir / Pausar / Siguiente.</li>
                <li>Widget "Now Playing" en la pantalla de sala.</li>
                <li>Cuenta regresiva de "¡PREPARATE!" sincronizada con el intro de la canción.</li>
            </ul>
            <div class="docs-warn">
                <strong>⚠️ Requisito:</strong> Spotify Premium es obligatorio para control de reproducción via API.
            </div>
        </article>

        <!-- WOD -->
        <article class="docs-article" id="art-wod">
            <h1>Generador WOD (IA)</h1>
            <div class="docs-meta"><span class="docs-badge">Integraciones</span></div>
            <p>El Generador de WOD usa inteligencia artificial para crear sesiones de entrenamiento personalizadas en
                segundos, basadas en los parámetros que vos elegís.</p>
            <h2>Parámetros configurables</h2>
            <ul>
                <li>Duración total de la clase</li>
                <li>Nivel de dificultad (principiante / intermedio / avanzado)</li>
                <li>Equipamiento disponible</li>
                <li>Tipo de entrenamiento (HIIT, Funcional, Fuerza…)</li>
                <li>Músculos o áreas a trabajar</li>
            </ul>
            <p>El resultado se importa directamente al Builder como una sesión editable, que podés ajustar antes de
                dictarla.</p>
            <div class="docs-tip">
                <strong>💡 Tip:</strong> Generá varios WODs y guardá los mejores como plantillas para usarlos en futuras
                clases.
            </div>
        </article>

        <?php if ($role === 'superadmin'): ?>
            <!-- SYS SALAS -->
            <article class="docs-article" id="art-sys-salas">
                <h1>Gestión de salas</h1>
                <div class="docs-meta"><span class="docs-badge">Sistema</span></div>
                <p>Cada gym tiene una o más salas identificadas por un <code>display_code</code> único. Este código se usa
                    para que la pantalla de sala se autoidentifique al conectarse.</p>
                <h2>Tabla <code>salas</code></h2>
                <ul>
                    <li><code>id</code> — ID interno de sala.</li>
                    <li><code>gym_id</code> — Gym propietario.</li>
                    <li><code>name</code> — Nombre visible (ej: "Sala Principal").</li>
                    <li><code>display_code</code> — Código único de 6-8 chars, embebido en la URL de sala.php.</li>
                    <li><code>current_session_id</code> — Sesión actualmente asociada (nullable).</li>
                    <li><code>active</code> — Flag de habilitación.</li>
                </ul>
                <p>El sync-server mantiene el estado en memoria en <code>sessionStates</code> (Map keyed por
                    <code>sala_id</code>). El estado se persiste en <code>sync_state</code> para reconexiones.</p>
            </article>

            <!-- SYS SOCKET -->
            <article class="docs-article" id="art-sys-socket">
                <h1>Servidor Socket.IO</h1>
                <div class="docs-meta"><span class="docs-badge">Sistema</span></div>
                <p>El sync-server (<code>sync-server/server.js</code>) es el cerebro del tiempo real. Corre en Node.js con
                    Socket.IO y MySQL.</p>
                <h2>Rooms</h2>
                <ul>
                    <li><code>sala:{id}</code> — Instructor + Display de una sala. Reciben ticks, block_change, wod_overlay.
                    </li>
                    <li><code>monitor</code> — Superadmin console. Recibe logs de todos los eventos.</li>
                    <li><code>agenda:{gym_id}</code> — Carteleras de horarios del gym.</li>
                </ul>
                <h2>Endpoints HTTP internos</h2>
                <ul>
                    <li><code>GET /internal/reload?session_id=X</code> — Recarga una sesión desde MySQL y hace broadcast.
                    </li>
                    <li><code>POST /internal/broadcast</code> — Envía mensaje del sistema a todos los sockets no-display.
                    </li>
                    <li><code>GET /internal/schedule-updated?gym_id=X</code> — Notifica a las carteleras que el horario
                        cambió.</li>
                </ul>
                <div class="docs-warn">
                    <strong>⚠️ Importante:</strong> El servidor nunca hace reload automático al reiniciarse. Las sesiones en
                    estado "playing" vuelven como "paused" y requieren un Play explícito del instructor.
                </div>
            </article>
        <?php endif; ?>

        <!-- FAQ -->
        <article class="docs-article" id="art-faq">
            <h1>Preguntas frecuentes</h1>
            <div class="docs-meta"><span class="docs-badge">FAQ</span></div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    La pantalla de sala dice "Esperando clase" pero ya inicié la sesión. ¿Por qué?
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-answer">Asegurate de haber asignado la sala en el panel de Live antes de hacer Play. La
                    pantalla de sala detecta la conexión automáticamente al cargar la sesión en el servidor.</div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    ¿Spotify no reproduce aunque está conectado?
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-answer">Spotify necesita un dispositivo activo. Abrí Spotify en tu teléfono o
                    computadora y reproducí algo manualmente primero. Después GymFlow puede controlar la reproducción.
                    Verificá también que tu cuenta sea Premium.</div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    Cerré el panel Live por error. ¿Perdí el estado de la sesión?
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-answer">No. El servidor persiste el estado. Volvé a ir a Sesiones → Iniciar y se
                    reconectará desde donde estaba. Si estaba en Play, quedará en Pause hasta que hagas Play de nuevo.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    ¿Puedo tener varias salas corriendo al mismo tiempo?
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-answer">Sí. Cada sala tiene su propio estado independiente en el servidor. Podés tener N
                    instructores dictando N clases en N salas simultáneamente, cada una con su propia pantalla y timer.
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    ¿Cómo accedo a la pantalla de sala desde el TV?
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-answer">Cada sala tiene una URL única con su código. Abrí esa URL en el navegador del TV
                    (o en cualquier dispositivo conectado al proyector). La pantalla se queda abierta y se sincroniza
                    automáticamente cuando inicia la sesión.</div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    ¿Puedo editar un bloque mientras la sesión está en vivo?
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div class="faq-answer">Podés extender el tiempo del bloque actual con el botón "+30s". Para cambios más
                    profundos, pausá, editá en el Builder y guardá — el servidor recarga automáticamente los bloques sin
                    perder el estado del timer.</div>
            </div>
        </article>

    </main>
</div>

<script>
    function showArticle(id) {
        document.querySelectorAll('.docs-article').forEach(a => a.classList.remove('visible'));
        document.querySelectorAll('.docs-nav-item').forEach(b => b.classList.remove('active'));
        const art = document.getElementById('art-' + id);
        if (art) art.classList.add('visible');
        const btn = document.querySelector(`[data-article="${id}"]`);
        if (btn) btn.classList.add('active');
        // Scroll content to top
        const content = document.querySelector('.docs-content');
        if (content) content.scrollTop = 0;
    }

    function toggleFaq(btn) {
        const item = btn.closest('.faq-item');
        item.classList.toggle('open');
    }

    // Search filter
    document.getElementById('docs-search').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        if (!q) {
            document.querySelectorAll('.docs-nav-item').forEach(b => b.style.display = '');
            document.querySelectorAll('.docs-sidebar-section').forEach(s => s.style.display = '');
            return;
        }
        let firstVisible = null;
        document.querySelectorAll('.docs-nav-item').forEach(btn => {
            const text = btn.textContent.toLowerCase();
            const article = document.getElementById('art-' + btn.dataset.article);
            const artText = article ? article.textContent.toLowerCase() : '';
            const match = text.includes(q) || artText.includes(q);
            btn.style.display = match ? '' : 'none';
            if (match && !firstVisible) firstVisible = btn.dataset.article;
        });
        // Hide empty sections
        document.querySelectorAll('.docs-sidebar-section').forEach(section => {
            let next = section.nextElementSibling;
            let hasVisible = false;
            while (next && !next.classList.contains('docs-sidebar-section')) {
                if (next.style.display !== 'none') hasVisible = true;
                next = next.nextElementSibling;
            }
            section.style.display = hasVisible ? '' : 'none';
        });
        if (firstVisible) showArticle(firstVisible);
    });
</script>