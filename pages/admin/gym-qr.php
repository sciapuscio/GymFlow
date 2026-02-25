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
$gymId = (int) $user['gym_id'];

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

// Build check-in URL — the URL that gets encoded in the QR
$checkinUrl = rtrim(BASE_URL, '/') . '/api/checkin.php?gym_qr_token=' . urlencode($gym['qr_token']);

// ── Nav ──────────────────────────────────────────────────────────────────────
layout_header('QR del Gym — ' . $gym['name'], 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'admin', 'admin');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Instructor', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>', 'instructor', 'admin');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'members', 'admin');
nav_item(BASE_URL . '/pages/admin/membership-plans.php', 'Planes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'plans', 'admin');
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
        <form method="POST" style="display:inline"
            onsubmit="return confirm('¿Regenerar el QR? Los códigos usados antes dejarán de funcionar.')">
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
        <div class="card" style="text-align:center">
            <div
                style="font-size:13px;font-weight:700;color:var(--gf-text-muted);letter-spacing:.08em;text-transform:uppercase;margin-bottom:16px">
                QR del Gym
            </div>

            <!-- QR image via Google Charts API (no JS library needed) -->
            <div id="qr-container"
                style="display:inline-block;padding:16px;background:#fff;border-radius:12px;margin-bottom:16px">
                <img id="qr-img"
                    src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=<?php echo urlencode($checkinUrl) ?>"
                    alt="QR Check-in <?php echo htmlspecialchars($gym['name']) ?>" width="240" height="240"
                    style="display:block">
            </div>

            <div
                style="font-family:var(--font-display);font-size:22px;color:var(--gf-accent);letter-spacing:.1em;margin-bottom:4px">
                <?php echo htmlspecialchars($gym['name']) ?>
            </div>
            <div style="font-size:12px;color:var(--gf-text-dim)">Escaneá para registrar tu presencia</div>

            <hr style="margin:16px 0">

            <div
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

            <div class="card" style="border-color:rgba(245,158,11,.25);background:rgba(245,158,11,.05)">
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

        .sidebar,
        .page-header,
        .card:not(#print-only) {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
        }

        .page-body {
            padding: 0 !important;
        }

        body {
            background: #fff !important;
            color: #000 !important;
        }

        #qr-container {
            border: 3px solid #000 !important;
        }

        .card {
            background: #fff !important;
            border: 1px solid #ddd !important;
        }

        /* Show only the QR card when printing */
        .page-body>div {
            grid-template-columns: 1fr !important;
        }

        .page-body>div>div:last-child {
            display: none !important;
        }
    }
</style>