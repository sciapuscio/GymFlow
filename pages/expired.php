<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Superadmins never land here
$user = getCurrentUser();
if ($user && $user['role'] === 'superadmin') {
    header('Location: ' . BASE_URL . '/pages/superadmin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso suspendido — GymFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0a0a0f;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            text-align: center;
            max-width: 480px;
            padding: 40px 24px;
        }

        .icon {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 32px;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #f8fafc;
        }

        p {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.5);
            line-height: 1.65;
            margin-bottom: 32px;
        }

        .btn {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: background .2s;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .gym-name {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.25);
            margin-top: 32px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="icon">🔒</div>
        <h1>Suscripción vencida</h1>
        <p>
            El acceso a GymFlow para tu gimnasio está suspendido.<br>
            Contactá al administrador de tu institución para renovar el ciclo de facturación.
        </p>
        <a href="<?php echo BASE_URL ?>/" class="btn">Volver al inicio</a>
        <?php if ($user && !empty($user['gym_name'])): ?>
            <div class="gym-name">
                <?php echo htmlspecialchars($user['gym_name']) ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>