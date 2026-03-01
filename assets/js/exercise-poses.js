// GymFlow — Exercise Pose Database
// All joint coordinates normalized [x, y] in 0..1 space (relative to canvas size)
// Joint keys: head neck ls rs le re lw rw lh rh lk rk la ra
// ls=left shoulder, rs=right shoulder, le=left elbow, re=right elbow
// lh=left hip, rh=right hip, lk=left knee, rk=right knee, la=left ankle, ra=right ankle
'use strict';

(function (global) {
    // ── Raw pose library ───────────────────────────────────────────────────────
    const P = {
        stand: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .36], re: [.71, .36],
            lw: [.26, .51], rw: [.74, .51],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        // ── SQUAT ──────────────────────────────────────────────────────────
        squat_up: {
            // Ready position: hip crease slightly open, arms in front at shoulder height
            head: [.50, .11], neck: [.50, .19],
            ls: [.36, .26], rs: [.64, .26],
            le: [.32, .34], re: [.68, .34],
            lw: [.32, .40], rw: [.68, .40],
            lh: [.41, .61], rh: [.59, .61],
            lk: [.40, .78], rk: [.60, .78],
            la: [.41, .94], ra: [.59, .94],
        },
        squat_down: {
            head: [.50, .26], neck: [.50, .34],
            ls: [.36, .40], rs: [.64, .40],
            le: [.36, .48], re: [.64, .48],
            lw: [.42, .53], rw: [.58, .53],
            lh: [.39, .55], rh: [.61, .55],
            lk: [.22, .73], rk: [.78, .73],
            la: [.27, .93], ra: [.73, .93],
        },
        // ── LUNGE ──────────────────────────────────────────────────────────
        lunge_l: {
            head: [.50, .15], neck: [.50, .23],
            ls: [.35, .29], rs: [.65, .29],
            le: [.29, .42], re: [.71, .42],
            lw: [.26, .56], rw: [.74, .56],
            lh: [.44, .56], rh: [.56, .56],
            lk: [.32, .75], rk: [.66, .72],
            la: [.25, .89], ra: [.70, .94],
        },
        lunge_r: {
            head: [.50, .15], neck: [.50, .23],
            ls: [.35, .29], rs: [.65, .29],
            le: [.29, .42], re: [.71, .42],
            lw: [.26, .56], rw: [.74, .56],
            lh: [.44, .56], rh: [.56, .56],
            lk: [.34, .72], rk: [.68, .75],
            la: [.30, .94], ra: [.75, .89],
        },
        // ── HINGE (deadlift / RDL) ─────────────────────────────────────────
        hinge_top: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .22], rs: [.65, .22],
            le: [.31, .35], re: [.69, .35],
            lw: [.31, .50], rw: [.69, .50],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        hinge_bottom: {
            // Torso ~45° forward, knees slightly bent, hands at shin level
            head: [.45, .28], neck: [.46, .36],
            ls: [.36, .40], rs: [.60, .38],
            le: [.34, .52], re: [.62, .50],
            lw: [.35, .68], rw: [.63, .67],
            lh: [.44, .54], rh: [.58, .54],
            lk: [.43, .73], rk: [.59, .73],
            la: [.42, .94], ra: [.58, .94],
        },
        // ── PUSH HORIZONTAL (push-up / bench) ──────────────────────────────
        pushh_up: {
            head: [.50, .19], neck: [.50, .24],
            ls: [.36, .27], rs: [.64, .27],
            le: [.35, .36], re: [.65, .36],
            lw: [.32, .46], rw: [.68, .46],
            lh: [.42, .55], rh: [.58, .55],
            lk: [.42, .73], rk: [.58, .73],
            la: [.42, .93], ra: [.58, .93],
        },
        pushh_down: {
            head: [.50, .25], neck: [.50, .30],
            ls: [.35, .33], rs: [.65, .33],
            le: [.31, .41], re: [.69, .41],
            lw: [.32, .47], rw: [.68, .47],
            lh: [.42, .54], rh: [.58, .54],
            lk: [.42, .72], rk: [.58, .72],
            la: [.42, .92], ra: [.58, .92],
        },
        // ── PUSH VERTICAL (press overhead) ─────────────────────────────────
        pressv_rack: {
            // Front rack: elbows high and forward, wrists BELOW elbows (y > elbow y)
            head: [.50, .09], neck: [.50, .17],
            ls: [.35, .23], rs: [.65, .23],
            le: [.33, .28], re: [.67, .28],
            lw: [.38, .33], rw: [.62, .33],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        pressv_top: {
            head: [.50, .17], neck: [.50, .24],
            ls: [.35, .29], rs: [.65, .29],
            le: [.33, .13], re: [.67, .13],
            lw: [.34, .04], rw: [.66, .04],
            lh: [.42, .62], rh: [.58, .62],
            lk: [.42, .79], rk: [.58, .79],
            la: [.42, .95], ra: [.58, .95],
        },
        // ── PULL VERTICAL (pull-up) ─────────────────────────────────────────
        pullv_hang: {
            head: [.50, .17], neck: [.50, .24],
            ls: [.35, .28], rs: [.65, .28],
            le: [.35, .16], re: [.65, .16],
            lw: [.35, .07], rw: [.65, .07],
            lh: [.42, .62], rh: [.58, .62],
            lk: [.42, .80], rk: [.58, .80],
            la: [.42, .95], ra: [.58, .95],
        },
        pullv_top: {
            head: [.50, .07], neck: [.50, .13],
            ls: [.35, .17], rs: [.65, .17],
            le: [.30, .09], re: [.70, .09],
            lw: [.35, .04], rw: [.65, .04],
            lh: [.42, .52], rh: [.58, .52],
            lk: [.42, .70], rk: [.58, .70],
            la: [.42, .87], ra: [.58, .87],
        },
        // ── PULL HORIZONTAL (row) ───────────────────────────────────────────
        pullh_ext: {
            head: [.50, .17], neck: [.50, .25],
            ls: [.33, .27], rs: [.67, .27],
            le: [.31, .38], re: [.69, .38],
            lw: [.30, .50], rw: [.70, .50],
            lh: [.42, .56], rh: [.58, .56],
            lk: [.42, .74], rk: [.58, .74],
            la: [.42, .94], ra: [.58, .94],
        },
        pullh_cont: {
            head: [.50, .16], neck: [.50, .24],
            ls: [.33, .27], rs: [.67, .27],
            le: [.24, .31], re: [.76, .31],
            lw: [.32, .35], rw: [.68, .35],
            lh: [.42, .56], rh: [.58, .56],
            lk: [.42, .74], rk: [.58, .74],
            la: [.42, .94], ra: [.58, .94],
        },
        // ── KETTLEBELL SWING ────────────────────────────────────────────────
        swing_back: {
            // Hip hinge: knees narrower, torso hinged, arms swinging back between legs
            head: [.50, .20], neck: [.50, .28],
            ls: [.34, .29], rs: [.66, .29],
            le: [.38, .38], re: [.62, .38],
            lw: [.44, .46], rw: [.56, .46],
            lh: [.41, .52], rh: [.59, .52],
            lk: [.40, .72], rk: [.60, .72],
            la: [.40, .93], ra: [.60, .93],
        },
        swing_top: {
            head: [.50, .09], neck: [.50, .17],
            ls: [.35, .23], rs: [.65, .23],
            le: [.29, .30], re: [.71, .30],
            lw: [.24, .37], rw: [.76, .37],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        // ── SIT-UP ──────────────────────────────────────────────────────────
        situp_down: {
            // Torso reclinado hacia atrás, rodillas dobladas, manos trás la cabeza
            head: [.50, .35], neck: [.50, .42],
            ls: [.38, .50], rs: [.62, .50],
            le: [.32, .36], re: [.68, .36],
            lw: [.42, .30], rw: [.58, .30],
            lh: [.42, .60], rh: [.58, .60],
            lk: [.32, .74], rk: [.68, .74],
            la: [.28, .90], ra: [.72, .90],
        },
        situp_up: {
            // Torso erguido hacia las rodillas, brazos extendidos al frente
            head: [.50, .10], neck: [.50, .18],
            ls: [.36, .25], rs: [.64, .25],
            le: [.29, .36], re: [.71, .36],
            lw: [.27, .50], rw: [.73, .50],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.32, .72], rk: [.68, .72],
            la: [.28, .90], ra: [.72, .90],
        },
        // ── MOUNTAIN CLIMBERS ───────────────────────────────────────────────
        mc_l: {
            head: [.50, .19], neck: [.50, .25],
            ls: [.36, .28], rs: [.64, .28],
            le: [.34, .37], re: [.66, .37],
            lw: [.32, .46], rw: [.68, .46],
            lh: [.42, .55], rh: [.58, .55],
            lk: [.40, .66], rk: [.58, .75],
            la: [.44, .75], ra: [.58, .95],
        },
        mc_r: {
            head: [.50, .19], neck: [.50, .25],
            ls: [.36, .28], rs: [.64, .28],
            le: [.34, .37], re: [.66, .37],
            lw: [.32, .46], rw: [.68, .46],
            lh: [.42, .55], rh: [.58, .55],
            lk: [.42, .75], rk: [.60, .66],
            la: [.42, .95], ra: [.56, .75],
        },
        // ── RUN ─────────────────────────────────────────────────────────────
        run_r: {
            head: [.50, .09], neck: [.50, .17],
            ls: [.35, .23], rs: [.65, .23],
            le: [.26, .32], re: [.74, .32],
            lw: [.22, .22], rw: [.71, .43],
            lh: [.43, .55], rh: [.57, .55],
            lk: [.36, .69], rk: [.62, .71],
            la: [.31, .87], ra: [.65, .87],
        },
        run_l: {
            head: [.50, .09], neck: [.50, .17],
            ls: [.35, .23], rs: [.65, .23],
            le: [.74, .32], re: [.26, .32],
            lw: [.71, .43], rw: [.22, .22],
            lh: [.43, .55], rh: [.57, .55],
            lk: [.62, .69], rk: [.36, .71],
            la: [.65, .87], ra: [.31, .87],
        },
        // ── JUMP ────────────────────────────────────────────────────────────
        jump_crouch: {
            head: [.50, .24], neck: [.50, .32],
            ls: [.36, .38], rs: [.64, .38],
            le: [.30, .46], re: [.70, .46],
            lw: [.26, .55], rw: [.74, .55],
            lh: [.40, .55], rh: [.60, .55],
            lk: [.33, .73], rk: [.67, .73],
            la: [.37, .94], ra: [.63, .94],
        },
        jump_air: {
            head: [.50, .05], neck: [.50, .13],
            ls: [.35, .19], rs: [.65, .19],
            le: [.28, .28], re: [.72, .28],
            lw: [.25, .38], rw: [.75, .38],
            lh: [.42, .54], rh: [.58, .54],
            lk: [.42, .70], rk: [.58, .70],
            la: [.42, .84], ra: [.58, .84],
        },
        // ── PLANK (iso) ─────────────────────────────────────────────────────
        plank: {
            head: [.50, .20], neck: [.50, .25],
            ls: [.36, .28], rs: [.64, .28],
            le: [.35, .37], re: [.65, .37],
            lw: [.32, .47], rw: [.68, .47],
            lh: [.42, .55], rh: [.58, .55],
            lk: [.42, .73], rk: [.58, .73],
            la: [.42, .93], ra: [.58, .93],
        },
        plank_b: {
            // Breathing exhale: hips very slightly raised, back slightly rounded
            head: [.50, .17], neck: [.50, .23],
            ls: [.36, .26], rs: [.64, .26],
            le: [.35, .35], re: [.65, .35],
            lw: [.32, .45], rw: [.68, .45],
            lh: [.42, .50], rh: [.58, .50],
            lk: [.42, .70], rk: [.58, .70],
            la: [.42, .90], ra: [.58, .90],
        },
        // ── OLYMPIC (clean phases) ──────────────────────────────────────────
        olympic_start: {
            head: [.50, .18], neck: [.50, .26],
            ls: [.32, .27], rs: [.68, .27],
            le: [.30, .37], re: [.70, .37],
            lw: [.30, .49], rw: [.70, .49],
            lh: [.41, .53], rh: [.59, .53],
            lk: [.38, .72], rk: [.62, .72],
            la: [.39, .94], ra: [.61, .94],
        },
        olympic_pull: {
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .22], rs: [.65, .22],
            le: [.30, .31], re: [.70, .31],
            lw: [.30, .41], rw: [.70, .41],
            lh: [.42, .57], rh: [.58, .57],
            lk: [.42, .74], rk: [.58, .74],
            la: [.42, .93], ra: [.58, .93],
        },
        olympic_catch: {
            head: [.50, .09], neck: [.50, .17],
            ls: [.35, .23], rs: [.65, .23],
            le: [.30, .29], re: [.70, .29],
            lw: [.37, .25], rw: [.63, .25],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        // ── BIKE (assault bike) ─────────────────────────────────────────────
        bike_a: {
            head: [.50, .12], neck: [.50, .20],
            ls: [.35, .26], rs: [.65, .26],
            le: [.30, .34], re: [.66, .30],
            lw: [.28, .44], rw: [.62, .21],
            lh: [.42, .57], rh: [.58, .57],
            lk: [.39, .76], rk: [.60, .70],
            la: [.40, .94], ra: [.60, .86],
        },
        bike_b: {
            head: [.50, .12], neck: [.50, .20],
            ls: [.35, .26], rs: [.65, .26],
            le: [.66, .34], re: [.30, .30],
            lw: [.62, .44], rw: [.28, .21],
            lh: [.42, .57], rh: [.58, .57],
            lk: [.60, .76], rk: [.39, .70],
            la: [.60, .94], ra: [.40, .86],
        },
        // ── TOES-TO-BAR (kip) ────────────────────────────────────────────────
        t2b_hang: {
            // Dead hang, body slightly hollow
            head: [.50, .17], neck: [.50, .24],
            ls: [.35, .28], rs: [.65, .28],
            le: [.35, .16], re: [.65, .16],
            lw: [.35, .06], rw: [.65, .06],
            lh: [.42, .64], rh: [.58, .64],
            lk: [.42, .81], rk: [.58, .81],
            la: [.42, .96], ra: [.58, .96],
        },
        t2b_top: {
            // Feet at bar height: hips pike, legs parallel or above horizontal
            head: [.50, .15], neck: [.50, .22],
            ls: [.35, .26], rs: [.65, .26],
            le: [.35, .14], re: [.65, .14],
            lw: [.35, .05], rw: [.65, .05],
            lh: [.42, .38], rh: [.58, .38],
            lk: [.42, .22], rk: [.58, .22],
            la: [.42, .08], ra: [.58, .08],
        },
        // ── CURL ────────────────────────────────────────────────────────────
        curl_down: {
            // Arms at sides, elbow extended
            head: [.50, .08], neck: [.50, .16],
            ls: [.38, .24], rs: [.62, .24],
            le: [.38, .42], re: [.62, .42],
            lw: [.38, .60], rw: [.62, .60],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        curl_up: {
            // Elbow fully flexed, forearm vertical, upper arm stays fixed
            head: [.50, .08], neck: [.50, .16],
            ls: [.38, .24], rs: [.62, .24],
            le: [.38, .42], re: [.62, .42],
            lw: [.35, .24], rw: [.65, .24],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        // ── LATERAL RAISE ───────────────────────────────────────────────────
        raise_side: {
            // Arms abducted to shoulder height (90°), slight elbow bend
            head: [.50, .08], neck: [.50, .16],
            ls: [.35, .23], rs: [.65, .23],
            le: [.22, .22], re: [.78, .22],
            lw: [.13, .26], rw: [.87, .26],
            lh: [.42, .58], rh: [.58, .58],
            lk: [.42, .76], rk: [.58, .76],
            la: [.42, .94], ra: [.58, .94],
        },
        // ── GHD ─────────────────────────────────────────────────────────────
        ghd_sit: {
            // Seated upright on GHD, hips at edge, knees secured
            head: [.50, .09], neck: [.50, .17],
            ls: [.35, .23], rs: [.65, .23],
            le: [.30, .30], re: [.70, .30],
            lw: [.29, .38], rw: [.71, .38],
            lh: [.42, .55], rh: [.58, .55],
            lk: [.38, .72], rk: [.62, .72],
            la: [.36, .90], ra: [.64, .90],
        },
        ghd_back: {
            // Full extension: torso parallel or past horizontal, arms behind head
            head: [.50, .78], neck: [.50, .70],
            ls: [.38, .62], rs: [.62, .62],
            le: [.35, .50], re: [.65, .50],
            lw: [.40, .42], rw: [.60, .42],
            lh: [.42, .55], rh: [.58, .55],
            lk: [.38, .72], rk: [.62, .72],
            la: [.36, .90], ra: [.64, .90],
        },
    };

    // ── Technique tips per exercise ────────────────────────────────────────────
    const TIPS = {
        'Bench Press': ['Arco de espalda controlado', 'Codos a 45° del torso', 'Toca el pecho con la barra'],
        'Press de Banca': ['Arco de espalda controlado', 'Codos a 45° del torso', 'Toca el pecho con la barra'],
        'Dips': ['Ligera inclinación adelante', 'Codos al cuerpo', 'Baja hasta 90°'],
        'Fondos': ['Ligera inclinación adelante', 'Codos al cuerpo', 'Baja hasta 90°'],
        'Push-ups': ['Plank activo todo el tiempo', 'Codos a 45°', 'Pecho toca el suelo'],
        'Flexiones': ['Plank activo todo el tiempo', 'Codos a 45°', 'Pecho toca el suelo'],
        'Ring Push-ups': ['Core activo, no caer', 'Codos a 45°', 'Control en bajada'],
        'Bent Over Row': ['Espalda neutra, no redonda', 'Tira con el codo', 'Toca el abdomen'],
        'Deadlift': ['Espalda recta y tensa', 'Barra pegada al cuerpo', 'Empuja el piso con los pies'],
        'Peso Muerto': ['Espalda recta y tensa', 'Barra pegada al cuerpo', 'Empuja el piso con los pies'],
        'Lat Pulldown': ['Pecho arriba y abierto', 'Tira hacia la clavícula', 'No te balancees'],
        'Pull-ups': ['Pecho al frente arriba', 'Codos hacia las caderas', 'Control en bajada'],
        'Dominadas': ['Pecho al frente arriba', 'Codos hacia las caderas', 'Control en bajada'],
        'Ring Rows': ['Cuerpo recto como tabla', 'Tira hasta el pecho', 'Pausa arriba'],
        'Chest-to-Bar Pull-ups': ['Explosivo, pecho toca la barra', 'Kipping controlado', 'Mantén la tensión'],
        'Handstand Push-ups': ['Manos a ancho de hombros', 'Baja la cabeza al suelo', 'Empuja fuerte'],
        'Strict Press': ['Core y glúteos activos', 'Barra sobre la cabeza alineada', 'Cabeza hacia adelante al subir'],
        'Press Militar Estricto': ['Core y glúteos activos', 'Barra sobre la cabeza alineada', 'Cabeza adelante al subir'],
        'Push Press': ['Dip corto de rodillas', 'Explosión de piernas', 'Bloquea los brazos arriba'],
        'Jerk': ['Split explosivo', 'Bloquea codos arriba', 'Pies paralelos al recuperar'],
        'Split Jerk': ['Split explosivo', 'Bloquea codos arriba', 'Pies paralelos al recuperar'],
        'Lateral Raises': ['Codos ligeramente doblados', 'Sube hasta el hombro', 'Control en bajada'],
        'Elevaciones Laterales': ['Codos ligeramente doblados', 'Sube hasta el hombro', 'Control en bajada'],
        'Bicep Curls': ['Codos fijos al costado', 'Supina en la subida', 'No balancees el torso'],
        'Curl de Bíceps': ['Codos fijos al costado', 'Supina en la subida', 'No balancees el torso'],
        'Muscle UP': ['Kip explosivo', 'Transición rápida', 'Empuja al fondo'],
        'Ring Dips': ['Anillas pegadas al cuerpo', 'Baja controlado', 'Extiende al tope'],
        'Fondos en Anillas': ['Anillas pegadas al cuerpo', 'Baja controlado', 'Extiende al tope'],
        'Tricep Extensions': ['Codos fijos arriba', 'Solo mueve el antebrazo', 'Control en bajada'],
        'Extensiones de Tríceps': ['Codos fijos arriba', 'Solo mueve el antebrazo', 'Control en bajada'],
        'GHD Sit-ups': ['Glúteos en el borde', 'Extiende completamente atrás', 'Core apretado al subir'],
        'Abdominales en GHD': ['Glúteos en el borde', 'Extiende completamente atrás', 'Core apretado al subir'],
        'L-Sit': ['Brazos extendidos', 'Piernas paralelas al suelo', 'Hombros abajo'],
        'Mountain Climbers': ['Plank rígido', 'Rodillas al pecho alternando', 'No levantes las caderas'],
        'Escaladores': ['Plank rígido', 'Rodillas al pecho alternando', 'No levantes las caderas'],
        'Plank': ['Cuerpo en línea recta', 'Glúteos y core activos', 'Respira de forma continua'],
        'Plancha': ['Cuerpo en línea recta', 'Glúteos y core activos', 'Respira de forma continua'],
        'Russian Twists': ['Pies levantados del suelo', 'Rota con el torso', 'Baja controlado'],
        'Rotaciones Rusas': ['Pies levantados del suelo', 'Rota con el torso', 'Baja controlado'],
        'Sit-ups': ['Pies anclados', 'Sube con el abdomen', 'Baja controlado'],
        'Abdominales': ['Pies anclados', 'Sube con el abdomen', 'Baja controlado'],
        'Toes-to-Bar': ['Kip controlado', 'Pies tocan la barra', 'Balancea atrás para ritmo'],
        'Pies a la Barra': ['Kip controlado', 'Pies tocan la barra', 'Balancea atrás para ritmo'],
        'Air Squats': ['Talones en el piso', 'Rodillas sobre pies', 'Cadera bajo las rodillas'],
        'Sentadillas': ['Talones en el piso', 'Rodillas sobre pies', 'Cadera bajo las rodillas'],
        'Back Squat': ['Barra en el trapecio', 'Pecho arriba', 'Rompe con las caderas primero'],
        'Sentadilla con Barra': ['Barra en el trapecio', 'Pecho arriba', 'Rompe con caderas primero'],
        'Box Jumps': ['Aterriza suave con rodillas', 'Sube explosivo', 'Desciende caminando'],
        'Saltos al Cajón': ['Aterriza suave con rodillas', 'Sube explosivo', 'Desciende caminando'],
        'Box Step-Ups': ['Pie completo en el cajón', 'Empuja con el talón', 'Alterna piernas'],
        'Subidas al Cajón': ['Pie completo en el cajón', 'Empuja con el talón', 'Alterna piernas'],
        'Bulgarian Split Squat': ['Pie trasero en el banco', 'Rodilla delantera sobre pie', 'Torso erguido'],
        'Sentadilla Búlgara': ['Pie trasero en el banco', 'Rodilla delantera sobre pie', 'Torso erguido'],
        'Front Squat': ['Codos altos en rack', 'Barra en la clavícula', 'Torso vertical'],
        'Sentadilla Frontal': ['Codos altos en rack', 'Barra en la clavícula', 'Torso vertical'],
        'Lunges': ['Rodilla delantera sobre el pie', 'Torso recto', 'Paso largo y firme'],
        'Estocadas': ['Rodilla delantera sobre el pie', 'Torso recto', 'Paso largo y firme'],
        'Romanian Deadlift': ['Espalda plana siempre', 'Barra pegada al cuerpo', 'Sientes los isquios'],
        'Peso Muerto Rumano': ['Espalda plana siempre', 'Barra pegada al cuerpo', 'Sientes los isquios'],
        'Wall Ball': ['Sentadilla profunda', 'Lanza a la x del target', 'Atrapa en cuclillas'],
        'Lanzamiento a la Pared': ['Sentadilla profunda', 'Lanza a la x del target', 'Atrapa en cuclillas'],
        'Assault Bike': ['Ritmo parejo de brazos/piernas', 'Sella con el core', 'Respira de forma continua'],
        'Bicicleta Assault': ['Ritmo parejo de brazos/piernas', 'Sella con el core', 'Respira de forma continua'],
        'Burpees': ['Plank rígido al bajar', 'Salta explosivo arriba', 'Palmas sobre la cabeza'],
        'Clean & Jerk': ['Primer pull controlado', 'Triple extensión explosiva', 'Codos altos en rack'],
        'Cargada y Envión': ['Primer pull controlado', 'Triple extensión explosiva', 'Codos altos en rack'],
        'Kettlebell Swing': ['Bisagra de cadera, no sentadilla', 'Proyecta con las caderas', 'Brazos como péndulo'],
        'Balanceo con Pesa Rusa': ['Bisagra de cadera', 'Proyecta con las caderas', 'Brazos como péndulo'],
        'Power Clean': ['Arranca desde el piso controlado', 'Jala alto con los codos', 'Catch en cuarto de sentadilla'],
        'Cargada de Potencia': ['Arranca desde el piso', 'Jala alto con los codos', 'Catch en cuarto sentadilla'],
        'Rowing': ['Piernas → cadera → brazos', 'Retorno: brazos → cadera → piernas', 'Cuerpo en 11 en el catch'],
        'Remo Ergómetro': ['Piernas → cadera → brazos', 'Retorno: brazos → cadera → piernas', 'Cuerpo en 11 en el catch'],
        'Snatch': ['Agarre ancho', 'Triple extensión explosiva', 'Catch con brazos bloqueados'],
        'Arranque': ['Agarre ancho', 'Triple extensión explosiva', 'Catch con brazos bloqueados'],
        'Thruster': ['Sentadilla profunda', 'Usa las piernas para el press', 'Bloquea arriba completamente'],
        'Double Unders': ['Muñecas, no brazos', 'Salto vertical pequeño', 'Mantén ritmo constante'],
        'Doble Comba': ['Muñecas, no brazos', 'Salto vertical pequeño', 'Mantén ritmo constante'],
        'Jump Rope': ['Salto pequeño y ligero', 'Ritmo constante', 'Aterrizaje en punta de pies'],
        'Soga': ['Salto pequeño y ligero', 'Ritmo constante', 'Aterrizaje en punta de pies'],
        'Run 400m': ['Cadencia alta > velocidad de zancada', 'Brazos a 90°', 'Respira rítmico'],
        'Carrera 400m': ['Cadencia alta > velocidad de zancada', 'Brazos a 90°', 'Respira rítmico'],
        'Shuttle Run': ['Frena con control', 'Toca el suelo', 'Acelera de cero cada vez'],
        'Carrera de Ida y Vuelta': ['Frena con control', 'Toca el suelo', 'Acelera de cero'],
        // New exercises
        'Hip Thrust': ['Espalda en el banco', 'Empuja con los talones', 'Glúteos al tope arriba'],
        'Empuje de Cadera': ['Espalda en el banco', 'Empuja con los talones', 'Glúteos al tope arriba'],
        'Farmer Carry': ['Hombros abajo y atrás', 'Core apretado', 'Pasos cortos y firmes'],
        'Cargada del Granjero': ['Hombros abajo y atrás', 'Core apretado', 'Pasos cortos y firmes'],
        'Hang Power Clean': ['Start desde la cadera', 'Triple extensión explosiva', 'Codos rápidos al frente'],
        'Cargada de Potencia desde Colgado': ['Start desde la cadera', 'Triple extensión explosiva', 'Codos rápidos al frente'],
        'Push Jerk': ['Dip corto y vertical', 'Empuja explosivo y baja bajo la barra', 'Pies paralelos al recuperar'],
    };

    // ── Archetype mapping ──────────────────────────────────────────────────────
    const EXERCISE_ARCHETYPE = {
        // Squat pattern
        'Air Squats': 'squat', 'Sentadillas': 'squat',
        'Back Squat': 'squat', 'Sentadilla con Barra': 'squat',
        'Front Squat': 'squat', 'Sentadilla Frontal': 'squat',
        'Wall Ball': 'squat', 'Lanzamiento a la Pared': 'squat',
        'Thruster': 'squat',
        // Lunge pattern
        'Lunges': 'lunge', 'Estocadas': 'lunge',
        'Bulgarian Split Squat': 'lunge', 'Sentadilla Búlgara': 'lunge',
        'Box Step-Ups': 'lunge', 'Subidas al Cajón': 'lunge',
        // Hinge pattern
        'Deadlift': 'hinge', 'Peso Muerto': 'hinge',
        'Romanian Deadlift': 'hinge', 'Peso Muerto Rumano': 'hinge',
        // Push horizontal
        'Push-ups': 'push_h', 'Flexiones': 'push_h',
        'Ring Push-ups': 'push_h', 'Flexiones en Anillas': 'push_h',
        'Bench Press': 'push_h', 'Press de Banca': 'push_h',
        'Dips': 'push_h', 'Fondos': 'push_h',
        // Push vertical
        'Strict Press': 'push_v', 'Press Militar Estricto': 'push_v',
        'Push Press': 'push_v',
        'Jerk': 'push_v', 'Split Jerk': 'push_v',
        'Handstand Push-ups': 'push_v', 'Flexiones en Pino': 'push_v',
        // Pull vertical
        'Pull-ups': 'pull_v', 'Dominadas': 'pull_v',
        'Chest-to-Bar Pull-ups': 'pull_v', 'Dominadas Pecho a Barra': 'pull_v',
        'Lat Pulldown': 'pull_v', 'Jalón al Pecho': 'pull_v',
        'Muscle UP': 'muscle_up', 'Muscle Up': 'muscle_up',
        'Toes-to-Bar': 't2b', 'Pies a la Barra': 't2b',
        'Ring Dips': 'dip', 'Fondos en Anillas': 'dip',
        // Pull horizontal
        'Bent Over Row': 'pull_h', 'Remo Inclinado': 'pull_h',
        'Ring Rows': 'pull_h', 'Remo en Anillas': 'pull_h',
        // Swing
        'Kettlebell Swing': 'swing', 'Balanceo con Pesa Rusa': 'swing',
        // Core dynamic
        'Sit-ups': 'core_dyn', 'Abdominales': 'core_dyn',
        'GHD Sit-ups': 'ghd', 'Abdominales en GHD': 'ghd',
        'Russian Twists': 'core_dyn', 'Rotaciones Rusas': 'core_dyn',
        // Core iso
        'Plank': 'core_iso', 'Plancha': 'core_iso',
        'L-Sit': 'core_iso',
        // Mountain climbers
        'Mountain Climbers': 'mc', 'Escaladores': 'mc',
        // Jump
        'Box Jumps': 'jump', 'Saltos al Cajón': 'jump',
        'Burpees': 'jump',
        'Jump Rope': 'jump', 'Soga': 'jump',
        'Double Unders': 'jump', 'Doble Comba': 'jump',
        // Run
        'Run 400m': 'run', 'Carrera 400m': 'run',
        'Shuttle Run': 'run', 'Carrera de Ida y Vuelta': 'run',
        // Bike
        'Assault Bike': 'bike', 'Bicicleta Assault': 'bike',
        'Rowing': 'row', 'Remo Ergómetro': 'row',
        // Olympic
        'Clean & Jerk': 'olympic', 'Cargada y Envión': 'olympic',
        'Power Clean': 'olympic', 'Cargada de Potencia': 'olympic',
        'Snatch': 'olympic', 'Arranque': 'olympic',
        'Hang Power Clean': 'olympic', 'Cargada de Potencia desde Colgado': 'olympic',
        'Push Jerk': 'push_v', 'Hip Thrust': 'hinge',
        'Empuje de Cadera': 'hinge',
        'Farmer Carry': 'hinge', 'Cargada del Granjero': 'hinge',
        // Arms — each has its own distinct archetype
        'Bicep Curls': 'curl', 'Curl de Bíceps': 'curl',
        'Tricep Extensions': 'press_down', 'Extensiones de Tríceps': 'press_down',
        'Lateral Raises': 'raise', 'Elevaciones Laterales': 'raise',
    };

    // ── Equipment per exercise ─────────────────────────────────────────────────
    // Maps specific exercise names to the prop that should be drawn alongside
    // the stickman. Exercises not listed here render with no equipment.
    const EXERCISE_EQUIPMENT = {
        // Barbell on back (high-bar / low-bar)
        'Back Squat': 'barbell_back', 'Sentadilla con Barra': 'barbell_back',
        // Barbell in front rack / overhead rack
        'Front Squat': 'barbell_front', 'Sentadilla Frontal': 'barbell_front',
        'Thruster': 'barbell_front',
        // Barbell at floor / hip hinge pulls
        'Deadlift': 'barbell_floor', 'Peso Muerto': 'barbell_floor',
        'Romanian Deadlift': 'barbell_floor', 'Peso Muerto Rumano': 'barbell_floor',
        'Bent Over Row': 'barbell_floor', 'Remo Inclinado': 'barbell_floor',
        // Olympic lifts — barbell travels from floor through pull to catch
        'Power Clean': 'barbell_olympic', 'Cargada de Potencia': 'barbell_olympic',
        'Clean & Jerk': 'barbell_olympic', 'Cargada y Envión': 'barbell_olympic',
        'Snatch': 'barbell_olympic', 'Arranque': 'barbell_olympic',
        // Barbell overhead press
        'Strict Press': 'barbell_press', 'Press Militar Estricto': 'barbell_press',
        'Push Press': 'barbell_press',
        'Jerk': 'barbell_press', 'Split Jerk': 'barbell_press',
        // Barbell bench press
        'Bench Press': 'barbell_bench', 'Press de Banca': 'barbell_bench',
        // Kettlebell
        'Kettlebell Swing': 'kettlebell', 'Balanceo con Pesa Rusa': 'kettlebell',
        // Machines / cardio equipment
        'Assault Bike': 'assault_bike', 'Bicicleta Assault': 'assault_bike',
        'Rowing': 'rowing_machine', 'Remo Ergómetro': 'rowing_machine',
        // Other equipment
        'Wall Ball': 'wall_ball', 'Lanzamiento a la Pared': 'wall_ball',
        'Jump Rope': 'jump_rope', 'Soga': 'jump_rope',
        'Double Unders': 'jump_rope', 'Doble Comba': 'jump_rope',
        // Bodyweight with apparatus
        'Dips': 'dip_bar', 'Fondos': 'dip_bar',
        'Ring Dips': 'rings', 'Fondos en Anillas': 'rings',
        'Ring Push-ups': 'rings', 'Flexiones en Anillas': 'rings',
        'Ring Rows': 'rings', 'Remo en Anillas': 'rings',
        'Muscle UP': 'rings', 'Muscle Up': 'rings',
        // New exercises
        'Hang Power Clean': 'barbell_olympic', 'Cargada de Potencia desde Colgado': 'barbell_olympic',
        'Push Jerk': 'barbell_press',
        'Farmer Carry': 'kettlebell',
    };


    // ── Archetype definitions ──────────────────────────────────────────────────
    const ARCHETYPES = {
        // ── Compound movements ────────────────────────────────────────────────
        muscle_up: { frames: [P.pullv_hang, P.pullv_top, P.pressv_rack, P.pressv_top, P.pressv_rack, P.pullv_hang], restFrames: [P.pullv_hang], cycleDuration: 3200, restCycleDuration: 5000 },
        squat: { frames: [P.squat_up, P.squat_down, P.squat_up], restFrames: [P.stand], cycleDuration: 2200, restCycleDuration: 5000 },
        lunge: { frames: [P.stand, P.lunge_l, P.stand, P.lunge_r], restFrames: [P.stand], cycleDuration: 2600, restCycleDuration: 5000 },
        hinge: { frames: [P.hinge_top, P.hinge_bottom, P.hinge_top], restFrames: [P.stand], cycleDuration: 2800, restCycleDuration: 5000 },
        olympic: { frames: [P.olympic_start, P.olympic_pull, P.olympic_catch, P.olympic_pull, P.olympic_start], restFrames: [P.stand], cycleDuration: 2400, restCycleDuration: 5000 },
        // ── Push patterns ─────────────────────────────────────────────────────
        push_h: { frames: [P.pushh_up, P.pushh_down, P.pushh_up], restFrames: [P.pushh_up], cycleDuration: 1800, restCycleDuration: 5000 },
        push_v: { frames: [P.pressv_rack, P.pressv_top, P.pressv_rack], restFrames: [P.stand], cycleDuration: 2000, restCycleDuration: 5000 },
        dip: { frames: [P.pressv_top, P.pushh_down, P.pressv_top], restFrames: [P.stand], cycleDuration: 1800, restCycleDuration: 5000 },
        press_down: { frames: [P.pressv_top, P.pressv_rack, P.pressv_top], restFrames: [P.stand], cycleDuration: 1800, restCycleDuration: 5000 },
        raise: { frames: [P.stand, P.raise_side, P.stand], restFrames: [P.stand], cycleDuration: 2200, restCycleDuration: 5000 },
        // ── Pull patterns ─────────────────────────────────────────────────────
        pull_v: { frames: [P.pullv_hang, P.pullv_top, P.pullv_hang], restFrames: [P.pullv_hang], cycleDuration: 2400, restCycleDuration: 5000 },
        pull_h: { frames: [P.pullh_ext, P.pullh_cont, P.pullh_ext], restFrames: [P.pullh_ext], cycleDuration: 2000, restCycleDuration: 5000 },
        t2b: { frames: [P.t2b_hang, P.t2b_top, P.t2b_hang], restFrames: [P.pullv_hang], cycleDuration: 2000, restCycleDuration: 5000 },
        curl: { frames: [P.curl_down, P.curl_up, P.curl_down], restFrames: [P.stand], cycleDuration: 1800, restCycleDuration: 5000 },
        // ── Core ─────────────────────────────────────────────────────────────
        core_dyn: { frames: [P.situp_down, P.situp_up, P.situp_down], restFrames: [P.stand], cycleDuration: 2200, restCycleDuration: 5000 },
        core_iso: { frames: [P.plank, P.plank_b, P.plank], restFrames: [P.stand], cycleDuration: 4000, restCycleDuration: 5000 },
        ghd: { frames: [P.ghd_sit, P.ghd_back, P.ghd_sit], restFrames: [P.stand], cycleDuration: 2800, restCycleDuration: 5000 },
        // ── Cardio & conditioning ─────────────────────────────────────────────
        swing: { frames: [P.swing_back, P.swing_top, P.swing_back], restFrames: [P.stand], cycleDuration: 1600, restCycleDuration: 5000 },
        mc: { frames: [P.mc_l, P.mc_r], restFrames: [P.pushh_up], cycleDuration: 800, restCycleDuration: 5000 },
        run: { frames: [P.run_r, P.run_l], restFrames: [P.stand], cycleDuration: 700, restCycleDuration: 5000 },
        jump: { frames: [P.jump_crouch, P.jump_air, P.jump_crouch], restFrames: [P.stand], cycleDuration: 1200, restCycleDuration: 5000 },
        bike: { frames: [P.bike_a, P.bike_b], restFrames: [P.bike_a], cycleDuration: 900, restCycleDuration: 5000 },
        row: { frames: [P.pullh_ext, P.pullh_cont, P.pullh_ext], restFrames: [P.pullh_ext], cycleDuration: 1800, restCycleDuration: 5000 },
    };

    // ── Public API ─────────────────────────────────────────────────────────────
    global.ExercisePoses = {
        getArchetype(exerciseName) {
            const name = exerciseName ? exerciseName.trim() : '';
            const key = EXERCISE_ARCHETYPE[name];
            const arch = key ? ARCHETYPES[key] : ARCHETYPES['squat']; // default fallback
            // Merge the per-exercise equipment tag so the renderer knows what to draw
            const equipment = EXERCISE_EQUIPMENT[name] || null;
            return Object.assign({}, arch, { equipment: equipment });
        },
        getTips(exerciseName) {
            return TIPS[exerciseName] || TIPS[exerciseName?.trim()] || ['Mantén la postura', 'Core activo', 'Movimiento controlado'];
        },
    };
})(window);
