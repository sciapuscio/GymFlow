<?php
/**
 * GymFlow — One-click Database Setup
 * Visit http://localhost/Training/setup.php to install
 * 
 * DELETE THIS FILE after installation for security!
 */

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';

$step = $_POST['step'] ?? 'check';
$messages = [];
$success = false;

function tryConnect($host, $user, $pass, $db = null)
{
    $dsn = "mysql:host=$host;charset=utf8mb4" . ($db ? ";dbname=$db" : '');
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'install') {
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    $dbHost = $_POST['db_host'] ?? 'localhost';

    try {
        $pdo = tryConnect($dbHost, $dbUser, $dbPass);
        $messages[] = ['ok', 'Conexión a MySQL exitosa'];

        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS gymflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $messages[] = ['ok', 'Base de datos "gymflow" creada (o ya existía)'];

        $pdo->exec("USE gymflow");

        // Run schema
        $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
        // Remove CREATE DATABASE and USE lines (already done)
        $schema = preg_replace('/^CREATE DATABASE.+\n/m', '', $schema);
        $schema = preg_replace('/^USE .+\n/m', '', $schema);

        // Split by ; and execute
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        $schemaErrors = 0;
        foreach ($statements as $sql) {
            if (!trim($sql))
                continue;
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'already exists') && !str_contains($e->getMessage(), 'Duplicate')) {
                    $messages[] = ['warn', 'Schema: ' . $e->getMessage()];
                    $schemaErrors++;
                }
            }
        }
        if ($schemaErrors === 0) {
            $messages[] = ['ok', 'Schema instalado correctamente (' . count($statements) . ' statements)'];
        }

        // Run seed
        $seed = file_get_contents(__DIR__ . '/sql/seed.sql');
        $seedStatements = array_filter(array_map('trim', explode(';', $seed)));
        $seedErrors = 0;
        foreach ($seedStatements as $sql) {
            if (!trim($sql))
                continue;
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate entry')) {
                    $messages[] = ['warn', 'Seed: ' . $e->getMessage()];
                    $seedErrors++;
                }
            }
        }
        if ($seedErrors === 0) {
            $messages[] = ['ok', 'Datos de ejemplo insertados correctamente'];
        }

        // Verify
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $messages[] = ['ok', "Verificación: $count usuarios en la base de datos"];

        // Update config with correct credentials
        $configPath = __DIR__ . '/config/app.php';
        $config = file_get_contents($configPath);
        $config = str_replace("define('DB_HOST', 'localhost')", "define('DB_HOST', '$dbHost')", $config);
        $config = str_replace("define('DB_USER', 'root')", "define('DB_USER', '$dbUser')", $config);
        $config = str_replace("define('DB_PASS', '')", "define('DB_PASS', '$dbPass')", $config);
        file_put_contents($configPath, $config);
        $messages[] = ['ok', 'Configuración actualizada en config/app.php'];

        $success = true;

    } catch (PDOException $e) {
        $messages[] = ['error', 'Error de conexión: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>GymFlow — Setup</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #080810;
            color: #e0e0ff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: rgba(15, 15, 26, .85);
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 20px;
            padding: 40px;
            width: min(480px, 95vw);
        }

        h1 {
            font-family: 'Bebas Neue', serif;
            font-size: 36px;
            letter-spacing: .1em;
            background: linear-gradient(135deg, #00f5d4, #ff6b35);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 4px;
        }

        p.sub {
            color: #8888aa;
            font-size: 13px;
            margin-bottom: 28px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #8888aa;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .10);
            border-radius: 8px;
            padding: 10px 14px;
            color: #e0e0ff;
            font-size: 14px;
            margin-bottom: 16px;
            outline: none;
            transition: border-color .2s;
        }

        input:focus {
            border-color: rgba(0, 245, 212, .4);
        }

        button {
            width: 100%;
            background: linear-gradient(135deg, #00f5d4, #00c9b1);
            color: #080810;
            font-family: 'Bebas Neue', serif;
            font-size: 20px;
            letter-spacing: .1em;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 245, 212, .4);
        }

        .msg {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 8px;
            display: flex;
            align-items: start;
            gap: 8px;
        }

        .msg.ok {
            background: rgba(16, 185, 129, .1);
            border: 1px solid rgba(16, 185, 129, .3);
            color: #10b981;
        }

        .msg.warn {
            background: rgba(245, 158, 11, .1);
            border: 1px solid rgba(245, 158, 11, .3);
            color: #f59e0b;
        }

        .msg.error {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
            color: #ef4444;
        }

        .btn-go {
            display: block;
            text-align: center;
            background: var(--gf-accent, #00f5d4);
            color: #080810;
            font-weight: 700;
            padding: 14px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 16px;
            margin-top: 20px;
        }

        .security-note {
            background: rgba(239, 68, 68, .07);
            border: 1px solid rgba(239, 68, 68, .2);
            border-radius: 8px;
            padding: 12px;
            font-size: 12px;
            color: #ef4444;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>GymFlow Setup</h1>
        <p class="sub">Instalación de base de datos. Ejecutá esto una sola vez.</p>

        <?php if (!empty($messages)): ?>
            <div style="margin-bottom:20px">
                <?php foreach ($messages as [$type, $msg]): ?>
                    <div class="msg <?php echo $type ?>">
                        <span>
                            <?php echo $type === 'ok' ? '✓' : ($type === 'warn' ? '⚠' : '✗') ?>
                        </span>
                        <span>
                            <?php echo htmlspecialchars($msg) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="msg ok">✓ <strong>¡Instalación completada con éxito!</strong></div>
            <a href="./" class="btn-go">🚀 Ir a GymFlow</a>
            <div class="security-note">⚠️ <strong>IMPORTANTE:</strong> Eliminá el archivo <code>setup.php</code> por
                seguridad antes de publicar en producción.</div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="step" value="install">
                <div>
                    <label>Host MySQL</label>
                    <input name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
                        placeholder="localhost">
                </div>
                <div>
                    <label>Usuario MySQL</label>
                    <input name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root') ?>" placeholder="root">
                </div>
                <div>
                    <label>Contraseña MySQL</label>
                    <input type="password" name="db_pass" value="" placeholder="(vacío para XAMPP por defecto)">
                </div>
                <button type="submit">INSTALAR BASE DE DATOS</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>