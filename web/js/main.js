/* ============================================================
   GYMFLOW — Landing Page JS
   - Navbar scroll behavior
   - Mobile menu toggle
   - Scroll-reveal animations (IntersectionObserver)
   - Hero particle system
   - Smooth anchor scrolling
   ============================================================ */

(function () {
    'use strict';

    // ---- NAVBAR SCROLL BEHAVIOR ----
    const navbar = document.getElementById('navbar');
    let lastScroll = 0;

    window.addEventListener('scroll', () => {
        const scrollY = window.scrollY;
        if (scrollY > 20) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
        lastScroll = scrollY;
    }, { passive: true });

    // ---- MOBILE MENU ----
    const navToggle = document.getElementById('navToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    let menuOpen = false;

    function openMenu() {
        mobileMenu.classList.add('open');
        navToggle.setAttribute('aria-expanded', 'true');
        menuOpen = true;
        // animate hamburger to X
        const spans = navToggle.querySelectorAll('span');
        spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
    }

    function closeMenu() {
        mobileMenu.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
        menuOpen = false;
        const spans = navToggle.querySelectorAll('span');
        spans[0].style.transform = '';
        spans[1].style.opacity = '';
        spans[2].style.transform = '';
    }

    if (navToggle) {
        navToggle.addEventListener('click', () => {
            menuOpen ? closeMenu() : openMenu();
        });
    }

    // Close mobile menu on link click
    document.querySelectorAll('.mobile-link').forEach(link => {
        link.addEventListener('click', closeMenu);
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (menuOpen && !mobileMenu.contains(e.target) && !navToggle.contains(e.target)) {
            closeMenu();
        }
    });

    // Close on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menuOpen) closeMenu();
    });

    // ---- SCROLL-REVEAL ANIMATIONS ----
    const revealElements = document.querySelectorAll('.reveal, .reveal-delay-1, .reveal-delay-2');

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px'
        });

        revealElements.forEach(el => observer.observe(el));
    } else {
        // Fallback for older browsers
        revealElements.forEach(el => el.classList.add('revealed'));
    }

    // ---- HERO PARTICLES ----
    const heroParticles = document.getElementById('heroParticles');

    function createParticle() {
        if (!heroParticles) return;
        const particle = document.createElement('div');
        particle.className = 'particle';

        // Randomize starting position and appearance
        const startX = Math.random() * 100;
        const duration = 8 + Math.random() * 12;
        const drift = (Math.random() - 0.5) * 80;
        const size = 1 + Math.random() * 2;
        const delay = Math.random() * 8;

        particle.style.cssText = `
      left: ${startX}%;
      width: ${size}px;
      height: ${size}px;
      animation-duration: ${duration}s;
      animation-delay: ${delay}s;
      --drift: ${drift}px;
    `;

        heroParticles.appendChild(particle);

        // Remove after animation completes
        setTimeout(() => {
            if (particle.parentNode) particle.parentNode.removeChild(particle);
        }, (duration + delay) * 1000);
    }

    // Create initial batch
    for (let i = 0; i < 20; i++) {
        createParticle();
    }
    // Continue spawning
    setInterval(createParticle, 1200);

    // ---- SMOOTH SCROLL FOR ANCHOR LINKS ----
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                const offsetTop = target.getBoundingClientRect().top + window.scrollY - 80;
                window.scrollTo({ top: offsetTop, behavior: 'smooth' });
            }
        });
    });

    // ---- ACTIVE NAV LINK ON SCROLL ----
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-links a');

    function updateActiveNav() {
        const scrollY = window.scrollY + 120;
        let current = '';
        sections.forEach(section => {
            if (section.offsetTop <= scrollY) {
                current = section.getAttribute('id');
            }
        });
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    }

    window.addEventListener('scroll', updateActiveNav, { passive: true });

    // ---- COUNTER ANIMATION FOR METRIC VALUES ----
    function animateCounter(el) {
        const text = el.textContent;
        if (text === '∞' || text.includes('★') || text.includes('Gratis') ||
            text.includes('Consultar') || text.includes('medida')) return;

        // Extract numeric part
        const match = text.match(/[+-]?\d+/);
        if (!match) return;

        const target = parseInt(match[0]);
        const prefix = text.substring(0, match.index);
        const suffix = text.substring(match.index + match[0].length);
        const duration = 1200;
        const start = performance.now();

        function tick(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            const current = Math.round(eased * target);
            el.textContent = prefix + current + suffix;
            if (progress < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    }

    // Observe metric values
    const metricObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const metricEls = entry.target.querySelectorAll('.metric-value, .stat-value');
                metricEls.forEach(animateCounter);
                metricObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    document.querySelectorAll('.benefits-grid, .hero-stats').forEach(el => {
        metricObserver.observe(el);
    });

    // ---- HERO CTA hover ripple effect ----
    document.querySelectorAll('.btn-primary').forEach(btn => {
        btn.addEventListener('mouseenter', function (e) {
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.style.cssText = `
        position:absolute;
        border-radius:50%;
        background:rgba(255,255,255,0.15);
        transform:scale(0);
        animation:ripple-out 0.6s ease-out forwards;
        pointer-events:none;
        width:100px; height:100px;
        left:${e.clientX - rect.left - 50}px;
        top:${e.clientY - rect.top - 50}px;
      `;
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            setTimeout(() => { if (ripple.parentNode) ripple.parentNode.removeChild(ripple); }, 700);
        });
    });

    // Add CSS for ripple animation
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
    @keyframes ripple-out {
      to { transform: scale(4); opacity: 0; }
    }
    .nav-links a.active { color: var(--color-primary) !important; }
  `;
    document.head.appendChild(rippleStyle);

    // ---- BILLING TOGGLE (mensual / anual) ----
    const billingToggle = document.getElementById('billingToggle');
    if (billingToggle) {
        let isAnual = false;

        const plans = [
            { amount: document.querySelector('[data-mensual="12.000"]'), note: document.getElementById('noteInstructor'), anualLabel: '$10.000 / mes · pagás $120.000 / año' },
            { amount: document.querySelector('[data-mensual="29.000"]'), note: document.getElementById('noteGimnasio'), anualLabel: '$24.200 / mes · pagás $290.400 / año' },
            { amount: document.querySelector('[data-mensual="55.000"]'), note: document.getElementById('noteCentro'), anualLabel: '$45.800 / mes · pagás $549.600 / año' }
        ];

        billingToggle.addEventListener('click', () => {
            isAnual = !isAnual;
            billingToggle.setAttribute('aria-checked', String(isAnual));
            billingToggle.classList.toggle('active', isAnual);

            plans.forEach(p => {
                if (!p.amount) return;
                if (isAnual) {
                    p.amount.textContent = p.amount.dataset.anual;
                    if (p.note) { p.note.textContent = '2 meses gratis incluidos'; p.note.classList.add('has-note'); }
                } else {
                    p.amount.textContent = p.amount.dataset.mensual;
                    if (p.note) { p.note.innerHTML = '&nbsp;'; p.note.classList.remove('has-note'); }
                }
            });
        });
    }

})();
