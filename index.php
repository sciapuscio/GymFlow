<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Check if already logged in
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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result && empty($result['error'])) {
        setcookie('gf_token', $result['token'], time() + SESSION_LIFETIME, '/', '', false, true);
        $dest = match ($result['role']) {
            'superadmin' => BASE_URL . '/pages/superadmin/dashboard.php',
            'admin' => BASE_URL . '/pages/admin/dashboard.php',
            default => BASE_URL . '/pages/instructor/dashboard.php',
        };
        header("Location: $dest");
        exit;
    }
    $error = ($result['error'] ?? '') === 'gym_inactive'
        ? 'Tu cuenta está suspendida. Contactá al administrador.'
        : 'Email o contraseña incorrectos';
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

        <?php if ($error): ?>
            <div class="error-toast">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <?php echo htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="tu@gimnasio.com" required autofocus
                    value="<?php echo htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="login-btn">INGRESAR</button>
        </form>


    </div>
</body>

</html>