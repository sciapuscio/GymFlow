<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');

// Load instructor profile
$stmt = db()->prepare("SELECT * FROM instructor_profiles WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$spotifyConnected = !empty($profile['spotify_access_token']);

layout_header('Mi Perfil', 'profile', $user);
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'profile');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'profile');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'profile');
nav_item(BASE_URL . '/pages/instructor/library.php', 'Biblioteca', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>', 'library', 'profile');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'profile');
nav_item(BASE_URL . '/pages/instructor/profile.php', 'Mi Perfil', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'profile', 'profile');
layout_footer($user);
?>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Mi Perfil</h1>
</div>

<div class="page-body" style="max-width:560px">

    <!-- ── Información básica ── -->
    <div class="card mb-4">
        <h2 style="font-size:15px;font-weight:700;margin-bottom:16px">Información básica</h2>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
            <div
                style="width:56px;height:56px;border-radius:50%;background:var(--gf-accent)26;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:var(--gf-accent)">
                <?php echo strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div>
                <div style="font-weight:700;font-size:16px">
                    <?php echo htmlspecialchars($user['name']) ?>
                </div>
                <div style="color:var(--gf-text-muted);font-size:13px">
                    <?php echo htmlspecialchars($user['email']) ?>
                </div>
                <span class="badge badge-accent" style="margin-top:4px">
                    <?php echo $user['role'] ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ── Spotify ── -->
    <div class="card">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="color:#1DB954">
                <path
                    d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z" />
            </svg>
            <h2 style="font-size:15px;font-weight:700;margin:0">Spotify</h2>
            <?php if ($spotifyConnected): ?>
                <span class="badge badge-work" style="margin-left:auto">Conectado</span>
            <?php else: ?>
                <span class="badge badge-muted" style="margin-left:auto">Desconectado</span>
            <?php endif; ?>
        </div>

        <?php if (!$spotifyConnected): ?>
            <!-- Step 1: Enter credentials -->
            <div id="spotify-setup">
                <p style="font-size:13px;color:var(--gf-text-muted);margin-bottom:16px">
                    Para usar Spotify en tus sesiones en vivo necesitás crear una app gratuita en
                    <a href="https://developer.spotify.com/dashboard" target="_blank"
                        style="color:var(--gf-accent)">developer.spotify.com</a>.
                    Luego ingresá tus credenciales y conectá tu cuenta Premium.
                </p>
                <div
                    style="background:var(--gf-surface-2);border-radius:10px;padding:14px;margin-bottom:16px;font-size:12px;color:var(--gf-text-muted)">
                    <strong style="color:var(--gf-text)">Redirect URI para tu app:</strong><br>
                    <code id="redirect-uri-code"
                        style="background:var(--gf-bg);padding:4px 8px;border-radius:6px;display:block;margin-top:6px;word-break:break-all">
                            <?php echo 'https://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/api/spotify.php?action=callback' ?>
                        </code>
                </div>
                <div class="form-group">
                    <label class="form-label">Client ID</label>
                    <input class="form-control" id="sp-client-id" placeholder="Tu Spotify Client ID"
                        value="<?php echo htmlspecialchars($profile['spotify_client_id'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Client Secret</label>
                    <input class="form-control" id="sp-client-secret" type="password" placeholder="Tu Spotify Client Secret"
                        value="<?php echo htmlspecialchars($profile['spotify_client_secret'] ?? '') ?>">
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-ghost" onclick="saveCredentials()" style="flex:1">Guardar credenciales</button>
                    <button class="btn btn-primary" id="connect-btn" onclick="connectSpotify()"
                        style="flex:1;background:#1DB954;border-color:#1DB954">
                        Conectar Spotify
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Already connected -->
            <div id="spotify-connected-view">
                <div
                    style="display:flex;align-items:center;gap:12px;padding:14px;background:rgba(29,185,84,.08);border:1px solid rgba(29,185,84,.25);border-radius:10px;margin-bottom:16px">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="#1DB954">
                        <path
                            d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.516 17.293a.75.75 0 01-1.032.25c-2.828-1.727-6.39-2.118-10.584-1.16a.75.75 0 01-.332-1.463c4.588-1.044 8.52-.596 11.698 1.34a.75.75 0 01.25 1.033zm1.47-3.27a.937.937 0 01-1.29.312c-3.236-1.99-8.168-2.567-11.993-1.404a.938.938 0 11-.546-1.795c4.374-1.328 9.81-.685 13.518 1.597a.937.937 0 01.31 1.29zm.126-3.402c-3.882-2.308-10.29-2.52-14.002-1.394a1.125 1.125 0 11-.656-2.154c4.26-1.295 11.343-1.046 15.822 1.613a1.125 1.125 0 11-1.164 1.935z" />
                    </svg>
                    <div>
                        <div style="font-weight:700" id="sp-account-name">Cuenta conectada</div>
                        <div style="font-size:12px;color:var(--gf-text-muted)">Spotify Premium activo</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-ghost btn-sm" onclick="updateCredentials()">Cambiar credenciales</button>
                    <button class="btn btn-danger btn-sm" onclick="disconnectSpotify()">Desconectar</button>
                </div>
                <!-- Edit creds section (hidden by default) -->
                <div id="creds-edit" style="display:none;margin-top:16px">
                    <div class="form-group">
                        <label class="form-label">Client ID</label>
                        <input class="form-control" id="sp-client-id" placeholder="Client ID"
                            value="<?php echo htmlspecialchars($profile['spotify_client_id'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Client Secret</label>
                        <input class="form-control" id="sp-client-secret" type="password" placeholder="Client Secret">
                    </div>
                    <button class="btn btn-ghost btn-sm" onclick="saveCredentials()">Guardar y reconectar</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    async function saveCredentials() {
        const cid = document.getElementById('sp-client-id')?.value.trim();
        const secret = document.getElementById('sp-client-secret')?.value.trim();
        if (!cid || !secret) { showToast('Completá ambos campos', 'error'); return; }
        await GF.post(window.GF_BASE + '/api/spotify.php?action=save-credentials', { client_id: cid, client_secret: secret });
        showToast('Credenciales guardadas', 'success');
    }

    async function connectSpotify() {
        const cid = document.getElementById('sp-client-id')?.value.trim();
        const secret = document.getElementById('sp-client-secret')?.value.trim();
        if (!cid || !secret) { showToast('Ingresá Client ID y Secret primero', 'error'); return; }
        // Save first
        await GF.post(window.GF_BASE + '/api/spotify.php?action=save-credentials', { client_id: cid, client_secret: secret });
        // Get OAuth URL
        const data = await GF.get(window.GF_BASE + '/api/spotify.php?action=auth');
        if (data.redirect) {
            const popup = window.open(data.redirect, 'spotify-oauth', 'width=500,height=700,left=200,top=100');
            window.addEventListener('message', function handler(e) {
                if (e.data?.spotify === 'connected') {
                    window.removeEventListener('message', handler);
                    showToast('¡Spotify conectado! (' + e.data.name + ')', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else if (e.data?.spotify === 'error') {
                    showToast('Error al conectar: ' + e.data.msg, 'error');
                }
            });
        }
    }

    async function disconnectSpotify() {
        if (!confirm('¿Desconectar Spotify?')) return;
        await GF.post(window.GF_BASE + '/api/spotify.php?action=disconnect', {});
        showToast('Spotify desconectado', 'success');
        setTimeout(() => location.reload(), 1000);
    }

    function updateCredentials() {
        document.getElementById('creds-edit').style.display = 'block';
    }
</script>
<?php layout_end(); ?>