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
        this._phase = 'idle';
        this._startTs = null;
        this._rafId = null;
        this._running = false;
        this._currentEx = null;
        // Idle personality
        this._idleAnim = null;   // { seq: [...], startMs: Date.now() }
        this._idleTimerId = null;
    }

    StickFigure.prototype.setExercise = function (name) {
        if (name === this._currentEx) return;
        this._currentEx = name;
        this._archetype = (typeof ExercisePoses !== 'undefined')
            ? ExercisePoses.getArchetype(name) : null;
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
        var delay = 4000 + Math.random() * 7000; // 4–11 seconds
        this._idleTimerId = setTimeout(function () {
            if (self._phase !== 'rest' && self._phase !== 'idle') return;
            if (!self._running) return;
            var idx = Math.floor(Math.random() * IDLE_SEQS.length);
            self._idleAnim = { seq: IDLE_SEQS[idx], startMs: Date.now() };
        }, delay);
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
                    this._drawPose(lerpPose(seq[s].pose, seq[s + 1].pose, p), W, H);
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
                } else {
                    // Animation complete — back to normal idle
                    this._idleAnim = null;
                    this._scheduleNextIdle();
                    this._drawPose(PS.stand, W, H);
                }
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
        this._drawPose(lerpPose(frames[from], frames[to], prog), W, H);
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
    };

    // ── StickmanWidget ────────────────────────────────────────────────────────
    function StickmanWidget(containerEl, opts) {
        this.container = containerEl;
        this.opts = opts || {};
        this._figure = null;
        this._tId = 'sm-tips-' + (Math.random() * 1e8 | 0);
        this._cId = 'sm-canvas-' + (Math.random() * 1e8 | 0);
        this._build();
    }

    StickmanWidget.prototype._build = function () {
        var size = this.opts.size || 'normal';
        var cW = (size === 'mini') ? 70 : 110;
        var cH = (size === 'mini') ? 120 : 185;
        var pad = (size === 'mini') ? '8px 10px' : '12px 14px';
        var html = '<div style="'
            + 'display:flex;flex-direction:column;align-items:center;gap:10px;'
            + 'padding:' + pad + ';'
            + 'background:rgba(255,255,255,0.04);'
            + 'border:1px solid rgba(255,255,255,0.09);'
            + 'border-radius:14px;width:100%;box-sizing:border-box;">'
            + '<canvas id="' + this._cId + '" width="' + cW + '" height="' + cH
            + '" style="display:block;"></canvas>';
        if (size !== 'mini') {
            html += '<div id="' + this._tId
                + '" style="display:flex;flex-direction:column;gap:4px;width:100%;"></div>';
        }
        html += '</div>';
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
        var tipsEl = document.getElementById(this._tId);
        if (!tipsEl) return;
        var tips = (typeof ExercisePoses !== 'undefined')
            ? ExercisePoses.getTips(exerciseName)
            : ['Mantén la postura', 'Core activo', 'Movimiento controlado'];
        var html = '';
        for (var i = 0; i < tips.length; i++) {
            html += '<div style="font-size:clamp(9px,1.1vw,12px);color:rgba(255,255,255,0.55);'
                + 'display:flex;align-items:flex-start;gap:5px;line-height:1.4;">'
                + '<span style="color:#ff6b35;flex-shrink:0;">&#9658;</span>'
                + tips[i] + '</div>';
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
