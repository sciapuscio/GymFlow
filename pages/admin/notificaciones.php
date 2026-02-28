<?php
/**
 * GymFlow — Admin Notificaciones Push
 * Allows gym admins to broadcast push notifications to all members.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];

layout_header('Notificaciones Push', 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'notificaciones');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Agenda', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'notificaciones');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'members', 'notificaciones');
nav_item(BASE_URL . '/pages/admin/asistencias.php', 'Asistencias', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>', 'asistencias', 'notificaciones');
nav_item(BASE_URL . '/pages/admin/notificaciones.php', 'Notificaciones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>', 'notificaciones', 'notificaciones');
layout_footer($user);
?>

<div class="page-header">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
    <h1 style="font-size:18px;font-weight:700">Notificaciones Push</h1>
    <div id="device-badge"
        style="margin-left:12px;font-size:12px;padding:4px 12px;border-radius:20px;background:rgba(107,114,128,.12);color:var(--gf-text-muted)">
        Cargando…</div>
</div>

<div class="page-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

        <!-- ── Compose ───────────────────────────────────────── -->
        <div class="card" style="padding:24px">
            <h2 style="font-size:15px;font-weight:700;margin-bottom:4px">📣 Nueva notificación</h2>
            <p style="font-size:12px;color:var(--gf-text-muted);margin-bottom:18px;line-height:1.5">
                Se envía a <strong id="device-count">…</strong> dispositivos registrados en este gimnasio.
            </p>

            <div id="push-result"
                style="display:none;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:16px"></div>

            <form id="push-form" onsubmit="sendPush(event)">
                <div class="form-group" style="margin-bottom:14px">
                    <label class="form-label" style="font-size:12px;font-weight:600;margin-bottom:5px">Título
                        <span id="title-count" style="font-weight:400;color:var(--gf-text-dim)">0/60</span>
                    </label>
                    <input id="push-title" class="form-control" type="text" maxlength="60"
                        placeholder="¡Oferta especial para socios! 🎉"
                        oninput="document.getElementById('title-count').textContent=this.value.length+'/60'" required>
                </div>

                <div class="form-group" style="margin-bottom:18px">
                    <label class="form-label" style="font-size:12px;font-weight:600;margin-bottom:5px">Mensaje
                        <span id="body-count" style="font-weight:400;color:var(--gf-text-dim)">0/200</span>
                    </label>
                    <textarea id="push-body" class="form-control" rows="4" maxlength="200"
                        placeholder="Escribí el cuerpo de la notificación…"
                        oninput="document.getElementById('body-count').textContent=this.value.length+'/200'" required
                        style="resize:vertical"></textarea>
                </div>

                <!-- Preview -->
                <div
                    style="background:rgba(255,255,255,.04);border:1px solid var(--gf-border);border-radius:12px;padding:14px 16px;margin-bottom:18px">
                    <div
                        style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-text-dim);margin-bottom:8px">
                        Preview</div>
                    <div style="display:flex;gap:10px;align-items:flex-start">
                        <div
                            style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,var(--gf-accent),var(--gf-accent-2));display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
                            🏋️</div>
                        <div>
                            <div id="prev-title" style="font-size:13px;font-weight:700;color:var(--gf-text)">Título de
                                la notificación</div>
                            <div id="prev-body"
                                style="font-size:12px;color:var(--gf-text-muted);margin-top:2px;line-height:1.4">Cuerpo
                                del mensaje…</div>
                        </div>
                    </div>
                </div>

                <button type="submit" id="push-btn" class="btn btn-primary"
                    style="width:100%;font-size:14px;padding:12px">
                    <span id="push-btn-text">📤 Enviar a todos</span>
                </button>
            </form>
        </div>

        <!-- ── History ──────────────────────────────────────── -->
        <div class="card" style="overflow:hidden">
            <div style="padding:20px 20px 12px;font-size:15px;font-weight:700">📋 Historial</div>
            <div id="history-list" style="font-size:13px">
                <div style="padding:32px;text-align:center;color:var(--gf-text-muted)">Cargando…</div>
            </div>
        </div>

    </div>
</div>

<script>
    const BASE_URL = '<?= BASE_URL ?>';
    const GYM_PARAM = '<?= $user['role'] === 'superadmin' ? '&gym_id=' . $gymId : '' ?>';

    // Live preview
    document.getElementById('push-title').addEventListener('input', e => {
        document.getElementById('prev-title').textContent = e.target.value || 'Título de la notificación';
    });
    document.getElementById('push-body').addEventListener('input', e => {
        document.getElementById('prev-body').textContent = e.target.value || 'Cuerpo del mensaje…';
    });

    // Load device count + history
    async function loadData() {
        try {
            const res = await fetch(`${BASE_URL}/api/admin-push.php?t=${Date.now()}${GYM_PARAM}`, { credentials: 'include' });
            const json = await res.json();

            const n = json.device_count ?? 0;
            document.getElementById('device-count').textContent = n;
            document.getElementById('device-badge').textContent = `${n} dispositivo${n !== 1 ? 's' : ''} registrado${n !== 1 ? 's' : ''}`;
            document.getElementById('device-badge').style.background = n > 0 ? 'rgba(16,185,129,.12)' : 'rgba(107,114,128,.12)';
            document.getElementById('device-badge').style.color = n > 0 ? '#10b981' : 'var(--gf-text-muted)';

            renderHistory(json.history ?? []);
        } catch (e) {
            document.getElementById('device-badge').textContent = 'Error al cargar';
        }
    }

    function renderHistory(items) {
        const el = document.getElementById('history-list');
        if (!items.length) {
            el.innerHTML = '<div style="padding:32px;text-align:center;color:var(--gf-text-muted)">Sin notificaciones enviadas aún.</div>';
            return;
        }
        el.innerHTML = items.map(n => `
        <div style="padding:12px 20px;border-bottom:1px solid var(--gf-border)">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(n.title)}</div>
                    <div style="font-size:12px;color:var(--gf-text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(n.body)}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:11px;color:#10b981;font-weight:600">✅ ${n.sent}</div>
                    ${n.failed > 0 ? `<div style="font-size:11px;color:#ef4444;font-weight:600">❌ ${n.failed}</div>` : ''}
                    <div style="font-size:10px;color:var(--gf-text-dim);margin-top:2px">${fmtDate(n.created_at)}</div>
                </div>
            </div>
        </div>
    `).join('');
    }

    async function sendPush(e) {
        e.preventDefault();
        const btn = document.getElementById('push-btn');
        const btnText = document.getElementById('push-btn-text');
        const result = document.getElementById('push-result');
        const title = document.getElementById('push-title').value.trim();
        const body = document.getElementById('push-body').value.trim();

        btn.disabled = true;
        btnText.textContent = '⏳ Enviando…';
        result.style.display = 'none';

        try {
            const res = await fetch(`${BASE_URL}/api/admin-push.php${GYM_PARAM ? '?' + GYM_PARAM.slice(1) : ''}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ title, body }),
            });
            const json = await res.json();

            if (json.error) throw new Error(json.error);

            result.style.display = 'block';
            result.style.background = 'rgba(16,185,129,.1)';
            result.style.border = '1px solid rgba(16,185,129,.3)';
            result.style.color = '#10b981';
            result.textContent = json.message;

            // Clear form
            document.getElementById('push-title').value = '';
            document.getElementById('push-body').value = '';
            document.getElementById('title-count').textContent = '0/60';
            document.getElementById('body-count').textContent = '0/200';
            document.getElementById('prev-title').textContent = 'Título de la notificación';
            document.getElementById('prev-body').textContent = 'Cuerpo del mensaje…';

            loadData(); // refresh history
        } catch (err) {
            result.style.display = 'block';
            result.style.background = 'rgba(239,68,68,.1)';
            result.style.border = '1px solid rgba(239,68,68,.3)';
            result.style.color = '#ef4444';
            result.textContent = '❌ ' + (err.message || 'Error desconocido');
        } finally {
            btn.disabled = false;
            btnText.textContent = '📤 Enviar a todos';
        }
    }

    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function fmtDate(s) {
        const d = new Date(s);
        return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit' }) + ' ' +
            d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
    }

    loadData();
</script>