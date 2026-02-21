<?php
// Shared layout header include
// Usage: include layout_header('Page Title', 'nav-key');
function layout_header(string $title, string $activeNav = '', ?array $user = null): void
{
    $base = defined('BASE_URL') ? BASE_URL : (str_starts_with(strtolower($_SERVER['HTTP_HOST'] ?? 'localhost'), 'localhost') || str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.') ? '/Training' : '');
    ?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>
            <?php echo htmlspecialchars($title) ?> — GymFlow
        </title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link
            href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700;800&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="<?php echo $base ?>/assets/css/design-system.css">
        <?php if (strpos($activeNav, 'builder') !== false || $activeNav === 'live'): ?>
            <link rel="stylesheet" href="<?php echo $base ?>/assets/css/builder.css">
        <?php endif; ?>
        <?php if (isset($user) && in_array($user['role'] ?? '', ['admin', 'instructor'])): ?>
            <link rel="stylesheet" href="<?php echo $base ?>/assets/css/tour.css">
        <?php endif; ?>
        <style>
            /* Dynamic branding from gym */
            :root {
                --gf-accent:
                    <?php echo htmlspecialchars($user['primary_color'] ?? '#00f5d4') ?>
                ;
                --gf-accent-2:
                    <?php echo htmlspecialchars($user['secondary_color'] ?? '#ff6b35') ?>
                ;
                --gf-accent-dim:
                    <?php echo htmlspecialchars($user['primary_color'] ?? '#00f5d4') ?>
                    26;
            }
        </style>
    </head>

    <body>
        <div class="app-layout">

            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-brand">
                    <?php if (!empty($user['logo_path'])): ?>
                        <img src="<?php echo $base . $user['logo_path'] ?>" alt="Logo" class="sidebar-logo">
                    <?php else: ?>
                        <div
                            style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,var(--gf-accent),var(--gf-accent-2));display:flex;align-items:center;justify-content:center;font-family:var(--font-display);color:#080810;font-size:18px;flex-shrink:0">
                            GF</div>
                    <?php endif; ?>
                    <span class="sidebar-brand-name">GymFlow</span>
                </div>

                <nav class="sidebar-nav">
                    <?php
}

function nav_section(string $label): void
{
    echo '<div class="nav-section-label">' . htmlspecialchars($label) . '</div>';
}

function nav_item(string $href, string $label, string $icon, string $key, string $activeNav): void
{
    $active = $key === $activeNav ? ' active' : '';
    echo "<a href=\"$href\" class=\"nav-item$active\">$icon <span>$label</span></a>";
}

function layout_footer(array $user): void
{
    $initials = strtoupper(substr($user['name'], 0, 1) . (strpos($user['name'], ' ') !== false ? substr($user['name'], strpos($user['name'], ' ') + 1, 1) : ''));
    $roleLabels = ['superadmin' => 'Super Admin', 'admin' => 'Gym Admin', 'instructor' => 'Instructor'];
    ?>
                </nav>
                <div class="sidebar-footer">
                    <div class="user-pill">
                        <div class="user-avatar">
                            <?php echo htmlspecialchars($initials) ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name">
                                <?php echo htmlspecialchars($user['name']) ?>
                            </div>
                            <div class="user-role">
                                <?php echo $roleLabels[$user['role']] ?? $user['role'] ?>
                            </div>
                        </div>
                        <a href="<?php echo defined('BASE_URL') ? BASE_URL : '' ?>/api/auth.php?action=logout"
                            id="logout-btn" title="Cerrar sesión"
                            style="color:var(--gf-text-dim);display:flex;align-items:center;">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </a>
                    </div>
                </div>
            </aside>

            <!-- Main -->
            <div class="main-content">

                <!-- Toast container -->
                <div class="toast-container" id="toasts"></div>
                <script>
                    window.GF_USER = <?php echo json_encode(['id' => $user['id'], 'role' => $user['role'], 'gym_id' => $user['gym_id'] ?? null, 'name' => $user['name']]) ?>;
                    window.GF_BASE = '<?php echo defined('BASE_URL') ? BASE_URL : '' ?>';

                    function showToast(msg, type = 'info') {
                        const t = document.createElement('div');
                        t.className = `toast ${type}`;
                        t.innerHTML = `<span>${msg}</span>`;
                        document.getElementById('toasts').appendChild(t);
                        setTimeout(() => t.remove(), 4000);
                    }

                    document.getElementById('logout-btn')?.addEventListener('click', async e => {
                        e.preventDefault();
                        await fetch(window.GF_BASE + '/api/auth.php?action=logout', { method: 'POST', credentials: 'include' });
                        location.href = window.GF_BASE + '/';
                    });
                </script>
                <?php
                // ── Onboarding tour (admin + instructor only) ──────────────────────
                $tourRole = $user['role'] ?? '';
                if (in_array($tourRole, ['admin', 'instructor'])) {
                    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                    $tourPage = null;
                    $tourNeeded = empty($user['last_login']); // NULL = new user
                    if (str_contains($scriptName, 'admin/dashboard'))
                        $tourPage = 'admin_dashboard';
                    elseif (str_contains($scriptName, 'instructor/dashboard'))
                        $tourPage = 'instructor_dashboard';
                    elseif (str_contains($scriptName, 'instructor/builder'))
                        $tourPage = 'builder';
                    if ($tourPage):
                        ?>
                        <script>
                            window.GF_TOUR_PAGE = '<?php echo $tourPage; ?>';
                            window.GF_TOUR_NEEDED = <?php echo $tourNeeded ? 'true' : 'false'; ?>;
                        </script>
                        <script src="<?php echo defined('BASE_URL') ? BASE_URL : '' ?>/assets/js/tour.js"></script>
                        <?php
                    endif;
                }
    // ── end tour ───────────────────────────────────────────────────────
}

function layout_end(): void
{
    echo '</div></div></body></html>';
}
?>