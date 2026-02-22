<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');

// Stats
$gymCount = (int) db()->query("SELECT COUNT(*) FROM gyms")->fetchColumn();
$userCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetchColumn();
$sessionCount = (int) db()->query("SELECT COUNT(*) FROM gym_sessions")->fetchColumn();
$salaCount = (int) db()->query("SELECT COUNT(*) FROM salas")->fetchColumn();
$gymList = db()->query("SELECT g.*, COUNT(DISTINCT u.id) as user_count, COUNT(DISTINCT s.id) as sala_count FROM gyms g LEFT JOIN users u ON u.gym_id = g.id AND u.role != 'superadmin' LEFT JOIN salas s ON s.gym_id = g.id GROUP BY g.id ORDER BY g.name")->fetchAll();

layout_header('Super Admin', 'superadmin', $user);
nav_section('Super Admin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>', 'gyms', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/users.php', 'Usuarios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'users', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/stickman-queue.php', 'Stickman Queue', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'stickman', 'superadmin');
layout_footer($user);
?>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Super Admin</h1>
    <a href="<?php echo BASE_URL ?>/pages/superadmin/gyms.php" class="btn btn-primary ml-auto">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Nuevo Gimnasio
    </a>
</div>

<div class="page-body">
    <div class="grid grid-4 mb-6">
        <div class="stat-card">
            <div class="stat-icon"><svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $gymCount ?>
                </div>
                <div class="stat-label">Gimnasios</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(255,107,53,.15);color:#ff6b35"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $userCount ?>
                </div>
                <div class="stat-label">Usuarios</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(124,58,237,.15);color:#7c3aed"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $sessionCount ?>
                </div>
                <div class="stat-label">Sesiones totales</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.15);color:#10b981"><svg width="24" height="24"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7" />
                </svg></div>
            <div>
                <div class="stat-value">
                    <?php echo $salaCount ?>
                </div>
                <div class="stat-label">Salas</div>
            </div>
        </div>
    </div>

    <!-- Quick tools row -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px">
        <a href="<?php echo BASE_URL ?>/pages/superadmin/gyms.php"
            style="display:flex;align-items:center;gap:12px;padding:16px 18px;background:var(--gf-surface);border:1px solid var(--gf-border);border-radius:14px;text-decoration:none;color:inherit;transition:border-color .2s"
            onmouseover="this.style.borderColor='var(--gf-accent)'"
            onmouseout="this.style.borderColor='var(--gf-border)'">
            <span style="font-size:22px">🏛️</span>
            <div>
                <div style="font-weight:600;font-size:14px">Gimnasios</div>
                <div style="font-size:12px;color:var(--gf-text-muted)">ABM completo</div>
            </div>
        </a>
        <a href="<?php echo BASE_URL ?>/pages/superadmin/gyms.php"
            style="display:flex;align-items:center;gap:12px;padding:16px 18px;background:var(--gf-surface);border:1px solid var(--gf-border);border-radius:14px;text-decoration:none;color:inherit;transition:border-color .2s"
            onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--gf-border)'">
            <span style="font-size:22px">💳</span>
            <div>
                <div style="font-weight:600;font-size:14px">Suscripciones</div>
                <div style="font-size:12px;color:var(--gf-text-muted)">Ciclos por gym</div>
            </div>
        </a>
        <a href="<?php echo BASE_URL ?>/pages/superadmin/users.php"
            style="display:flex;align-items:center;gap:12px;padding:16px 18px;background:var(--gf-surface);border:1px solid var(--gf-border);border-radius:14px;text-decoration:none;color:inherit;transition:border-color .2s"
            onmouseover="this.style.borderColor='var(--gf-accent)'"
            onmouseout="this.style.borderColor='var(--gf-border)'">
            <span style="font-size:22px">👥</span>
            <div>
                <div style="font-weight:600;font-size:14px">Usuarios</div>
                <div style="font-size:12px;color:var(--gf-text-muted)">Todos los roles</div>
            </div>
        </a>
        <a href="<?php echo BASE_URL ?>/pages/superadmin/stickman-queue.php"
            style="display:flex;align-items:center;gap:12px;padding:16px 18px;background:var(--gf-surface);border:1px solid var(--gf-border);border-radius:14px;text-decoration:none;color:inherit;transition:border-color .2s"
            onmouseover="this.style.borderColor='var(--gf-accent)'"
            onmouseout="this.style.borderColor='var(--gf-border)'">
            <span style="font-size:22px">🕴</span>
            <div>
                <div style="font-weight:600;font-size:14px">Stickman Queue</div>
                <div style="font-size:12px;color:var(--gf-text-muted)">Ejercicios sin animación</div>
            </div>
        </a>
    </div>

    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 style="font-size:16px;font-weight:700">Gimnasios</h2>
            <a href="<?php echo BASE_URL ?>/pages/superadmin/gyms.php" class="btn btn-ghost btn-sm">Gestionar</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Usuarios</th>
                        <th>Salas</th>
                        <th>Spotify</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gymList as $g): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <div
                                        style="width:32px;height:32px;border-radius:8px;background:<?php echo htmlspecialchars($g['primary_color']) ?>26;display:flex;align-items:center;justify-content:center;font-weight:700;color:<?php echo htmlspecialchars($g['primary_color']) ?>;font-size:12px">
                                        <?php echo strtoupper(substr($g['name'], 0, 2)) ?>
                                    </div>
                                    <strong>
                                        <?php echo htmlspecialchars($g['name']) ?>
                                    </strong>
                                </div>
                            </td>
                            <td style="color:var(--gf-text-muted);font-size:13px">
                                <?php echo htmlspecialchars($g['slug']) ?>
                            </td>
                            <td>
                                <?php echo $g['user_count'] ?>
                            </td>
                            <td>
                                <?php echo $g['sala_count'] ?>
                            </td>
                            <td><span
                                    class="badge <?php echo $g['spotify_mode'] !== 'disabled' ? 'badge-success' : 'badge-muted' ?>">
                                    <?php echo $g['spotify_mode'] ?>
                                </span></td>
                            <td><span class="badge <?php echo $g['active'] ? 'badge-work' : 'badge-danger' ?>">
                                    <?php echo $g['active'] ? 'Activo' : 'Inactivo' ?>
                                </span></td>
                            <td style="display:flex;gap:6px">
                                <a href="<?php echo BASE_URL ?>/pages/admin/dashboard.php?gym_id=<?php echo $g['id'] ?>"
                                    class="btn btn-ghost btn-sm">Panel</a>
                                <button class="btn btn-danger btn-sm"
                                    onclick="toggleGym(<?php echo $g['id'] ?>, <?php echo (int) !$g['active'] ?>)">
                                    <?php echo $g['active'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══ MENSAJERÍA DEL SISTEMA ════════════════════════════════════════════ -->
<div class="page-body" style="margin-top:0">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">

        <!-- ── Banner persistente ──────────────────────────────────────── -->
        <div class="card">
            <div class="flex items-center gap-2 mb-4">
                <span style="font-size:20px">📌</span>
                <h2 style="font-size:16px;font-weight:700;margin:0">Aviso Persistente</h2>
                <span style="margin-left:auto;font-size:12px;color:var(--gf-text-muted)">Visible en todas las
                    páginas</span>
            </div>

            <!-- Active notices list -->
            <div id="notice-list" style="margin-bottom:16px"></div>

            <!-- Create form -->
            <div style="display:flex;flex-direction:column;gap:10px">
                <select id="notice-type"
                    style="background:var(--gf-surface-2);border:1px solid var(--gf-border);border-radius:8px;color:inherit;padding:8px 12px;font-size:13px">
                    <option value="warning">⚠️ Advertencia</option>
                    <option value="info">ℹ️ Información</option>
                    <option value="error">🚨 Error / Urgente</option>
                </select>
                <textarea id="notice-msg" rows="3"
                    placeholder="Ej: El sistema no estará disponible hoy entre las 20 y 21 hs."
                    style="background:var(--gf-surface-2);border:1px solid var(--gf-border);border-radius:8px;color:inherit;padding:10px 12px;font-size:13px;resize:vertical;font-family:inherit"></textarea>
                <button class="btn btn-primary" onclick="publishNotice()">📌 Publicar aviso</button>
            </div>
        </div>

        <!-- ── Broadcast en tiempo real ────────────────────────────────── -->
        <div class="card">
            <div class="flex items-center gap-2 mb-4">
                <span style="font-size:20px">📢</span>
                <h2 style="font-size:16px;font-weight:700;margin:0">Mensaje Urgente</h2>
                <span style="margin-left:auto;font-size:12px;color:var(--gf-text-muted)">Popup instantáneo en tiempo
                    real</span>
            </div>
            <p style="font-size:13px;color:var(--gf-text-muted);margin-bottom:14px;line-height:1.5">
                Se entrega como popup bloqueante a <strong>todos los admins e instructores</strong> actualmente
                conectados.
                Las pantallas de sala no lo reciben.
            </p>

            <div style="display:flex;flex-direction:column;gap:10px">
                <select id="bc-type"
                    style="background:var(--gf-surface-2);border:1px solid var(--gf-border);border-radius:8px;color:inherit;padding:8px 12px;font-size:13px">
                    <option value="info">ℹ️ Información</option>
                    <option value="warning">⚠️ Advertencia</option>
                    <option value="error">🚨 Error / Urgente</option>
                </select>
                <textarea id="bc-msg" rows="3"
                    placeholder="Ej: Vamos a reiniciar el servidor. Por favor volvé a establecer las salas."
                    style="background:var(--gf-surface-2);border:1px solid var(--gf-border);border-radius:8px;color:inherit;padding:10px 12px;font-size:13px;resize:vertical;font-family:inherit"></textarea>
                <button class="btn btn-primary" id="bc-btn" onclick="sendBroadcast()"
                    style="background:#ff6b35;border-color:#ff6b35">📢 Enviar a todos ahora</button>
                <div id="bc-result" style="font-size:12px;color:var(--gf-text-muted);min-height:18px"></div>
            </div>
        </div>

    </div>
</div>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    async function toggleGym(id, newActive) {
        await GF.put(`${window.GF_BASE}/api/gyms.php?id=${id}`, { active: newActive });
        location.reload();
    }

    // ── System Notices ────────────────────────────────────────────────────
    async function loadNotices() {
        const list = document.getElementById('notice-list');
        const d = await GF.get(`${window.GF_BASE}/api/system-notices.php`);
        if (!d) {
            list.innerHTML = '<div style="font-size:13px;color:var(--gf-text-muted)">Sin avisos activos.</div>';
            return;
        }
        const typeLabel = { warning: '⚠️ Advertencia', info: 'ℹ️ Info', error: '🚨 Urgente' };
        const typeBg = { warning: 'rgba(245,158,11,.08)', info: 'rgba(59,130,246,.08)', error: 'rgba(239,68,68,.08)' };
        list.innerHTML = `
            <div style="padding:12px 14px;border-radius:10px;background:${typeBg[d.type] || typeBg.info};display:flex;align-items:flex-start;gap:10px;font-size:13px">
                <span style="flex-shrink:0">${typeLabel[d.type] || 'ℹ️'}</span>
                <div style="flex:1;line-height:1.5">${d.message.replace(/</g, '&lt;')}</div>
                <button onclick="deleteNotice(${d.id})" title="Eliminar"
                    style="background:none;border:none;color:var(--gf-text-muted);cursor:pointer;font-size:16px;padding:0;flex-shrink:0">✕</button>
            </div>`;
    }

    async function publishNotice() {
        const message = document.getElementById('notice-msg').value.trim();
        const type = document.getElementById('notice-type').value;
        if (!message) return showToast('Escribí un mensaje primero', 'error');
        await GF.post(`${window.GF_BASE}/api/system-notices.php`, { message, type });
        document.getElementById('notice-msg').value = '';
        showToast('Aviso publicado ✓', 'success');
        loadNotices();
    }

    async function deleteNotice(id) {
        await fetch(`${window.GF_BASE}/api/system-notices.php?id=${id}`, { method: 'DELETE', credentials: 'include' });
        showToast('Aviso eliminado', 'info');
        loadNotices();
    }

    // ── Broadcast ────────────────────────────────────────────────────────
    async function sendBroadcast() {
        const message = document.getElementById('bc-msg').value.trim();
        const type = document.getElementById('bc-type').value;
        const btn = document.getElementById('bc-btn');
        const result = document.getElementById('bc-result');
        if (!message) return showToast('Escribí un mensaje primero', 'error');

        btn.disabled = true;
        btn.textContent = 'Enviando…';
        result.textContent = '';

        const d = await GF.post(`${window.GF_BASE}/api/broadcast.php`, { message, type });
        btn.disabled = false;
        btn.textContent = '📢 Enviar a todos ahora';

        if (d?.ok) {
            const n = d.server?.recipients ?? '?';
            result.textContent = `✓ Enviado a ${n} conexión${n !== 1 ? 'es' : ''} activa${n !== 1 ? 's' : ''}.`;
            result.style.color = 'var(--gf-accent)';
            document.getElementById('bc-msg').value = '';
        } else {
            result.textContent = d?.warning || 'Error al enviar.';
            result.style.color = '#ff6b35';
        }
    }

    document.addEventListener('DOMContentLoaded', loadNotices);
</script>
<?php layout_end(); ?>