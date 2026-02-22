<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';

$user = requireAuth();

// Role-based back link
$dashMap = [
    'superadmin' => BASE_URL . '/pages/superadmin/dashboard.php',
    'admin' => BASE_URL . '/pages/admin/dashboard.php',
    'instructor' => BASE_URL . '/pages/instructor/dashboard.php',
];
$dashUrl = $dashMap[$user['role']] ?? BASE_URL . '/';

layout_header('Mi Perfil', 'profile', $user);
// Nav items based on role
if ($user['role'] === 'superadmin') {
    nav_section('Super Admin');
    nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'profile');
    nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>', 'gyms', 'profile');
} elseif ($user['role'] === 'admin') {
    nav_section('Administración');
    nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'admin', 'profile');
} else {
    nav_section('Instructor');
    nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'instructor', 'profile');
}
layout_footer($user);
?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="<?php echo $dashUrl ?>" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
        </a>
        <h1 style="font-size:20px;font-weight:700">Mi Perfil</h1>
    </div>
</div>

<div class="page-body">
    <div style="max-width:640px;margin:0 auto;display:flex;flex-direction:column;gap:18px">

        <!-- Avatar & info card -->
        <div class="card" style="display:flex;align-items:center;gap:20px">
            <div
                style="width:64px;height:64px;border-radius:16px;background:linear-gradient(135deg,var(--gf-accent),var(--gf-accent-2));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);color:#080810;font-size:26px;font-weight:700;flex-shrink:0">
                <?php
                $n = $user['name'];
                $parts = explode(' ', $n);
                echo htmlspecialchars(strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : '')));
                ?>
            </div>
            <div>
                <div style="font-size:18px;font-weight:700">
                    <?php echo htmlspecialchars($user['name']) ?>
                </div>
                <div style="font-size:13px;color:var(--gf-text-muted);margin-top:2px">
                    <?php echo htmlspecialchars($user['email']) ?>
                </div>
                <div style="margin-top:6px;display:flex;gap:8px;align-items:center">
                    <span class="badge badge-success" style="font-size:11px">
                        <?php
                        $rl = ['superadmin' => 'Super Admin', 'admin' => 'Gym Admin', 'instructor' => 'Instructor'];
                        echo $rl[$user['role']] ?? $user['role'];
                        ?>
                    </span>
                    <?php if (!empty($user['gym_name'])): ?>
                        <span style="font-size:12px;color:var(--gf-text-muted)">
                            <?php echo htmlspecialchars($user['gym_name']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit form -->
        <div class="card">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:20px">Datos de la cuenta</h2>
            <form id="profile-form" style="display:flex;flex-direction:column;gap:16px">
                <div>
                    <label class="form-label">Nombre completo</label>
                    <input type="text" id="pf-name" class="form-input"
                        value="<?php echo htmlspecialchars($user['name']) ?>" autocomplete="name">
                    <div class="field-error" id="err-name"></div>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" id="pf-email" class="form-input"
                        value="<?php echo htmlspecialchars($user['email']) ?>" autocomplete="email">
                    <div class="field-error" id="err-email"></div>
                </div>
                <button type="submit" class="btn btn-primary" style="align-self:flex-start">
                    Guardar cambios
                </button>
            </form>
        </div>

        <!-- Change password -->
        <div class="card">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:20px">Cambiar contraseña</h2>
            <form id="pw-form" style="display:flex;flex-direction:column;gap:16px">
                <div>
                    <label class="form-label">Contraseña actual</label>
                    <input type="password" id="pw-current" class="form-input" autocomplete="current-password">
                    <div class="field-error" id="err-current_password"></div>
                </div>
                <div>
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" id="pw-new" class="form-input" autocomplete="new-password">
                    <div class="field-error" id="err-new_password"></div>
                </div>
                <div>
                    <label class="form-label">Confirmar nueva contraseña</label>
                    <input type="password" id="pw-confirm" class="form-input" autocomplete="new-password">
                    <div class="field-error" id="err-confirm_password"></div>
                </div>
                <button type="submit" class="btn btn-primary"
                    style="align-self:flex-start;background:#ff6b35;border-color:#ff6b35">
                    Cambiar contraseña
                </button>
            </form>
        </div>

    </div>
</div>

<style>
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--gf-text-muted);
        margin-bottom: 6px;
    }

    .form-input {
        width: 100%;
        background: var(--gf-surface-2);
        border: 1px solid var(--gf-border);
        border-radius: 10px;
        color: inherit;
        padding: 10px 14px;
        font-size: 14px;
        font-family: inherit;
        transition: border-color .15s;
        box-sizing: border-box;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--gf-accent);
    }

    .form-input.error {
        border-color: #ef4444;
    }

    .field-error {
        font-size: 12px;
        color: #f87171;
        margin-top: 4px;
        min-height: 16px;
    }
</style>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    function clearErrors() {
        document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
        document.querySelectorAll('.form-input').forEach(el => el.classList.remove('error'));
    }

    function showErrors(fields) {
        Object.entries(fields).forEach(([k, msg]) => {
            const el = document.getElementById('err-' + k);
            const input = document.getElementById('pf-' + k) || document.getElementById('pw-' + k.replace('current_password', 'current').replace('new_password', 'new').replace('confirm_password', 'confirm'));
            if (el) el.textContent = msg;
            if (input) input.classList.add('error');
        });
    }

    // ── Profile form ──────────────────────────────────────────────────────────────
    document.getElementById('profile-form').addEventListener('submit', async e => {
        e.preventDefault();
        clearErrors();
        const btn = e.submitter;
        btn.disabled = true; btn.textContent = 'Guardando…';

        const res = await GF.put(`${window.GF_BASE}/api/profile.php`, {
            name: document.getElementById('pf-name').value,
            email: document.getElementById('pf-email').value,
        });

        btn.disabled = false; btn.textContent = 'Guardar cambios';

        if (res?.ok) {
            showToast('Perfil actualizado ✓', 'success');
            // Update sidebar display name live
            const nameEl = document.querySelector('.user-name');
            if (nameEl) nameEl.textContent = document.getElementById('pf-name').value;
        } else if (res?.fields) {
            showErrors(res.fields);
        } else {
            showToast(res?.error || 'Error al guardar', 'error');
        }
    });

    // ── Password form ─────────────────────────────────────────────────────────────
    document.getElementById('pw-form').addEventListener('submit', async e => {
        e.preventDefault();
        clearErrors();
        const btn = e.submitter;
        btn.disabled = true; btn.textContent = 'Cambiando…';

        const res = await GF.put(`${window.GF_BASE}/api/profile.php`, {
            current_password: document.getElementById('pw-current').value,
            new_password: document.getElementById('pw-new').value,
            confirm_password: document.getElementById('pw-confirm').value,
        });

        btn.disabled = false; btn.textContent = 'Cambiar contraseña';

        if (res?.ok) {
            showToast('Contraseña actualizada ✓', 'success');
            document.getElementById('pw-form').reset();
        } else if (res?.fields) {
            showErrors(res.fields);
        } else {
            showToast(res?.error || 'Error al cambiar la contraseña', 'error');
        }
    });
</script>
<?php layout_end(); ?>