<?php
/**
 * GymFlow — Gestión de Sedes
 * Lista, crea, edita y muestra QR de cada sede del gimnasio.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin');
$gymId = $user['role'] === 'superadmin'
    ? (int) ($_GET['gym_id'] ?? verifyCookieValue('sa_gym_ctx') ?? 0)
    : (int) $user['gym_id'];

// ── Load sedes ────────────────────────────────────────────────────────────────
$sedesStmt = db()->prepare("
    SELECT s.*,
           COUNT(DISTINCT ss.id) AS slots_count,
           COUNT(DISTINCT sa.id) AS salas_count
    FROM sedes s
    LEFT JOIN schedule_slots ss ON ss.sede_id = s.id
    LEFT JOIN salas          sa ON sa.sede_id = s.id
    WHERE s.gym_id = ?
    GROUP BY s.id
    ORDER BY s.name
");
$sedesStmt->execute([$gymId]);
$sedes = $sedesStmt->fetchAll();

layout_header('Sedes', 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'sedes');
nav_item(BASE_URL . '/pages/admin/sedes.php', 'Sedes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>', 'sedes', 'sedes');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'members', 'sedes');
nav_item(BASE_URL . '/pages/admin/asistencias.php', 'Asistencias', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>', 'asistencias', 'sedes');
layout_footer($user);
?>

<div class="page-header">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
    </svg>
    <h1 style="font-size:18px;font-weight:700">Sedes</h1>
    <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="openModal()">+ Nueva sede</button>
</div>

<div class="page-body">
    <?php if (empty($sedes)): ?>
        <div class="card" style="text-align:center;padding:48px 24px;color:var(--gf-text-muted)">
            <div style="font-size:36px;margin-bottom:12px">🏢</div>
            <div style="font-size:15px;font-weight:600;margin-bottom:6px">Sin sedes configuradas</div>
            <div style="font-size:13px;margin-bottom:20px">Creá las sedes de tu gimnasio para separar grillas y check-ins.
            </div>
            <button class="btn btn-primary btn-sm" onclick="openModal()">Crear primera sede</button>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
            <?php foreach ($sedes as $sede): ?>
                <div class="card" style="overflow:hidden">
                    <div style="padding:20px 20px 16px;display:flex;align-items:flex-start;gap:14px">
                        <div
                            style="width:44px;height:44px;border-radius:12px;background:var(--gf-accent-dim);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">
                            🏢</div>
                        <div style="flex:1;min-width:0">
                            <div
                                style="font-size:15px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?= htmlspecialchars($sede['name']) ?>
                            </div>
                            <?php if ($sede['address']): ?>
                                <div style="font-size:12px;color:var(--gf-text-muted);margin-top:2px">
                                    <?= htmlspecialchars($sede['address']) ?>
                                </div>
                            <?php endif ?>
                            <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap">
                                <span style="font-size:11px;color:var(--gf-text-muted)">📅
                                    <?= $sede['slots_count'] ?> clases
                                </span>
                                <span style="font-size:11px;color:var(--gf-text-muted)">🏠
                                    <?= $sede['salas_count'] ?> salas
                                </span>
                                <?php if (!$sede['active']): ?>
                                    <span style="font-size:11px;color:#ef4444;font-weight:600">INACTIVA</span>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>
                    <div
                        style="border-top:1px solid var(--gf-border);padding:12px 20px;display:flex;gap:8px;align-items:center">
                        <button class="btn btn-sm" onclick='editSede(<?= htmlspecialchars(json_encode($sede)) ?>)'
                            style="font-size:12px">Editar</button>
                        <button class="btn btn-sm" onclick='showQR(<?= (int) $sede['id'] ?>,
                    <?= json_encode($sede['name']) ?>,
                    <?= json_encode($sede['qr_token']) ?>)'
                            style="font-size:12px">Ver QR
                        </button>
                        <button class="btn btn-sm" onclick='toggleActive(<?= (int) $sede['id'] ?>,
                    <?= $sede['active'] ? 0 : 1 ?>)'
                            style="font-size:12px;margin-left:auto;color:
                    <?= $sede['active'] ? '#ef4444' : 'var(--gf-accent)' ?>">
                            <?= $sede['active'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<!-- Modal: crear/editar sede -->
<div id="sede-modal"
    style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(8,8,16,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center">
    <div
        style="background:var(--gf-surface);border:1px solid var(--gf-border);border-radius:20px;padding:32px;width:90%;max-width:480px">
        <h2 id="modal-title" style="font-size:17px;font-weight:700;margin-bottom:24px">Nueva sede</h2>
        <input id="sede-id" type="hidden" value="">
        <div style="margin-bottom:16px">
            <label
                style="font-size:13px;font-weight:600;color:var(--gf-text-muted);display:block;margin-bottom:6px">Nombre
                *</label>
            <input id="sede-name" class="input" placeholder="Ej: Sede Centro" style="width:100%">
        </div>
        <div style="margin-bottom:24px">
            <label
                style="font-size:13px;font-weight:600;color:var(--gf-text-muted);display:block;margin-bottom:6px">Dirección</label>
            <input id="sede-address" class="input" placeholder="Av. Corrientes 1234, CABA" style="width:100%">
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
            <button class="btn btn-sm" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary btn-sm" id="save-btn" onclick="saveSede()">Guardar</button>
        </div>
    </div>
</div>

<!-- Modal: QR de sede -->
<div id="qr-modal"
    style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(8,8,16,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:32px;width:90%;max-width:360px;text-align:center">
        <div id="qr-sede-name" style="font-size:16px;font-weight:700;color:#080810;margin-bottom:20px"></div>
        <div id="qr-container" style="margin:0 auto 20px"></div>
        <div style="font-size:11px;color:#888;margin-bottom:20px">Imprimí este QR y colocalo en la entrada de la sede
        </div>
        <div style="display:flex;gap:10px;justify-content:center">
            <button class="btn btn-sm" onclick="document.getElementById('qr-modal').style.display='none'"
                style="background:#f0f0f0;color:#080810">Cerrar</button>
            <button class="btn btn-primary btn-sm" onclick="printQR()">Imprimir</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
    const BASE_URL = '<?= BASE_URL ?>';

    function openModal(sede = null) {
        document.getElementById('sede-id').value = sede?.id ?? '';
        document.getElementById('sede-name').value = sede?.name ?? '';
        document.getElementById('sede-address').value = sede?.address ?? '';
        document.getElementById('modal-title').textContent = sede ? 'Editar sede' : 'Nueva sede';
        const m = document.getElementById('sede-modal');
        m.style.display = 'flex';
    }
    function editSede(sede) { openModal(sede); }
    function closeModal() { document.getElementById('sede-modal').style.display = 'none'; }

    async function saveSede() {
        const id = document.getElementById('sede-id').value;
        const name = document.getElementById('sede-name').value.trim();
        const address = document.getElementById('sede-address').value.trim();
        if (!name) { alert('El nombre es obligatorio'); return; }

        const btn = document.getElementById('save-btn');
        btn.disabled = true; btn.textContent = 'Guardando…';

        const method = id ? 'PUT' : 'POST';
        const url = id ? `${BASE_URL}/api/sedes.php?id=${id}` : `${BASE_URL}/api/sedes.php`;
        const res = await fetch(url, {
            method, credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, address })
        });
        const json = await res.json();
        if (json.error) { alert(json.error); btn.disabled = false; btn.textContent = 'Guardar'; return; }
        location.reload();
    }

    async function toggleActive(id, newActive) {
        if (!confirm(newActive ? '¿Activar esta sede?' : '¿Desactivar esta sede?')) return;
        await fetch(`${BASE_URL}/api/sedes.php?id=${id}`, {
            method: 'PUT', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: newActive })
        });
        location.reload();
    }

    let _currentQrToken = null;
    function showQR(id, name, qrToken) {
        _currentQrToken = qrToken;
        document.getElementById('qr-sede-name').textContent = name;
        const container = document.getElementById('qr-container');
        container.innerHTML = '';
        const checkinUrl = `${BASE_URL}/checkin?qr=${qrToken}`;
        QRCode.toCanvas(checkinUrl, { width: 240, margin: 2, color: { dark: '#080810', light: '#ffffff' } }, (err, canvas) => {
            if (!err) container.appendChild(canvas);
        });
        document.getElementById('qr-modal').style.display = 'flex';
    }

    function printQR() {
        const canvas = document.querySelector('#qr-container canvas');
        if (!canvas) return;
        const w = window.open('');
        w.document.write(`<html><body style="text-align:center;padding:40px"><img src="${canvas.toDataURL()}" style="width:300px"><br><p style="font-family:sans-serif;margin-top:20px;font-size:16px">${document.getElementById('qr-sede-name').textContent}</p></body></html>`);
        w.document.close();
        w.print();
    }

    // Close modals on backdrop click
    ['sede-modal', 'qr-modal'].forEach(id => {
        document.getElementById(id).addEventListener('click', e => {
            if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
        });
    });
</script>
<?php layout_end(); ?>