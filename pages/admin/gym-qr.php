<?php
/**
 * GymFlow — Página de impresión del QR del gym
 * Solo admin / superadmin
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin', 'staff');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];

// Fetch gym data (including qr_token)
$gym = db()->prepare("SELECT * FROM gyms WHERE id = ?");
$gym->execute([$gymId]);
$gym = $gym->fetch();

// Regenerate QR token if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate') {
    $newToken = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
    db()->prepare("UPDATE gyms SET qr_token = ? WHERE id = ?")->execute([$newToken, $gymId]);
    header('Location: ' . BASE_URL . '/pages/admin/gym-qr.php?regenerated=1');
    exit;
}

// Auto-generate qr_token if gym doesn't have one yet (e.g. created before migration)
if (empty($gym['qr_token'])) {
    $newToken = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
    db()->prepare("UPDATE gyms SET qr_token = ? WHERE id = ?")->execute([$newToken, $gymId]);
    $gym['qr_token'] = $newToken;
}

// Build check-in URL — the URL that gets encoded in the QR
$checkinUrl = rtrim(BASE_URL, '/') . '/api/checkin.php?gym_qr_token=' . urlencode($gym['qr_token']);

// ── Nav ──────────────────────────────────────────────────────────────────────
layout_header('QR del Gym — ' . $gym['name'], 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'gym-qr');
if ($user['role'] === 'staff')
    nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Agenda', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'gym-qr');
else
    nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Instructor', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>', 'instructor', 'gym-qr');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'members', 'gym-qr');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'plans', 'gym-qr');
nav_item(BASE_URL . '/pages/admin/gym-qr.php', 'QR Check-in', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>', 'gym-qr', 'gym-qr');
layout_footer($user);
?>

<div class="page-header">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
    </svg>
    <h1 style="font-size:18px;font-weight:700">QR Check-in</h1>
    <div style="margin-left:auto;display:flex;gap:10px">
        <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Imprimir</button>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="regenerate">
            <button type="submit" class="btn btn-danger btn-sm">🔄 Regenerar QR</button>
        </form>
    </div>
</div>

<?php if (isset($_GET['regenerated'])): ?>
    <div style="padding:12px 28px">
        <div class="card"
            style="background:rgba(16,185,129,0.08);border-color:rgba(16,185,129,0.3);padding:12px 16px;font-size:13px;color:#10b981">
            ✅ QR regenerado correctamente. Imprimí el nuevo código.
        </div>
    </div>
<?php endif; ?>

<div class="page-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

        <!-- QR Preview card -->
        <div class="card" id="print-only" style="text-align:center">
            <div
                style="font-size:13px;font-weight:700;color:var(--gf-text-muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:16px">
                QR del Gym
            </div>

            <!-- QR image via Google Charts API (no JS library needed) -->
            <div id="qr-container"
                style="display:inline-block;padding:16px;background:#fff;border-radius:12px;margin-bottom:16px;position:relative">
                <img id="qr-img"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&ecc=H&data=<?php echo urlencode($checkinUrl) ?>"
                    alt="QR Check-in <?php echo htmlspecialchars($gym['name']) ?>" width="240" height="240"
                    style="display:block">
                <?php if (!empty($gym['logo_path'])): ?>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
                    width:56px;height:56px;background:#fff;border-radius:50%;padding:4px;
                    display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 2px #eee">
                        <img src="<?php echo BASE_URL . $gym['logo_path'] ?>" alt="Logo"
                            style="width:44px;height:44px;object-fit:contain;border-radius:50%">
                    </div>
                <?php endif ?>
            </div>

            <div
                style="font-family:var(--font-display);font-size:22px;color:var(--gf-accent);letter-spacing:.1em;margin-bottom:4px">
                <?php echo htmlspecialchars($gym['name']) ?>
            </div>
            <div style="font-size:12px;color:var(--gf-text-dim)">Escaneá para registrar tu presencia</div>

            <hr class="no-print" style="margin:16px 0">

            <div class="no-print"
                style="font-size:11px;color:var(--gf-text-dim);word-break:break-all;background:var(--gf-surface-2);padding:8px 12px;border-radius:6px;text-align:left">
                <strong style="color:var(--gf-text-muted)">URL:</strong><br>
                <?php echo htmlspecialchars($checkinUrl) ?>
            </div>
        </div>

        <!-- Info & instructions -->
        <div style="display:flex;flex-direction:column;gap:16px">

            <div class="card">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">¿Cómo funciona?</h3>
                <ol style="font-size:13px;color:var(--gf-text-muted);line-height:1.9;padding-left:20px">
                    <li>Imprimís este QR y lo pegás en la entrada del gym.</li>
                    <li>El alumno abre la app GymFlow en su celular.</li>
                    <li>Toca "Registrar presencia" y escanea el QR.</li>
                    <li>El sistema valida su membresía y descuenta 1 clase automáticamente.</li>
                    <li>Ve un mensaje de confirmación en su pantalla 💪</li>
                </ol>
            </div>

            <div class="card">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">Configuración de check-in</h3>
                <form method="POST" action="<?php echo BASE_URL ?>/api/gyms.php?action=update_checkin">
                    <input type="hidden" name="gym_id" value="<?php echo $gymId ?>">
                    <div style="display:grid;gap:12px">
                        <div>
                            <label class="form-label">Ventana de check-in (minutos antes de la clase)</label>
                            <input type="number" name="checkin_window_minutes" class="input"
                                value="<?php echo (int) ($gym['checkin_window_minutes'] ?? 30) ?>" min="5" max="120">
                        </div>
                        <div>
                            <label class="form-label">Límite de cancelación sin ausencia (minutos)</label>
                            <input type="number" name="cancel_cutoff_minutes" class="input"
                                value="<?php echo (int) ($gym['cancel_cutoff_minutes'] ?? 120) ?>" min="0" max="1440">
                        </div>
                    </div>
                    <div style="margin-top:14px">
                        <button type="submit" class="btn btn-primary btn-sm">Guardar configuración</button>
                    </div>
                </form>
            </div>

            <div class="card" style="border-color:rgba(0,245,212,.2);background:rgba(0,245,212,.04)">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">📱 Código del Gym (App)</h3>
                <p style="font-size:12px;color:var(--gf-text-muted);margin-bottom:10px;line-height:1.5">
                    Este es el código que los alumnos usan para registrarse e iniciar sesión en la app GymFlow.
                    Formato: <code
                        style="background:var(--gf-surface-2);padding:1px 6px;border-radius:4px">email@codigo</code>
                </p>
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="flex:1;background:var(--gf-surface-2);border:1px solid var(--gf-border);border-radius:8px;padding:12px 16px;font-family:monospace;font-size:18px;font-weight:700;color:var(--gf-accent);letter-spacing:.05em"
                        id="gym-slug-display">
                        <?php echo htmlspecialchars($gym['slug']) ?>
                    </div>
                    <button
                        onclick="navigator.clipboard.writeText('<?php echo addslashes($gym['slug']) ?>');this.textContent='✅ Copiado!';setTimeout(()=>this.textContent='📋 Copiar',2000)"
                        class="btn btn-sm"
                        style="white-space:nowrap;background:rgba(0,245,212,.12);color:var(--gf-accent);border:1px solid rgba(0,245,212,.3)">
                        📋 Copiar
                    </button>
                </div>
            </div>

            <div class="card">
                <div style="font-size:13px;font-weight:700;color:#f59e0b;margin-bottom:8px">⚠️ Sobre la regeneración
                </div>
                <p style="font-size:12px;color:var(--gf-text-muted);line-height:1.6">
                    Si regenerás el QR, el código impreso anterior deja de funcionar.
                    Recordá imprimir el nuevo y reemplazarlo. Solo hacelo si creés que
                    alguien está usando el QR sin autorización.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Print styles -->
<style>
    @media print {

        /* Hide everything except the QR card */
        .sidebar,
        .page-header,
        .card:not(#print-only),
        .no-print {
            display: none !important;
        }

        /* Reset layout for print */
        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            color: #000 !important;
            width: 100% !important;
        }

        .app-layout {
            display: block !important;
        }

        .sidebar {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .page-body {
            padding: 0 !important;
            display: block !important;
            width: 100% !important;
        }

        /* The grid wrapper — collapse to block so QR takes full width */
        .page-body>div {
            display: block !important;
            width: 100% !important;
        }

        /* QR card: centered, full page */
        #print-only {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100% !important;
            min-height: 92vh !important;
            border: none !important;
            background: #fff !important;
            box-shadow: none !important;
            padding: 40px 0 !important;
        }

        /* Scale QR image up for print */
        #qr-img {
            width: 320px !important;
            height: 320px !important;
        }

        #qr-container {
            border: 3px solid #000 !important;
            background: #fff !important;
            padding: 16px !important;
            border-radius: 8px !important;
        }

        /* Gym name */
        #print-only div[style*="font-family"] {
            color: #111 !important;
            font-size: 32px !important;
            margin-top: 12px !important;
        }

        /* Legend */
        #print-only div[style*="font-size:12px"] {
            color: #444 !important;
            font-size: 14px !important;
        }

        /* URL box */
        #print-only div[style*="word-break"] {
            background: #f5f5f5 !important;
            color: #333 !important;
            border: 1px solid #ccc !important;
            max-width: 360px !important;
            margin-top: 8px !important;
        }

        hr {
            border-color: #ddd !important;
            width: 80% !important;
        }
    }
</style>