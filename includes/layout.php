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
                        <a href="<?php echo defined('BASE_URL') ? BASE_URL : '' ?>/pages/profile.php" title="Mi perfil"
                            style="color:var(--gf-text-dim);display:flex;align-items:center;margin-right:2px">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </a>
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

                <?php
                // ── System Notice Banner ───────────────────────────────────
                try {
                    $notice = db()->query(
                        "SELECT id, message, type FROM system_notices WHERE active = 1 ORDER BY id DESC LIMIT 1"
                    )->fetch();
                } catch (\Throwable $e) {
                    $notice = null; // table may not exist yet
                }
                if ($notice):
                    $noticeColors = [
                        'warning' => ['bg' => 'rgba(245,158,11,0.08)', 'border' => 'rgba(245,158,11,0.25)', 'icon' => '⚠️'],
                        'error' => ['bg' => 'rgba(239,68,68,0.08)', 'border' => 'rgba(239,68,68,0.25)', 'icon' => '🚨'],
                        'info' => ['bg' => 'rgba(59,130,246,0.08)', 'border' => 'rgba(59,130,246,0.25)', 'icon' => 'ℹ️'],
                    ];
                    $nc = $noticeColors[$notice['type']] ?? $noticeColors['info'];
                    ?>
                    <div id="system-notice-banner"
                        style="margin:0 0 18px 0;padding:14px 18px;border-radius:12px;background:<?php echo $nc['bg'] ?>;border:1px solid <?php echo $nc['border'] ?>;display:flex;align-items:center;gap:14px;font-size:14px;line-height:1.5">
                        <span style="font-size:20px;flex-shrink:0"><?php echo $nc['icon'] ?></span>
                        <div style="flex:1"><?php echo htmlspecialchars($notice['message']) ?></div>
                    </div>
                <?php endif; ?>
                <script>
                    window.GF_USER = <?php echo json_encode(['id' => $user['id'], 'role' => $user['role'], 'gym_id' => $user['gym_id'] ?? null, 'name' => $user['name']]) ?>;
                    window.GF_BASE = '<?php echo defined('BASE_URL') ? BASE_URL : '' ?>';
                    window.GF_SOCKET_URL = '<?php echo defined('SOCKET_URL') ? SOCKET_URL : 'http://localhost:3000' ?>';

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
                ?>

                <?php if (in_array($user['role'] ?? '', ['superadmin', 'admin', 'instructor'])): ?>
                    <style>
                        #gf-broadcast-overlay {
                            display: none;
                            position: fixed;
                            inset: 0;
                            z-index: 9999;
                            background: rgba(8, 8, 16, 0.82);
                            backdrop-filter: blur(6px);
                            align-items: center;
                            justify-content: center;
                        }

                        #gf-broadcast-overlay.active {
                            display: flex;
                        }

                        #gf-broadcast-box {
                            background: var(--gf-surface, #14141e);
                            border: 1px solid rgba(255, 255, 255, 0.12);
                            border-radius: 20px;
                            padding: 36px 40px;
                            max-width: 520px;
                            width: 90%;
                            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.6);
                            text-align: center;
                            animation: gfBroadcastIn .25s cubic-bezier(.34, 1.56, .64, 1);
                        }

                        @keyframes gfBroadcastIn {
                            from {
                                transform: scale(.88);
                                opacity: 0;
                            }

                            to {
                                transform: scale(1);
                                opacity: 1;
                            }
                        }

                        #gf-broadcast-icon {
                            font-size: 40px;
                            margin-bottom: 16px;
                        }

                        #gf-broadcast-title {
                            font-size: 13px;
                            font-weight: 700;
                            letter-spacing: .12em;
                            text-transform: uppercase;
                            color: var(--gf-text-muted, #888);
                            margin-bottom: 14px;
                        }

                        #gf-broadcast-msg {
                            font-size: 17px;
                            font-weight: 500;
                            line-height: 1.5;
                            color: var(--gf-text, #f0f0f0);
                            margin-bottom: 28px;
                        }

                        #gf-broadcast-close {
                            background: var(--gf-accent, #00f5d4);
                            color: #080810;
                            border: none;
                            border-radius: 12px;
                            font-size: 14px;
                            font-weight: 700;
                            letter-spacing: .08em;
                            padding: 12px 32px;
                            cursor: pointer;
                            transition: transform .1s, box-shadow .15s;
                        }

                        #gf-broadcast-close:hover {
                            transform: scale(1.04);
                            box-shadow: 0 0 24px rgba(0, 245, 212, .4);
                        }
                    </style>
                    <div id="gf-broadcast-overlay">
                        <div id="gf-broadcast-box">
                            <div id="gf-broadcast-icon">📢</div>
                            <div id="gf-broadcast-title">Mensaje del Sistema</div>
                            <div id="gf-broadcast-msg"></div>
                            <button id="gf-broadcast-close"
                                onclick="document.getElementById('gf-broadcast-overlay').classList.remove('active')">Entendido</button>
                        </div>
                    </div>
                    <script>
                        (function () {
                            const ICONS = { info: 'ℹ️', warning: '⚠️', error: '🚨' };
                            function showBroadcast(data) {
                                document.getElementById('gf-broadcast-icon').textContent = ICONS[data.type] || '📢';
                                document.getElementById('gf-broadcast-msg').textContent = data.message || '';
                                document.getElementById('gf-broadcast-overlay').classList.add('active');
                            }
                            // Connect a dedicated system socket (no sala room)
                            function connectBroadcastSocket() {
                                if (typeof io === 'undefined') return;
                                const sock = io(window.GF_SOCKET_URL || 'http://localhost:3001',
                                    { transports: ['websocket', 'polling'] });
                                sock.on('connect', () => {
                                    sock.emit('join:system', { role: <?php echo json_encode($user['role'] ?? 'admin'); ?> });
                                });
                                sock.on('system:broadcast', showBroadcast);
                            }
                            // Wait for socket.io to be available (may be loaded late on non-live pages)
                            if (document.readyState === 'loading') {
                                document.addEventListener('DOMContentLoaded', connectBroadcastSocket);
                            } else {
                                connectBroadcastSocket();
                            }
                            // If io not yet loaded when DOM fires, retry after 2s
                            setTimeout(connectBroadcastSocket, 2000);
                        })();
                    </script>
                    <!-- socket.io client for broadcast-only pages (no-op if already loaded) -->
                    <script>
                        if (typeof io === 'undefined') {
                            var _s = document.createElement('script');
                            _s.src = (window.GF_SOCKET_URL || 'http://localhost:3000') + '/socket.io/socket.io.js';
                            _s.onerror = function () { };
                            document.head.appendChild(_s);
                        }
                    </script>
                <?php endif; ?>
            <?php } // end layout_footer()


function layout_end(): void
{
    echo '</div></div></body></html>';
}
?>