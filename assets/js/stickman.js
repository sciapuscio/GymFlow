// GymFlow — StickFigure Animation Engine
// ES5 compatible — no template literals, no optional chaining, no classes.

(function (global) {

    var KEYS = ['head', 'neck', 'ls', 'rs', 'le', 're', 'lw', 'rw', 'lh', 'rh', 'lk', 'rk', 'la', 'ra'];

    var BONES = [
        ['neck', 'ls'], ['neck', 'rs'],
        ['ls', 'le'], ['le', 'lw'],
        ['rs', 're'], ['re', 'rw'],
        ['neck', 'lh'], ['neck', 'rh'],
        ['lh', 'rh'],
        ['lh', 'lk'], ['lk', 'la'],
        ['rh', 'rk'], ['rk', 'ra'],
    ];

    // ── Poses for idle animations ─────────────────────────────────────────────
    var PS = {
        stand: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .36], re: [.71, .36],
            lw: [.26, .51], rw: [.74, .51],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        },
        // Hop: slight crouch
        hop_crouch: {
            head: [.50, .12], neck: [.50, .20],
            ls: [.35, .27], rs: [.65, .27],
            le: [.28, .38], re: [.72, .38],
            lw: [.24, .50], rw: [.76, .50],
            lh: [.43, .63], rh: [.57, .63],
            lk: [.40, .76], rk: [.60, .76],
            la: [.42, .90], ra: [.58, .90]
        },
        // Hop: body in the air
        hop_air: {
            head: [.50, .02], neck: [.50, .10],
            ls: [.33, .17], rs: [.67, .17],
            le: [.27, .28], re: [.73, .28],
            lw: [.23, .38], rw: [.77, .38],
            lh: [.41, .52], rh: [.59, .52],
            lk: [.40, .66], rk: [.60, .66],
            la: [.41, .79], ra: [.59, .79]
        },
        // Wipe: right arm raised to forehead level
        wipe_up: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .36], re: [.68, .18],
            lw: [.26, .51], rw: [.58, .10],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        },
        // Wipe: forearm sweeps across forehead
        wipe_across: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .36], re: [.66, .16],
            lw: [.26, .51], rw: [.37, .10],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        },
        // Sit: down on the floor, legs out to sides
        sit: {
            head: [.50, .44], neck: [.50, .52],
            ls: [.39, .58], rs: [.61, .58],
            le: [.32, .70], re: [.68, .70],
            lw: [.26, .82], rw: [.74, .82],
            lh: [.41, .74], rh: [.59, .74],
            lk: [.27, .78], rk: [.73, .78],
            la: [.18, .76], ra: [.82, .76]
        },
        // Sit stretch: lean forward, hands reaching toward feet
        sit_stretch: {
            head: [.50, .61], neck: [.50, .68],
            ls: [.39, .71], rs: [.61, .71],
            le: [.30, .80], re: [.70, .80],
            lw: [.18, .78], rw: [.82, .78],
            lh: [.41, .74], rh: [.59, .74],
            lk: [.27, .78], rk: [.73, .78],
            la: [.18, .76], ra: [.82, .76]
        },
        // Wave: brazo derecho arriba, listo para saludar
        wave_up: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .36], re: [.74, .14],
            lw: [.26, .51], rw: [.86, .10],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        },
        // Wave: muñeca baja (medio saludo)
        wave_down: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .36], re: [.74, .14],
            lw: [.26, .51], rw: [.86, .24],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        },
        // Jog: rodilla izquierda arriba, brazo derecho adelante
        jog_left: {
            head: [.50, .06], neck: [.50, .14],
            ls: [.34, .21], rs: [.66, .21],
            le: [.25, .32], re: [.74, .30],
            lw: [.20, .44], rw: [.78, .24],
            lh: [.42, .56], rh: [.58, .56],
            lk: [.34, .64], rk: [.61, .74],
            la: [.35, .57], ra: [.63, .90]
        },
        // Jog: rodilla derecha arriba, brazo izquierdo adelante
        jog_right: {
            head: [.50, .06], neck: [.50, .14],
            ls: [.34, .21], rs: [.66, .21],
            le: [.26, .30], re: [.75, .32],
            lw: [.22, .24], rw: [.79, .44],
            lh: [.42, .56], rh: [.58, .56],
            lk: [.41, .74], rk: [.66, .64],
            la: [.38, .90], ra: [.66, .57]
        },
        // Scrub right: brazo izquierdo extendido a la izquierda frotando
        scrub_r: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.16, .20], re: [.71, .36],
            lw: [.00, .18], rw: [.74, .51],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        },
        // Scrub left: brazo izquierdo extendido un poco menos (ida y vuelta)
        scrub_l: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.18, .22], re: [.71, .36],
            lw: [.08, .20], rw: [.74, .51],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        }
    };

    // ── Idle animation sequences ──────────────────────────────────────────────
    // Each frame: {pose, ms} where ms = time to TRANSITION to the NEXT frame.
    // Last frame has ms = hold time before animation is marked "done".
    var IDLE_SEQS = [
        // 🦘 Hop!
        [
            { pose: PS.stand, ms: 200 },
            { pose: PS.hop_crouch, ms: 180 },
            { pose: PS.hop_air, ms: 260 },
            { pose: PS.hop_crouch, ms: 160 },
            { pose: PS.stand, ms: 400 }
        ],
        // 💦 Wipe sweat from forehead
        [
            { pose: PS.stand, ms: 350 },
            { pose: PS.wipe_up, ms: 300 },
            { pose: PS.wipe_across, ms: 450 },
            { pose: PS.wipe_up, ms: 280 },
            { pose: PS.stand, ms: 500 }
        ],
        // 👋 Wave + trot
        [
            { pose: PS.stand, ms: 200 },
            { pose: PS.wave_up, ms: 300 },  // levanta el brazo
            { pose: PS.wave_down, ms: 160 },  // ola 1
            { pose: PS.wave_up, ms: 160 },
            { pose: PS.wave_down, ms: 160 },  // ola 2
            { pose: PS.wave_up, ms: 160 },
            { pose: PS.wave_down, ms: 160 },  // ola 3
            { pose: PS.stand, ms: 300 },  // baja brazo
            { pose: PS.jog_left, ms: 180 },  // trote 1
            { pose: PS.stand, ms: 140 },
            { pose: PS.jog_right, ms: 180 },  // trote 2
            { pose: PS.stand, ms: 140 },
            { pose: PS.jog_left, ms: 180 },  // trote 3
            { pose: PS.stand, ms: 140 },
            { pose: PS.jog_right, ms: 180 },  // trote 4
            { pose: PS.stand, ms: 450 }   // para
        ],
        // 🧘 Sit and stretch
        [
            { pose: PS.stand, ms: 400 },
            { pose: PS.sit, ms: 700 },   // baja al piso
            { pose: PS.sit_stretch, ms: 2500 },  // estira y aguanta
            { pose: PS.sit, ms: 600 },   // vuelve a sentarse
            { pose: PS.stand, ms: 900 }    // se levanta despacio
        ]
    ];

    // ── Math helpers ──────────────────────────────────────────────────────────
    function easeInOut(t) {
        return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
    }
    function lerp(a, b, t) { return a + (b - a) * t; }

    function lerpPose(A, B, t) {
        var et = easeInOut(Math.max(0, Math.min(1, t)));
        var out = {};
        for (var i = 0; i < KEYS.length; i++) {
            var k = KEYS[i];
            var a = A[k] || [0.5, 0.5];
            var b = B[k] || [0.5, 0.5];
            out[k] = [lerp(a[0], b[0], et), lerp(a[1], b[1], et)];
        }
        return out;
    }

    // ── StickFigure ───────────────────────────────────────────────────────────
    function StickFigure(canvas, opts) {
        opts = opts || {};
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.color = opts.color || 'rgba(255,255,255,0.92)';
        this._archetype = null;
        this._equipment = null;  // equipment type string, e.g. 'barbell_back'
        this._phase = 'idle';
        this._startTs = null;
        this._rafId = null;
        this._running = false;
        this._currentEx = null;
        // Idle personality
        this._idleAnim = null;   // { seq: [...], startMs: Date.now() }
        this._idleTimerId = null;
        // Janitor easter egg
        this._janitor = null;    // { startMs, state, offsetX, targetX }
    }

    StickFigure.prototype.setExercise = function (name) {
        if (name === this._currentEx) return;
        this._currentEx = name;
        this._archetype = (typeof ExercisePoses !== 'undefined')
            ? ExercisePoses.getArchetype(name) : null;
        this._equipment = this._archetype ? (this._archetype.equipment || null) : null;
        this._startTs = null;
        if (!this._running) this._startLoop();
    };

    StickFigure.prototype.setPhase = function (phase) {
        if (phase === this._phase) return;
        this._phase = phase;
        this._startTs = null;
        // Manage idle personality timer
        if (this._idleTimerId) { clearTimeout(this._idleTimerId); this._idleTimerId = null; }
        this._idleAnim = null;
        if (phase === 'rest' || phase === 'idle') {
            this._scheduleNextIdle();
        }
    };

    StickFigure.prototype._scheduleNextIdle = function () {
        var self = this;
        var delay = 4000 + Math.random() * 7000; // 4-11 seconds
        this._idleTimerId = setTimeout(function () {
            if (self._phase !== 'rest' && self._phase !== 'idle') return;
            if (!self._running) return;
            // 25% chance of janitor easter egg during rest
            if (self._phase === 'rest' && Math.random() < 0.25) {
                self._startJanitor();
            } else {
                var idx = Math.floor(Math.random() * IDLE_SEQS.length);
                self._idleAnim = { seq: IDLE_SEQS[idx], startMs: Date.now() };
            }
        }, delay);
    };

    // ── Janitor Easter Egg ─────────────────────────────────────────────────────
    // States: 'run_out' → 'scrub' → 'run_back'
    StickFigure.prototype._startJanitor = function () {
        // Target X offset: run ~85% of canvas width to the left (toward RECUPERACIÓN text)
        var targetX = -(this.canvas.width * 0.82);
        this._janitor = {
            startMs: Date.now(),
            state: 'run_out',
            offsetX: 0,           // current horizontal translation
            targetX: targetX,
            scrubStartMs: 0,
            scrubDuration: 1800,  // ms spent scrubbing
            runSpeed: 0.65        // canvas-widths per second
        };
    };

    StickFigure.prototype._updateJanitor = function () {
        var j = this._janitor;
        if (!j) return false;
        var now = Date.now();
        var W = this.canvas.width;
        var spd = j.runSpeed * W / 1000; // px/ms

        if (j.state === 'run_out') {
            j.offsetX -= spd * 16;  // ~16ms per frame
            if (j.offsetX <= j.targetX) {
                j.offsetX = j.targetX;
                j.state = 'scrub';
                j.scrubStartMs = now;
            }
        } else if (j.state === 'scrub') {
            if (now - j.scrubStartMs >= j.scrubDuration) {
                j.state = 'run_back';
            }
        } else if (j.state === 'run_back') {
            j.offsetX += spd * 16;
            if (j.offsetX >= 0) {
                j.offsetX = 0;
                this._janitor = null;
                this._scheduleNextIdle();
                return false; // back to normal
            }
        }
        return true; // still animating
    };

    StickFigure.prototype._janitorPose = function (elapsed) {
        var j = this._janitor;
        if (!j) return PS.stand;
        if (j.state === 'run_out') {
            // Running pose alternating jog_right/jog_left (mirrored — going left)
            return (Math.floor(elapsed / 140) % 2 === 0) ? PS.jog_right : PS.jog_left;
        } else if (j.state === 'run_back') {
            return (Math.floor(elapsed / 140) % 2 === 0) ? PS.jog_left : PS.jog_right;
        } else {
            // Scrubbing: arm swipes left/right rapidly
            var t = Date.now() - j.scrubStartMs;
            return (Math.floor(t / 110) % 2 === 0) ? PS.scrub_r : PS.scrub_l;
        }
    };

    StickFigure.prototype._startLoop = function () {
        this._running = true;
        var self = this;
        var loop = function (ts) {
            if (!self._running) return;
            if (!self._startTs) self._startTs = ts;
            self._render(ts - self._startTs);
            self._rafId = requestAnimationFrame(loop);
        };
        this._rafId = requestAnimationFrame(loop);
    };

    StickFigure.prototype.stop = function () {
        this._running = false;
        if (this._rafId) { cancelAnimationFrame(this._rafId); this._rafId = null; }
        if (this._idleTimerId) { clearTimeout(this._idleTimerId); this._idleTimerId = null; }
        this._idleAnim = null;
    };

    StickFigure.prototype._render = function (elapsed) {
        var W = this.canvas.width;
        var H = this.canvas.height;
        var ctx = this.ctx;
        ctx.clearRect(0, 0, W, H);

        var isRest = (this._phase === 'rest' || this._phase === 'idle');

        // ── Idle personality animation takes priority ─────────────────────────
        if (isRest && this._idleAnim) {
            var seq = this._idleAnim.seq;
            var t = Date.now() - this._idleAnim.startMs;
            var cum = 0;
            var drawn = false;

            for (var s = 0; s < seq.length - 1; s++) {
                var segDur = seq[s].ms;
                if (t < cum + segDur) {
                    var p = (t - cum) / segDur;
                    var idlePose = lerpPose(seq[s].pose, seq[s + 1].pose, p);
                    this._drawPose(idlePose, W, H);
                    if (this._equipment && !isRest) this._drawEquipment(idlePose, W, H, elapsed);
                    drawn = true;
                    break;
                }
                cum += segDur;
            }

            if (!drawn) {
                // Last frame: hold it for its .ms, then finish
                var last = seq[seq.length - 1];
                if (t < cum + last.ms) {
                    this._drawPose(last.pose, W, H);
                    if (this._equipment && !isRest) this._drawEquipment(last.pose, W, H, elapsed);
                } else {
                    // Animation complete — back to normal idle
                    this._idleAnim = null;
                    this._scheduleNextIdle();
                    this._drawPose(PS.stand, W, H);
                }
            }
            return;
        }

        // ── Janitor easter egg ────────────────────────────────────────────────
        if (isRest && this._janitor) {
            var still = this._updateJanitor();
            var pose = this._janitorPose(elapsed);
            var ox = this._janitor ? this._janitor.offsetX : 0;
            ctx.save();
            ctx.translate(ox, 0);

            // While scrubbing, draw an eraser blob at the left wrist position
            if (this._janitor && this._janitor.state === 'scrub') {
                var lw2 = pose['lw'] || [0.05, 0.2];
                var ex = lw2[0] * W + ox;
                var ey = lw2[1] * H;
                // Draw eraser/cloth effect directly on main ctx (not translated)
                ctx.restore();
                ctx.save();
                ctx.translate(ox, 0);
                // Cloth rectangle that oscillates
                var st = this._janitor ? (Date.now() - this._janitor.scrubStartMs) : 0;
                var oscillate = Math.sin(st / 55) * 4;
                ctx.fillStyle = 'rgba(255,255,255,0.18)';
                ctx.beginPath();
                ctx.roundRect(lw2[0] * W - 14 + oscillate, lw2[1] * H - 8, 22, 14, 3);
                ctx.fill();
            }

            this._drawPose(pose, W, H);
            ctx.restore();

            if (!still && this._janitor === null) {
                this._drawPose(PS.stand, W, H);
            }
            return;
        }

        // ── Normal exercise animation ─────────────────────────────────────────
        var arch = this._archetype;
        if (!arch) { this._drawPose(PS.stand, W, H); return; }

        var frames = (isRest && arch.restFrames && arch.restFrames.length)
            ? arch.restFrames : arch.frames;
        var dur = isRest ? (arch.restCycleDuration || 5000) : (arch.cycleDuration || 2000);
        var t2 = (elapsed % dur) / dur;
        var n = frames.length;
        var segT = t2 * n;
        var from = Math.floor(segT) % n;
        var to = (from + 1) % n;
        var prog = segT - Math.floor(segT);
        var pose = lerpPose(frames[from], frames[to], prog);
        this._drawPose(pose, W, H);
        if (this._equipment && !isRest) this._drawEquipment(pose, W, H, elapsed);
    };

    StickFigure.prototype._drawPose = function (pose, W, H) {
        var ctx = this.ctx;
        var headR = Math.max(4, H * 0.062);
        var lw = Math.max(1.5, H * 0.017);
        function px(k) {
            var j = pose[k] || [0.5, 0.5];
            return [j[0] * W, j[1] * H];
        }
        ctx.strokeStyle = this.color;
        ctx.lineWidth = lw;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        for (var i = 0; i < BONES.length; i++) {
            var p1 = px(BONES[i][0]);
            var p2 = px(BONES[i][1]);
            ctx.moveTo(p1[0], p1[1]);
            ctx.lineTo(p2[0], p2[1]);
        }
        var pN = px('neck');
        var pH = px('head');
        ctx.moveTo(pN[0], pN[1]);
        ctx.lineTo(pH[0], pH[1]);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(pH[0], pH[1], headR, 0, Math.PI * 2);
        ctx.stroke();

        // ── Ground shadow line under feet ──────────────────────────────
        var ankleL = px('la');
        var ankleR = px('ra');
        var groundY = Math.max(ankleL[1], ankleR[1]) + lw * 0.5;
        var groundX = (ankleL[0] + ankleR[0]) / 2;
        ctx.beginPath();
        ctx.ellipse(groundX, groundY, W * 0.22, H * 0.014, 0, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,0.07)';
        ctx.fill();
    };

    // ── Equipment prop renderer ───────────────────────────────────────────────
    StickFigure.prototype._drawEquipment = function (pose, W, H, elapsed) {
        var ctx = this.ctx;
        var eq = this._equipment;
        var lw = Math.max(1.5, H * 0.017);
        elapsed = elapsed || 0;

        // Helper: convert normalised pose coords to canvas pixels
        function px(k) {
            var j = pose[k] || [0.5, 0.5];
            return [j[0] * W, j[1] * H];
        }
        // Helper: draw a filled circle
        function disc(x, y, r, fillColor) {
            ctx.beginPath();
            ctx.arc(x, y, r, 0, Math.PI * 2);
            ctx.fillStyle = fillColor;
            ctx.fill();
        }
        // Helper: draw a barbell between two world points, extending outward by `ext` px
        function barbell(x1, y1, x2, y2, ext, barbellColor, discColor, discR) {
            // Determine the direction of the bar
            var dx = x2 - x1;
            var dy = y2 - y1;
            var len = Math.sqrt(dx * dx + dy * dy) || 1;
            var ux = dx / len;
            var uy = dy / len;
            // Extend bar beyond wrists
            var ax = x1 - ux * ext;
            var ay = y1 - uy * ext;
            var bx = x2 + ux * ext;
            var by = y2 + uy * ext;
            // Bar shaft
            ctx.beginPath();
            ctx.moveTo(ax, ay);
            ctx.lineTo(bx, by);
            ctx.strokeStyle = barbellColor;
            ctx.lineWidth = lw * 0.85;
            ctx.lineCap = 'round';
            ctx.stroke();
            // Discs
            disc(ax, ay, discR, discColor);
            disc(bx, by, discR, discColor);
            // Collar rings (thin white accent)
            ctx.beginPath();
            ctx.arc(ax, ay, discR, 0, Math.PI * 2);
            ctx.arc(bx, by, discR, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(255,255,255,0.5)';
            ctx.lineWidth = lw * 0.5;
            ctx.stroke();
        }

        var barbColor = 'rgba(200,200,220,0.90)';
        var discFill = 'rgba(60,60,80,0.92)';
        var discR = Math.max(5, H * 0.058); // disc radius
        var ext = Math.max(8, W * 0.12);  // bar extension beyond wrists

        // ── BARBELL BACK (Back Squat) ─────────────────────────────────────────
        if (eq === 'barbell_back') {
            var ls = px('ls'); var rs = px('rs');
            // Bar sits on the traps — slightly behind shoulders, between ls and rs
            var barY = (ls[1] + rs[1]) / 2 + H * 0.02;
            barbell(ls[0], barY, rs[0], barY, ext, barbColor, discFill, discR);
            // Hands gripping the bar
            var lw2 = px('lw'); var rw2 = px('rw');
            disc(lw2[0], barY, lw * 1.2, 'rgba(255,255,255,0.55)');
            disc(rw2[0], barY, lw * 1.2, 'rgba(255,255,255,0.55)');
        }

        // ── BARBELL FRONT (Front Squat / Thruster) ────────────────────────────
        else if (eq === 'barbell_front') {
            var lw3 = px('lw'); var rw3 = px('rw');
            var ls2 = px('ls'); var rs2 = px('rs');
            // Bar rests in the front rack — at shoulder height, wrists close
            var barY2 = (ls2[1] + rs2[1]) / 2;
            barbell(ls2[0], barY2, rs2[0], barY2, ext, barbColor, discFill, discR);
            disc(lw3[0], barY2, lw * 1.2, 'rgba(255,255,255,0.55)');
            disc(rw3[0], barY2, lw * 1.2, 'rgba(255,255,255,0.55)');
        }

        // ── BARBELL FLOOR (Deadlift / RDL / Row) ─────────────────────────────
        else if (eq === 'barbell_floor') {
            var lw4 = px('lw'); var rw4 = px('rw');
            // Draw bar at each wrist's own X and Y — so it follows the hands
            // naturally as they travel from hip height down to shin level.
            barbell(lw4[0], lw4[1], rw4[0], rw4[1], ext, barbColor, discFill, discR);
        }


        // ── BARBELL OLYMPIC (Clean / Snatch — floor → rack → overhead) ───────
        else if (eq === 'barbell_olympic') {
            var lw5 = px('lw'); var rw5 = px('rw');
            var midY2 = (lw5[1] + rw5[1]) / 2;
            barbell(lw5[0], midY2, rw5[0], midY2, ext, barbColor, discFill, discR);
        }

        // ── BARBELL PRESS (Strict Press / Push Press / Jerk) ─────────────────
        else if (eq === 'barbell_press') {
            var lw6 = px('lw'); var rw6 = px('rw');
            var midY3 = (lw6[1] + rw6[1]) / 2;
            barbell(lw6[0], midY3, rw6[0], midY3, ext * 0.8, barbColor, discFill, discR);
        }

        // ── BARBELL BENCH (Bench Press — bar follows wrists) ─────────────────
        else if (eq === 'barbell_bench') {
            var lw7 = px('lw'); var rw7 = px('rw');
            var midY4 = (lw7[1] + rw7[1]) / 2;
            barbell(lw7[0], midY4, rw7[0], midY4, ext, barbColor, discFill, discR);
        }

        // ── KETTLEBELL ────────────────────────────────────────────────────────
        else if (eq === 'kettlebell') {
            var lw8 = px('lw'); var rw8 = px('rw');
            var kx = (lw8[0] + rw8[0]) / 2;
            var ky = (lw8[1] + rw8[1]) / 2 + H * 0.03;
            var kbR = Math.max(6, H * 0.065);  // kettlebell body radius
            var hW = kbR * 0.65;               // handle half-width
            var hH = kbR * 0.5;                // handle height above body
            // Body (filled sphere-like circle)
            ctx.beginPath();
            ctx.arc(kx, ky, kbR, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(40,40,55,0.92)';
            ctx.fill();
            ctx.strokeStyle = 'rgba(180,180,200,0.80)';
            ctx.lineWidth = lw * 0.9;
            ctx.stroke();
            // Handle (arc on top)
            ctx.beginPath();
            ctx.arc(kx, ky - kbR * 0.3, hW, Math.PI, 0, false);
            ctx.strokeStyle = 'rgba(200,200,220,0.90)';
            ctx.lineWidth = lw * 1.1;
            ctx.stroke();
            // Window accent line
            ctx.beginPath();
            ctx.arc(kx, ky + kbR * 0.1, kbR * 0.4, 0.3, Math.PI - 0.3);
            ctx.strokeStyle = 'rgba(255,255,255,0.18)';
            ctx.lineWidth = lw * 0.6;
            ctx.stroke();
        }

        // ── ASSAULT BIKE ──────────────────────────────────────────────────────
        else if (eq === 'assault_bike') {
            var lh2 = px('lh'); var rh2 = px('rh');
            var la = px('la'); var ra = px('ra');
            var lk = px('lk'); var rk = px('rk');
            var lw9 = px('lw'); var rw9 = px('rw');
            var bikeColor = 'rgba(160,180,210,0.70)';
            ctx.strokeStyle = bikeColor;
            ctx.lineWidth = lw * 0.9;
            ctx.lineCap = 'round';
            // Seat (horizontal line beneath hips)
            var seatY = (lh2[1] + rh2[1]) / 2 + H * 0.04;
            var seatX = (lh2[0] + rh2[0]) / 2;
            ctx.beginPath();
            ctx.moveTo(seatX - W * 0.07, seatY);
            ctx.lineTo(seatX + W * 0.07, seatY);
            ctx.stroke();
            // Seat post going down to BB
            var bbX = seatX;
            var bbY = (la[1] + ra[1]) / 2 - H * 0.04;
            ctx.beginPath();
            ctx.moveTo(seatX, seatY);
            ctx.lineTo(bbX, bbY);
            ctx.stroke();
            // Pedal cranks — small circles at ankles
            var crankR = Math.max(4, H * 0.032);
            ctx.beginPath();
            ctx.arc(bbX, bbY, crankR, 0, Math.PI * 2);
            ctx.stroke();
            // Pedal arms to ankles
            ctx.beginPath();
            ctx.moveTo(bbX, bbY); ctx.lineTo(la[0], la[1]);
            ctx.moveTo(bbX, bbY); ctx.lineTo(ra[0], ra[1]);
            ctx.stroke();
            // Fan wheel front (large circle in front)
            var wheelX = bbX + W * 0.25;
            var wheelY = bbY;
            var wheelR = Math.max(12, H * 0.13);
            ctx.beginPath();
            ctx.arc(wheelX, wheelY, wheelR, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(140,160,190,0.50)';
            ctx.stroke();
            // Spokes
            ctx.strokeStyle = 'rgba(140,160,190,0.35)';
            ctx.lineWidth = lw * 0.5;
            for (var spoke = 0; spoke < 8; spoke++) {
                var ang = (spoke / 8) * Math.PI * 2 + elapsed * 0.004;
                ctx.beginPath();
                ctx.moveTo(wheelX, wheelY);
                ctx.lineTo(wheelX + Math.cos(ang) * wheelR, wheelY + Math.sin(ang) * wheelR);
                ctx.stroke();
            }
            // Handlebar — connects wrists area to bike frame
            var hbY = (lw9[1] + rw9[1]) / 2;
            var hbX = (lw9[0] + rw9[0]) / 2;
            ctx.beginPath();
            ctx.moveTo(hbX, hbY - H * 0.04);
            ctx.lineTo(hbX, hbY + H * 0.04);
            ctx.strokeStyle = bikeColor;
            ctx.lineWidth = lw * 0.9;
            ctx.stroke();
        }

        // ── ROWING MACHINE ────────────────────────────────────────────────────
        else if (eq === 'rowing_machine') {
            var la2 = px('la'); var ra2 = px('ra');
            var lh3 = px('lh'); var rh3 = px('rh');
            var lw10 = px('lw'); var rw10 = px('rw');
            var rowColor = 'rgba(150,185,215,0.65)';
            // Rail (monorail under athlete)
            var railY = (la2[1] + ra2[1]) / 2 + H * 0.04;
            var railX1 = W * 0.05;
            var railX2 = W * 0.95;
            ctx.beginPath();
            ctx.moveTo(railX1, railY);
            ctx.lineTo(railX2, railY);
            ctx.strokeStyle = rowColor;
            ctx.lineWidth = lw * 0.8;
            ctx.stroke();
            // Seat slider
            var seatCx = (lh3[0] + rh3[0]) / 2;
            ctx.beginPath();
            ctx.rect(seatCx - W * 0.06, railY - H * 0.01, W * 0.12, H * 0.015);
            ctx.fillStyle = 'rgba(120,150,180,0.55)';
            ctx.fill();
            // Flywheel at front right
            var fwX = W * 0.88;
            var fwY = railY - H * 0.12;
            var fwR = Math.max(8, H * 0.08);
            ctx.beginPath();
            ctx.arc(fwX, fwY, fwR, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(140,165,195,0.55)';
            ctx.lineWidth = lw;
            ctx.stroke();
            // Chain / cord from hands to flywheel
            var handleX = (lw10[0] + rw10[0]) / 2;
            var handleY = (lw10[1] + rw10[1]) / 2;
            ctx.beginPath();
            ctx.moveTo(handleX, handleY);
            ctx.lineTo(fwX - fwR, fwY);
            ctx.strokeStyle = 'rgba(200,220,240,0.45)';
            ctx.lineWidth = lw * 0.6;
            ctx.setLineDash([3, 4]);
            ctx.stroke();
            ctx.setLineDash([]);
            // Handle bar (horizontal across hands)
            var hbHalf = W * 0.06;
            ctx.beginPath();
            ctx.moveTo(handleX - hbHalf, handleY);
            ctx.lineTo(handleX + hbHalf, handleY);
            ctx.strokeStyle = rowColor;
            ctx.lineWidth = lw * 1.1;
            ctx.stroke();
            // Footrests
            ctx.beginPath();
            ctx.moveTo(la2[0] - W * 0.04, la2[1]);
            ctx.lineTo(la2[0] + W * 0.04, la2[1]);
            ctx.moveTo(ra2[0] - W * 0.04, ra2[1]);
            ctx.lineTo(ra2[0] + W * 0.04, ra2[1]);
            ctx.strokeStyle = rowColor;
            ctx.lineWidth = lw * 0.8;
            ctx.stroke();
        }

        // ── WALL BALL ─────────────────────────────────────────────────────────
        else if (eq === 'wall_ball') {
            var lw11 = px('lw'); var rw11 = px('rw');
            // Radius
            var br = Math.max(7, H * 0.075);

            // ── Target mark on the wall (top-right) ──────────────────────────
            var targetX = W * 0.82;
            var targetY = H * 0.12;
            ctx.beginPath();
            ctx.arc(targetX, targetY, br * 0.55, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(255,255,255,0.12)';
            ctx.lineWidth = lw * 0.7;
            ctx.stroke();
            // crosshair
            ctx.beginPath();
            ctx.moveTo(targetX - br * 0.4, targetY);
            ctx.lineTo(targetX + br * 0.4, targetY);
            ctx.moveTo(targetX, targetY - br * 0.4);
            ctx.lineTo(targetX, targetY + br * 0.4);
            ctx.stroke();

            // ── Determine ball position along the throw arc ───────────────────
            // Cycle phases (normalised 0-1):
            //   0.00 – 0.30 : squat / hold (ball in hands)
            //   0.30 – 0.55 : throw — ball leaves and climbs to target
            //   0.55 – 0.80 : fall — ball drops back from target to hands
            //   0.80 – 1.00 : catch / squat again (ball in hands)
            var arch = this._archetype;
            var cycleDur = (arch && arch.cycleDuration) ? arch.cycleDuration : 2000;
            var cycleT = (elapsed % cycleDur) / cycleDur;  // 0 → 1

            // Wrist midpoint (where "hands" are this frame)
            var handX = (lw11[0] + rw11[0]) / 2;
            var handY = (lw11[1] + rw11[1]) / 2 - H * 0.02;

            var bx, by;
            var inFlight = false;

            if (cycleT < 0.30) {
                // Holding — ball in hands
                bx = handX; by = handY;
            } else if (cycleT < 0.55) {
                // Throwing — ball travels from hands to target (parabolic ease)
                var tp = (cycleT - 0.30) / 0.25;           // 0→1
                var te = tp < 0.5 ? 2 * tp * tp : -1 + (4 - 2 * tp) * tp; // easeInOut
                bx = handX + (targetX - handX) * te;
                by = handY + (targetY - handY) * te;
                inFlight = true;
            } else if (cycleT < 0.80) {
                // Falling — ball returns from target to hands
                var fp = (cycleT - 0.55) / 0.25;           // 0→1
                var fe = fp < 0.5 ? 2 * fp * fp : -1 + (4 - 2 * fp) * fp;
                bx = targetX + (handX - targetX) * fe;
                by = targetY + (handY - targetY) * fe;
                inFlight = true;
            } else {
                // Catching — ball in hands
                bx = handX; by = handY;
            }

            // Subtle motion blur trail during flight
            if (inFlight) {
                var trailAlpha = 0.12;
                for (var tr = 1; tr <= 3; tr++) {
                    ctx.beginPath();
                    ctx.arc(bx - (bx - handX) * tr * 0.06, by - (by - handY) * tr * 0.06,
                        br * (1 - tr * 0.15), 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(80,110,80,' + trailAlpha + ')';
                    ctx.fill();
                }
            }

            // Shadow (only when near ground level, fades during flight)
            var shadowAlpha = inFlight ? Math.max(0, 0.2 - (handY - by) / H * 0.8) : 0.25;
            ctx.beginPath();
            ctx.arc(bx + 2, by + 2, br, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(0,0,0,' + shadowAlpha + ')';
            ctx.fill();

            // Ball body
            var grad = ctx.createRadialGradient(bx - br * 0.3, by - br * 0.3, br * 0.1, bx, by, br);
            grad.addColorStop(0, 'rgba(100,130,100,0.92)');
            grad.addColorStop(1, 'rgba(40,60,40,0.95)');
            ctx.beginPath();
            ctx.arc(bx, by, br, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();
            ctx.strokeStyle = 'rgba(180,210,180,0.60)';
            ctx.lineWidth = lw * 0.7;
            ctx.stroke();

            // Seam lines
            ctx.beginPath();
            ctx.arc(bx, by, br * 0.7, 0.2, Math.PI - 0.2);
            ctx.strokeStyle = 'rgba(255,255,255,0.18)';
            ctx.lineWidth = lw * 0.5;
            ctx.stroke();
        }

        // ── JUMP ROPE ─────────────────────────────────────────────────────────
        else if (eq === 'jump_rope') {
            var lw12 = px('lw'); var rw12 = px('rw');
            var la3 = px('la'); var ra3 = px('ra');
            var head12 = px('head');
            var ropeCol = 'rgba(220,180,100,0.88)';

            // Handles (small rects at wrists) — always visible
            var hLen = Math.max(4, H * 0.04);
            ctx.fillStyle = ropeCol;
            ctx.beginPath();
            ctx.roundRect(lw12[0] - lw, lw12[1] - hLen / 2, lw * 2, hLen, 2);
            ctx.fill();
            ctx.beginPath();
            ctx.roundRect(rw12[0] - lw, rw12[1] - hLen / 2, lw * 2, hLen, 2);
            ctx.fill();

            // ── Full 360° rotation ──────────────────────────────────────────
            // theta goes 0 → 2π every cycle (≈ 650 ms ≈ 92 jumps/min)
            var cycleMs = 650;
            var theta = (elapsed % cycleMs) / cycleMs * Math.PI * 2;

            // Vertical extent of the rotation circle:
            //   top  = slightly above stickman head
            //   bot  = slightly below feet
            var bodyTop = head12[1] - H * 0.08;
            var bodyBot = Math.max(la3[1], ra3[1]) + H * 0.06;
            var centerY = (bodyTop + bodyBot) / 2;
            var radius = (bodyBot - bodyTop) / 2;

            // Arc midpoint orbits in the vertical plane
            var midX = (lw12[0] + rw12[0]) / 2;
            var arcMidY = centerY + radius * Math.cos(theta);

            // depth: +1 = fully in front of stickman, -1 = fully behind
            var depth = Math.sin(theta);

            // Opacity & weight decrease when rope is behind the body
            var ropAlpha = depth >= 0 ? 0.88 : 0.18;
            var ropLW = lw * (depth >= 0 ? 0.78 : 0.40);

            ctx.beginPath();
            ctx.moveTo(lw12[0], lw12[1]);
            ctx.quadraticCurveTo(midX, arcMidY, rw12[0], rw12[1]);
            if (depth < 0) { ctx.setLineDash([4, 6]); }
            ctx.strokeStyle = 'rgba(220,180,100,' + ropAlpha + ')';
            ctx.lineWidth = ropLW;
            ctx.lineCap = 'round';
            ctx.stroke();
            ctx.setLineDash([]);
        }

        // ── DIP BAR (paralelas) ────────────────────────────────────────────────
        else if (eq === 'dip_bar') {
            var lw13 = px('lw'); var rw13 = px('rw');
            var barColor = 'rgba(200,200,220,0.85)';
            var postColor = 'rgba(160,160,180,0.70)';
            // Bar sits slightly above the wrists (hands grip from above)
            var barY = Math.min(lw13[1], rw13[1]) - H * 0.015;
            var barL = lw13[0] - W * 0.08;  // extends a bit beyond each hand
            var barR = rw13[0] + W * 0.08;
            // Horizontal bar
            ctx.beginPath();
            ctx.moveTo(barL, barY);
            ctx.lineTo(barR, barY);
            ctx.strokeStyle = barColor;
            ctx.lineWidth = lw * 1.2;
            ctx.lineCap = 'round';
            ctx.stroke();
            // Left post (vertical support)
            ctx.beginPath();
            ctx.moveTo(barL + W * 0.03, barY);
            ctx.lineTo(barL + W * 0.03, barY + H * 0.18);
            ctx.strokeStyle = postColor;
            ctx.lineWidth = lw * 0.9;
            ctx.stroke();
            // Right post
            ctx.beginPath();
            ctx.moveTo(barR - W * 0.03, barY);
            ctx.lineTo(barR - W * 0.03, barY + H * 0.18);
            ctx.strokeStyle = postColor;
            ctx.lineWidth = lw * 0.9;
            ctx.stroke();
            // Knobs at base of posts
            disc(barL + W * 0.03, barY + H * 0.18, lw * 1.2, postColor);
            disc(barR - W * 0.03, barY + H * 0.18, lw * 1.2, postColor);
        }

        // ── RINGS (anillas) ────────────────────────────────────────────────────
        else if (eq === 'rings') {
            var lw14 = px('lw'); var rw14 = px('rw');
            var ringColor = 'rgba(210,190,130,0.90)';
            var ropeRingColor = 'rgba(200,200,200,0.55)';
            var ringR = Math.max(6, H * 0.055);
            var ringLW = lw * 1.1;
            // Subtle sway animation
            var sway = Math.sin(elapsed / 1200) * W * 0.012;
            // Ring centres: a bit above and around the wrists
            var lRingX = lw14[0] + sway;
            var lRingY = lw14[1] - ringR * 0.5;
            var rRingX = rw14[0] + sway;
            var rRingY = rw14[1] - ringR * 0.5;
            // Suspension straps (from top of canvas)
            var strapTopY = H * 0.04;
            ctx.setLineDash([3, 4]);
            ctx.beginPath();
            ctx.moveTo(lRingX, strapTopY);
            ctx.lineTo(lRingX, lRingY - ringR);
            ctx.moveTo(rRingX, strapTopY);
            ctx.lineTo(rRingX, rRingY - ringR);
            ctx.strokeStyle = ropeRingColor;
            ctx.lineWidth = lw * 0.7;
            ctx.stroke();
            ctx.setLineDash([]);
            // Left ring
            ctx.beginPath();
            ctx.arc(lRingX, lRingY, ringR, 0, Math.PI * 2);
            ctx.strokeStyle = ringColor;
            ctx.lineWidth = ringLW;
            ctx.stroke();
            // Right ring
            ctx.beginPath();
            ctx.arc(rRingX, rRingY, ringR, 0, Math.PI * 2);
            ctx.strokeStyle = ringColor;
            ctx.lineWidth = ringLW;
            ctx.stroke();
        }
    };


    // ── StickmanWidget ────────────────────────────────────────────────────────
    function StickmanWidget(containerEl, opts) {
        this.container = containerEl;
        this.opts = opts || {};
        this._figure = null;
        this._tId = 'sm-tips-' + (Math.random() * 1e8 | 0);
        this._nId = 'sm-name-' + (Math.random() * 1e8 | 0);
        this._cId = 'sm-canvas-' + (Math.random() * 1e8 | 0);
        this._build();
    }

    StickmanWidget.prototype._build = function () {
        var size = this.opts.size || 'normal';
        var isMini = size === 'mini';
        var cW = isMini ? 70 : 160;
        var cH = isMini ? 120 : 260;
        var html;

        if (isMini) {
            // ── Mini (instructor panel): compact column layout ──────────────
            html = '<div style="'
                + 'display:flex;flex-direction:column;align-items:center;gap:10px;'
                + 'padding:8px 10px;'
                + 'background:rgba(255,255,255,0.04);'
                + 'border:1px solid rgba(255,255,255,0.09);'
                + 'border-radius:14px;width:100%;box-sizing:border-box;">'
                + '<canvas id="' + this._cId + '" width="' + cW + '" height="' + cH
                + '" style="display:block;"></canvas>'
                + '</div>';
        } else {
            // ── Normal (TV display): row — canvas left, name+tips right ───
            // Readable from across the room.
            html = '<div style="'
                + 'display:flex;flex-direction:row;align-items:center;gap:18px;'
                + 'padding:14px 16px;'
                + 'background:rgba(255,255,255,0.04);'
                + 'border:1px solid rgba(255,255,255,0.09);'
                + 'border-radius:14px;width:100%;box-sizing:border-box;">'
                + '<canvas id="' + this._cId + '" width="' + cW + '" height="' + cH
                + '" style="display:block;flex-shrink:0;"></canvas>'
                + '<div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:8px;">'
                + '<div id="' + this._nId + '" style="'
                + 'font-family:&quot;Bebas Neue&quot;,sans-serif;'
                + 'font-size:clamp(22px,2.8vw,40px);'
                + 'letter-spacing:.05em;line-height:1.1;'
                + 'color:rgba(255,255,255,0.95);text-transform:uppercase;"></div>'
                + '<div id="' + this._tId
                + '" style="display:flex;flex-direction:column;gap:8px;"></div>'
                + '</div>'
                + '</div>';
        }
        this.container.innerHTML = html;
        var canvas = this.container.querySelector('canvas');
        this._figure = new StickFigure(canvas, {
            color: this.opts.color || 'rgba(255,255,255,0.88)'
        });
    };


    StickmanWidget.prototype.update = function (exerciseName, phase) {
        if (!this._figure) return;
        this._figure.setExercise(exerciseName || '');
        this._figure.setPhase(phase || 'idle');
        this._updateTips(exerciseName);
    };

    StickmanWidget.prototype._updateTips = function (exerciseName) {
        var isMini = this.opts.size === 'mini';

        // Update exercise name label (display screen only)
        if (!isMini) {
            var nameEl = document.getElementById(this._nId);
            if (nameEl) nameEl.textContent = exerciseName || '';
        }

        var tipsEl = document.getElementById(this._tId);
        if (!tipsEl) return;
        var tips = (typeof ExercisePoses !== 'undefined')
            ? ExercisePoses.getTips(exerciseName)
            : ['Mantén la postura', 'Core activo', 'Movimiento controlado'];
        var html = '';
        for (var i = 0; i < tips.length; i++) {
            if (isMini) {
                html += '<div style="font-size:clamp(9px,1.1vw,12px);color:rgba(255,255,255,0.55);'
                    + 'display:flex;align-items:flex-start;gap:5px;line-height:1.4;">'
                    + '<span style="color:#ff6b35;flex-shrink:0;">&#9658;</span>'
                    + tips[i] + '</div>';
            } else {
                // Large text for TV readability
                html += '<div style="font-size:clamp(15px,1.9vw,24px);font-weight:600;'
                    + 'color:rgba(255,255,255,0.75);'
                    + 'display:flex;align-items:flex-start;gap:8px;line-height:1.4;">'
                    + '<span style="color:#ff6b35;flex-shrink:0;margin-top:2px;">&#9658;</span>'
                    + tips[i] + '</div>';
            }
        }
        tipsEl.innerHTML = html;
    };


    StickmanWidget.prototype.stop = function () {
        if (this._figure) this._figure.stop();
    };

    // ── Expose ────────────────────────────────────────────────────────────────
    global.StickFigure = StickFigure;
    global.StickmanWidget = StickmanWidget;

})(window);
