// GymFlow — StickFigure Animation Engine
// Renders a minimalist stick figure on a <canvas>, animating between keyframe poses.
// ES5 style to maximize browser compatibility.

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

    // ── StickFigure constructor ───────────────────────────────────────────────
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
    };

    StickFigure.prototype._render = function (elapsed) {
        var arch = this._archetype;
        var W = this.canvas.width;
        var H = this.canvas.height;
        var ctx = this.ctx;
        ctx.clearRect(0, 0, W, H);
        if (!arch) { this._drawPose(this._standPose(), W, H); return; }
        var isRest = (this._phase === 'rest' || this._phase === 'idle');
        var frames = (isRest && arch.restFrames && arch.restFrames.length)
            ? arch.restFrames : arch.frames;
        var dur = isRest ? (arch.restCycleDuration || 5000) : (arch.cycleDuration || 2000);
        var t = (elapsed % dur) / dur;
        var n = frames.length;
        var segT = t * n;
        var from = Math.floor(segT) % n;
        var to = (from + 1) % n;
        var progress = segT - Math.floor(segT);
        var pose = lerpPose(frames[from], frames[to], progress);
        this._drawPose(pose, W, H);
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

    StickFigure.prototype._standPose = function () {
        return {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .36], re: [.71, .36],
            lw: [.26, .51], rw: [.74, .51],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94]
        };
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
