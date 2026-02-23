<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
$user = getCurrentUser();
if ($user) {
    $dest = match ($user['role']) {
        'superadmin' => BASE_URL . '/pages/superadmin/dashboard.php',
        'admin' => BASE_URL . '/pages/admin/dashboard.php',
        default => BASE_URL . '/pages/instructor/dashboard.php',
    };
    header("Location: $dest");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymFlow — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL ?>/assets/css/design-system.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        /* Animated background */
        .login-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: radial-gradient(ellipse at 20% 50%, rgba(0, 245, 212, 0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(255, 107, 53, 0.06) 0%, transparent 50%),
                #080810;
        }

        .grid-lines {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.015) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .glow-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            animation: orb-float 8s ease infinite;
        }

        .glow-orb-1 {
            width: 400px;
            height: 400px;
            background: rgba(0, 245, 212, 0.12);
            top: -100px;
            left: -100px;
        }

        .glow-orb-2 {
            width: 300px;
            height: 300px;
            background: rgba(255, 107, 53, 0.08);
            bottom: -50px;
            right: -50px;
            animation-delay: -4s;
        }

        @keyframes orb-float {

            0%,
            100% {
                transform: translate(0, 0);
            }

            50% {
                transform: translate(20px, 20px);
            }
        }

        /* Login card */
        .login-card {
            position: relative;
            z-index: 1;
            width: min(420px, 95vw);
            background: rgba(15, 15, 26, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 32px 80px rgba(0, 0, 0, 0.6);
        }

        .login-brand {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-logo {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #00f5d4, #ff6b35);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-family: 'Bebas Neue', serif;
            font-size: 28px;
            color: #080810;
            letter-spacing: 0.05em;
        }

        .login-title {
            font-family: 'Bebas Neue', serif;
            font-size: 42px;
            letter-spacing: 0.1em;
            background: linear-gradient(135deg, #00f5d4, #ff6b35);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-subtitle {
            font-size: 13px;
            color: #8888aa;
            margin-top: 4px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .error-toast {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            color: #ef4444;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #00f5d4, #00c9b1);
            color: #080810;
            font-size: 15px;
            font-weight: 800;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Bebas Neue', serif;
            letter-spacing: 0.15em;
            font-size: 18px;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 245, 212, 0.4);
        }

        .login-btn:hover::before {
            opacity: 1;
        }

        .login-btn:active {
            transform: translateY(0);
        }

        /* Label */
        .form-label {
            color: #8888aa;
        }
    </style>
</head>

<body>
    <div class="login-bg"></div>
    <div class="grid-lines"></div>
    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>

    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo">GF</div>
            <div class="login-title">GymFlow</div>
            <div class="login-subtitle">Gestión de Clases Premium</div>
        </div>

        <!-- ── Error banner ─────────────────────────────────────── -->
        <div class="error-toast" id="login-error" style="display:none">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span id="login-error-msg">Error</span>
        </div>

        <!-- ── Step 1: email + password ──────────────────────────── -->
        <form id="login-form" style="display:flex;flex-direction:column;gap:0">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" id="f-email" class="form-control" placeholder="tu@gimnasio.com" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <input type="password" id="f-password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" id="login-btn" class="login-btn" style="margin-top:8px">INGRESAR</button>
        </form>

        <!-- ── Step 2: OTP code ───────────────────────────────────── -->
        <div id="otp-step" style="display:none;flex-direction:column;gap:0;text-align:center">
            <div style="font-size:40px;margin-bottom:8px">🔐</div>
            <div style="font-size:15px;font-weight:600;margin-bottom:4px">Verificación en dos pasos</div>
            <div style="font-size:13px;color:#8888aa;margin-bottom:24px">Ingresá el código de 6 dígitos de tu app
                autenticadora</div>
            <div style="position:relative;margin-bottom:16px">
                <input type="text" id="otp-code" class="form-control" maxlength="6" inputmode="numeric" pattern="[0-9]*"
                    placeholder="000000"
                    style="text-align:center;font-size:28px;font-weight:700;letter-spacing:.35em;padding:14px">
            </div>
            <button id="otp-btn" class="login-btn">VERIFICAR</button>
            <button onclick="cancelOtp()"
                style="margin-top:12px;background:none;border:none;color:#8888aa;font-size:13px;cursor:pointer;font-family:inherit">←
                Volver al login</button>
        </div>
    </div>

    <script>
        const BASE = '<?php echo BASE_URL ?>';
        let _otpToken = null;

        function showError(msg) {
            document.getElementById('login-error-msg').textContent = msg;
            document.getElementById('login-error').style.display = 'flex';
        }
        function hideError() { document.getElementById('login-error').style.display = 'none'; }

        // Redirect after successful login
        function redirect(role) {
            const map = { superadmin: '/pages/superadmin/dashboard.php', admin: '/pages/admin/dashboard.php' };
            location.href = BASE + (map[role] || '/pages/instructor/dashboard.php');
        }

        // ── Step 1: email + password ─────────────────────────────────────────────
        document.getElementById('login-form').addEventListener('submit', async e => {
            e.preventDefault();
            hideError();
            const btn = document.getElementById('login-btn');
            btn.disabled = true; btn.textContent = 'VERIFICANDO…';

            try {
                const res = await fetch(BASE + '/api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        email: document.getElementById('f-email').value,
                        password: document.getElementById('f-password').value,
                    }),
                });
                const data = await res.json();

                if (!res.ok) {
                    showError(data.error === 'gym_inactive'
                        ? 'Tu cuenta está suspendida. Contactá al administrador.'
                        : 'Email o contraseña incorrectos');
                    return;
                }

                if (data.otp_required) {
                    // Show OTP step
                    _otpToken = data.otp_token;
                    document.getElementById('login-form').style.display = 'none';
                    const otpStep = document.getElementById('otp-step');
                    otpStep.style.display = 'flex';
                    document.getElementById('otp-code').focus();
                } else {
                    redirect(data.role);
                }
            } catch { showError('Error de conexión'); }
            finally { btn.disabled = false; btn.textContent = 'INGRESAR'; }
        });

        // ── Step 2: OTP code ─────────────────────────────────────────────────────
        async function submitOtp() {
            hideError();
            const code = document.getElementById('otp-code').value.trim();
            if (code.length !== 6) return;
            const btn = document.getElementById('otp-btn');
            btn.disabled = true; btn.textContent = 'VERIFICANDO…';

            try {
                const res = await fetch(BASE + '/api/auth.php?action=otp_verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ otp_token: _otpToken, code }),
                });
                const data = await res.json();
                if (!res.ok) { showError(data.error || 'Código incorrecto'); return; }
                redirect(data.role);
            } catch { showError('Error de conexión'); }
            finally { btn.disabled = false; btn.textContent = 'VERIFICAR'; }
        }

        document.getElementById('otp-btn').addEventListener('click', submitOtp);

        // Auto-submit when 6 digits typed
        document.getElementById('otp-code').addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
            if (this.value.length === 6) submitOtp();
        });

        // Also submit on Enter
        document.getElementById('otp-code').addEventListener('keydown', e => {
            if (e.key === 'Enter') submitOtp();
        });

        function cancelOtp() {
            _otpToken = null;
            document.getElementById('otp-step').style.display = 'none';
            document.getElementById('login-form').style.display = 'flex';
            document.getElementById('otp-code').value = '';
            hideError();
        }
    </script>
</body>

</html>