/**
 * GymFlow — Onboarding Tour Engine  (assets/js/tour.js)
 *
 * Triggered automatically when window.GF_TOUR_NEEDED === true
 * (set by layout.php when $user['last_login'] IS NULL).
 *
 * On dismiss/complete → POST /api/auth.php?action=first_login
 * which sets last_login = NOW(), so the tour won't show again.
 *
 * Required injections from layout.php:
 *   window.GF_TOUR_PAGE   — 'admin_dashboard' | 'instructor_dashboard' | 'builder'
 *   window.GF_TOUR_NEEDED — true | false
 *   window.GF_USER        — {id, role, gym_id, name}
 *   window.GF_BASE        — base URL prefix
 */

(function () {
    'use strict';

    // ── Guards ───────────────────────────────────────────────────────────────────
    if (!window.GF_TOUR_PAGE || !window.GF_USER) return;
    if (window.GF_USER.role === 'superadmin') return;
    if (!window.GF_TOUR_NEEDED) return; // last_login already set → not a new user

    const BASE = window.GF_BASE || '';
    const PAGE = window.GF_TOUR_PAGE;

    // ── Step state (localStorage only as cross-page progression cache) ──────────
    // NOTE: localStorage here is NOT the primary gate (that's GF_TOUR_NEEDED).
    //       It's used only so we know WHICH step to start on current page.
    const STEP_KEY = 'gf_tour_step_' + (window.GF_USER.id || '');

    function getStep() { return parseInt(localStorage.getItem(STEP_KEY) || '0', 10); }
    function setStep(n) { localStorage.setItem(STEP_KEY, String(n)); }

    // ── Tour step definitions ───────────────────────────────────────────────────
    const STEPS = [
        {
            // Step 0 — Welcome modal (admin_dashboard only, no spotlight)
            page: 'admin_dashboard',
            selector: null,
            welcome: true,
            emoji: '🎉',
            title: '¡Bienvenido a GymFlow!',
            subtitle: 'Tu gimnasio está listo. En 6 pasos rápidos te mostramos cómo sacarle el máximo provecho.',
            preview: [
                { num: 1, icon: '🎨', text: 'Branding — dale identidad a tu gimnasio' },
                { num: 2, icon: '⚡', text: 'Panel del Instructor — tu centro de operaciones' },
                { num: 3, icon: '📺', text: 'Display — la pantalla que ven tus alumnos' },
                { num: 4, icon: '🎵', text: 'Spotify Premium — sincronizá música con tu clase' },
                { num: 5, icon: '🏗️', text: 'Builder — diseñá bloques y sesiones' },
                { num: 6, icon: '🎯', text: 'Doble clic — cómo interactuar con el Builder' },
            ],
            nextLabel: 'Empezar el tour →',
            nextStep: 1,
        },
        {
            // Step 1 — Branding button in page header
            page: 'admin_dashboard',
            selector: 'button.btn-secondary',
            emoji: '🎨',
            title: 'Branding de tu Gimnasio',
            body: 'Lo primero es darle identidad a tu espacio. Subí el <strong>logo</strong>, elegí tus <strong>colores</strong> y escribí el nombre que verán tus alumnos en pantalla.',
            arrow: 'bottom',
            nextLabel: 'Personalizar ahora →',
            pauseOnNext: true, // pause tour while branding modal is open
            onNext: function () {
                const modal = document.getElementById('branding-modal');
                if (!modal) return;
                modal.classList.add('open');
                // Pause the tour overlay so the modal is fully accessible
                window._gfTour.pause();
                // Resume + advance when the modal closes (class removed)
                const obs = new MutationObserver(() => {
                    if (!modal.classList.contains('open')) {
                        obs.disconnect();
                        window._gfTour.resume();
                    }
                });
                obs.observe(modal, { attributes: true, attributeFilter: ['class'] });
            },
        },
        {
            // Step 2 — Highlight Instructor link in sidebar
            page: 'admin_dashboard',
            selector: 'a.nav-item[href*="instructor/dashboard"]',
            emoji: '⚡',
            title: 'Panel de Instructor',
            body: 'Aquí controlás tus clases en vivo. Hacé clic en <strong>Instructor</strong> en el menú para ver el panel desde donde manejás cada sesión.',
            arrow: 'right',
            nextLabel: 'Ir al panel de Instructor →',
            nextHref: BASE + '/pages/instructor/dashboard.php',
        },
        {
            // Step 3 — Instructor dashboard header
            page: 'instructor_dashboard',
            selector: '.page-header',
            emoji: '🎛️',
            title: 'Tu panel de Instructor',
            body: 'Desde acá gestionás tus sesiones, bloques y plantillas. Podés crear una sesión, <strong>guardarla para reutilizarla las veces que quieras</strong>, y lanzarla en vivo para que tus alumnos la vean en pantalla.',
            arrow: 'bottom',
            nextLabel: 'Siguiente →',
        },
        {
            // Step 4 — Display window for students
            page: 'instructor_dashboard',
            selector: 'a[href*="display/sala"]',
            emoji: '📺',
            title: 'Ventana para tus Alumnos',
            body: 'Cada sala tiene su propia pantalla. Hacé clic en <strong>Display</strong> para abrir la ventana que proyectás en la TV del gimnasio — tus alumnos ven el timer, los ejercicios y la música en tiempo real.',
            arrow: 'bottom',
            nextLabel: 'Siguiente →',
        },
        {
            // Step 5 — Spotify / Profile link
            page: 'instructor_dashboard',
            selector: 'a.nav-item[href*="profile"]',
            emoji: '🎵',
            title: 'Conectá Spotify Premium',
            body: '<strong>Opcional pero muy recomendable.</strong> Con Spotify conectado la música se sincroniza automáticamente con el timing de cada bloque de tu clase.',
            arrow: 'right',
            extraBtn: { label: '🎵 Conectar Spotify', href: BASE + '/pages/instructor/profile.php' },
            nextLabel: 'Omitir este paso',
        },
        {
            // Step 4 — Builder link
            page: 'instructor_dashboard',
            selector: 'a.nav-item[href*="builder"]',
            emoji: '🏗️',
            title: 'Builder de Sesiones',
            body: 'Acá diseñás tus bloques de entrenamiento (Tabata, HIIT, Circuitos…). Hacé clic para abrirlo.',
            arrow: 'right',
            nextLabel: 'Abrir el Builder →',
            nextHref: BASE + '/pages/instructor/builder.php',
        },
        {
            // Step 5 — Builder interaction hint (finish)
            page: 'builder',
            selector: '#blocks-panel, .blocks-list, .builder-canvas, main',
            emoji: '🎯',
            title: 'Cómo usar el Builder',
            body: '<strong>Doble clic</strong> en un bloque para seleccionarlo y ver sus propiedades.<br><br>Desde ahí elegís los <strong>ejercicios</strong> y ajustás tiempos, rondas y descansos.<br><br>Cuando tu sesión esté lista, ¡lanzala en vivo!',
            arrow: 'left',
            nextLabel: '¡Entendido! Empezar →',
            finish: true,
        },
    ];

    // Find first step for this page starting from saved progress
    const currentSaved = getStep();
    const startIdx = STEPS.findIndex((s, i) => s.page === PAGE && i >= currentSaved);
    if (startIdx === -1) return;

    let currentIdx = startIdx;
    let highlightEl = null;
    let transitioning = false; // prevents backdrop from eating the click that opened the step

    function setTransitioning() {
        transitioning = true;
        setTimeout(() => { transitioning = false; }, 400);
    }

    // ── Notify server: mark first login done ────────────────────────────────────
    async function markFirstLogin() {
        try {
            await fetch(`${BASE}/api/auth.php?action=first_login`, {
                method: 'POST',
                credentials: 'include',
            });
        } catch (_) { }
        localStorage.removeItem(STEP_KEY);
    }

    // ── Build DOM ────────────────────────────────────────────────────────────────
    const overlay = document.createElement('div');
    overlay.id = 'gf-tour-overlay';
    overlay.innerHTML = `
    <div id="gf-tour-backdrop"></div>
    <div id="gf-tour-welcome" style="display:none"></div>
    <div id="gf-tour-tip" class="hidden" data-arrow="none"></div>
  `;
    document.body.appendChild(overlay);

    const backdrop = overlay.querySelector('#gf-tour-backdrop');
    const welcomeDiv = overlay.querySelector('#gf-tour-welcome');
    const tip = overlay.querySelector('#gf-tour-tip');

    // ── Spotlight ────────────────────────────────────────────────────────────────
    function spotlight(rect, p = 8) {
        const t = rect.top - p, b = rect.bottom + p;
        const l = rect.left - p, r = rect.right + p;
        backdrop.style.clipPath = `polygon(
      0% 0%,100% 0%,100% 100%,0% 100%,0% 0%,
      ${l}px ${t}px,${l}px ${b}px,${r}px ${b}px,${r}px ${t}px,${l}px ${t}px)`;
    }

    // ── Position tooltip ─────────────────────────────────────────────────────────
    function positionTip(rect, arrow) {
        tip.setAttribute('data-arrow', arrow || 'none');
        const TW = 320, mg = 16, vw = window.innerWidth, vh = window.innerHeight;
        let left, top;
        if (!rect) { left = (vw - TW) / 2; top = vh / 2 - 100; }
        else if (arrow === 'right') { left = rect.right + mg; top = rect.top; }
        else if (arrow === 'left') { left = rect.left - TW - mg; top = rect.top; }
        else if (arrow === 'bottom') { left = rect.left; top = rect.top - 270 - mg; }
        else { left = rect.left; top = rect.bottom + mg; }
        left = Math.max(mg, Math.min(left, vw - TW - mg));
        top = Math.max(mg, Math.min(top, vh - 320));
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
    }

    // ── Progress dots ────────────────────────────────────────────────────────────
    function dots(total, cur) {
        return '<div class="tour-progress">' +
            Array.from({ length: total }, (_, i) =>
                `<div class="tour-dot ${i < cur ? 'done' : i === cur ? 'active' : ''}"></div>`
            ).join('') + '</div>';
    }

    // ── Render welcome modal ─────────────────────────────────────────────────────
    function showWelcome(step) {
        welcomeDiv.style.display = 'flex';
        tip.classList.add('hidden');
        backdrop.style.clipPath = '';
        const preview = (step.preview || []).map(p =>
            `<div class="tour-step-preview-item">
         <div class="tour-step-num">${p.num}</div>
         <span>${p.icon} ${p.text}</span>
       </div>`
        ).join('');
        welcomeDiv.innerHTML = `
      <div class="tour-welcome-card">
        <span class="tour-welcome-emoji">${step.emoji}</span>
        <h2 class="tour-welcome-title">${step.title}</h2>
        <p class="tour-welcome-sub">${step.subtitle}</p>
        <div class="tour-steps-preview">${preview}</div>
        <div class="tour-welcome-actions">
          <button class="tour-btn-primary" id="tour-start-btn" style="justify-content:center;font-size:.95rem;padding:13px">
            ${step.nextLabel}
          </button>
          <button class="tour-skip" id="tour-skip-welcome">Omitir tour</button>
        </div>
      </div>`;
        document.getElementById('tour-start-btn').onclick = (e) => {
            e.stopPropagation();
            welcomeDiv.style.display = 'none';
            currentIdx++;
            setTimeout(() => showStep(currentIdx), 50); // tiny delay clears any pending click events
        };
        document.getElementById('tour-skip-welcome').onclick = (e) => {
            e.stopPropagation();
            dismiss();
        };
    }

    // ── Render guided step ───────────────────────────────────────────────────────
    function showStep(idx) {
        setTransitioning(); // block backdrop click during transition
        const step = STEPS[idx];
        if (!step) { finish(); return; }

        if (highlightEl) { highlightEl.classList.remove('gf-tour-highlight'); highlightEl = null; }
        welcomeDiv.style.display = 'none';
        tip.classList.remove('hidden');

        let targetEl = step.selector ? document.querySelector(step.selector) : null;
        if (!targetEl && step.selector) targetEl = document.querySelector('.sidebar-nav') || document.querySelector('main');

        let rect = null;
        if (targetEl) {
            highlightEl = targetEl;
            targetEl.classList.add('gf-tour-highlight');
            rect = targetEl.getBoundingClientRect();
            spotlight(rect);
        } else {
            backdrop.style.clipPath = '';
        }

        const guided = STEPS.filter(s => !s.welcome);
        const guidedIdx = guided.indexOf(step);
        const total = guided.length;
        const stepNum = guidedIdx + 1;

        const extraHtml = step.extraBtn
            ? `<a href="${step.extraBtn.href}" class="tour-btn-secondary"
             style="text-decoration:none;display:inline-flex;align-items:center;padding:9px 14px"
             onclick="localStorage.setItem('${STEP_KEY}','${idx + 1}')">
           ${step.extraBtn.label}
         </a>`
            : '';

        let nextAction;
        if (step.finish) {
            nextAction = `onclick="window._gfTour.finish()"`;
        } else if (step.nextHref) {
            nextAction = `onclick="window._gfTour.runOnNext(${idx});localStorage.setItem('${STEP_KEY}','${idx + 1}');location.href='${step.nextHref}'"`;
        } else {
            nextAction = `onclick="window._gfTour.next()"`;
        }

        tip.innerHTML = `
      ${dots(total, guidedIdx)}
      <div class="tour-badge"><span class="tour-badge-dot"></span>Paso ${stepNum} de ${total}</div>
      <span class="tour-icon">${step.emoji}</span>
      <div class="tour-title">${step.title}</div>
      <div class="tour-body">${step.body || ''}</div>
      <div class="tour-actions">
        ${extraHtml}
        <button class="tour-btn-primary" ${nextAction}>${step.nextLabel || 'Siguiente →'}</button>
        <button class="tour-skip" onclick="window._gfTour.dismiss()">Omitir</button>
      </div>`;

        positionTip(rect, step.arrow);
        setStep(idx);
    }

    // ── Public API ───────────────────────────────────────────────────────────────
    function clearHighlight() {
        if (highlightEl) { highlightEl.classList.remove('gf-tour-highlight'); highlightEl = null; }
    }
    function hideOverlay() {
        overlay.style.transition = 'opacity .35s ease';
        overlay.style.opacity = '0';
        overlay.style.pointerEvents = 'none';
        setTimeout(() => overlay.remove(), 400);
    }
    function dismiss() {
        clearHighlight();
        markFirstLogin();
        hideOverlay();
    }
    function finish() {
        clearHighlight();
        markFirstLogin();
        hideOverlay();
    }

    window._gfTour = {
        next() {
            clearHighlight();
            const step = STEPS[currentIdx];
            if (step && typeof step.onNext === 'function') step.onNext();
            // If step requests a pause (e.g. opens a modal), don't advance yet;
            // resume() will handle advancing once the modal closes.
            if (step && step.pauseOnNext) return;
            const nextIdx = currentIdx + 1;
            if (nextIdx < STEPS.length && STEPS[nextIdx].page === PAGE) {
                currentIdx = nextIdx;
                showStep(currentIdx);
            } else {
                setStep(nextIdx);
                finish();
            }
        },
        runOnNext(idx) {
            const step = STEPS[idx];
            if (step && typeof step.onNext === 'function') step.onNext();
        },
        // Hides tour overlay without dismissing (used while a modal is open)
        pause() {
            clearHighlight();
            overlay.style.display = 'none';
        },
        // Restores overlay and advances to the next step
        resume() {
            overlay.style.display = '';
            setTransitioning();
            const nextIdx = currentIdx + 1;
            if (nextIdx < STEPS.length && STEPS[nextIdx].page === PAGE) {
                currentIdx = nextIdx;
                showStep(currentIdx);
            } else {
                setStep(nextIdx);
                finish();
            }
        },
        dismiss,
        finish,
    };

    // ── Close on backdrop click ──────────────────────────────────────────────────
    backdrop.addEventListener('click', () => {
        if (transitioning) return; // ignore clicks during step transitions
        // Don't dismiss if any modal is currently open (e.g. branding modal)
        if (document.querySelector('.modal-overlay.open')) return;
        dismiss();
    });

    // ── Start ────────────────────────────────────────────────────────────────────
    function boot() {
        const step = STEPS[currentIdx];
        if (!step) return;
        setTimeout(() => {
            if (step.welcome) showWelcome(step);
            else showStep(currentIdx);
        }, 700);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
