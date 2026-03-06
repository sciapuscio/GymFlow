<?php
/**
 * GymFlow — Sesiones compartidas conmigo
 * Sessions that other instructors have shared with the current user.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin', 'member');
$userEmail = strtolower($user['email'] ?? '');

// Sessions shared with this user's email
$sessions = db()->prepare("
    SELECT gs.id, gs.name, gs.share_description, gs.total_duration, gs.created_at,
           g.name  AS gym_name,
           g.id    AS source_gym_id,
           ic.id   AS client_id,
           ic.gym_id AS instructor_gym_id
    FROM session_access_grants sag
    JOIN instructor_clients ic  ON ic.id = sag.client_id
    JOIN gym_sessions gs        ON gs.id = sag.session_id AND gs.shared = 1
    JOIN gyms g                 ON g.id  = gs.gym_id
    WHERE LOWER(ic.client_email) = ?
      AND ic.status = 'active'
    ORDER BY gs.created_at DESC
");
$sessions->execute([$userEmail]);
$sessions = $sessions->fetchAll();

$svgShare = '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>';

layout_header('Sesiones compartidas', 'compartidas', $user);

// Sidebar — same as clientes.php
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'compartidas');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'compartidas');
nav_item(BASE_URL . '/pages/instructor/clientes.php', 'Clientes', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'clientes', 'compartidas');
nav_item(BASE_URL . '/pages/instructor/compartidas.php', 'Compartidas', $svgShare, 'compartidas', 'compartidas');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'compartidas');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'compartidas');
layout_footer($user);
?>

<div class="page-header">
    <?= $svgShare ?>
    <h1 style="font-size:20px;font-weight:700">Sesiones compartidas conmigo</h1>
    <span
        style="margin-left:12px;font-size:12px;padding:4px 12px;border-radius:20px;background:rgba(59,130,246,.1);color:#60a5fa">
        <?= count($sessions) ?> sesión
        <?= count($sessions) != 1 ? 'es' : '' ?>
    </span>
</div>

<div class="page-body">

    <?php if (empty($sessions)): ?>
        <div class="card" style="padding:60px 30px;text-align:center">
            <?= $svgShare ?>
            <h3 style="margin-top:16px;margin-bottom:8px">Sin sesiones compartidas</h3>
            <p style="color:var(--gf-text-muted);font-size:14px">
                Cuando un instructor te comparta sesiones, aparecerán acá.
            </p>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
            <?php foreach ($sessions as $s): ?>
                <div class="card" id="shared-<?= $s['id'] ?>" style="padding:0;overflow:hidden">

                    <!-- Header with gym accent -->
                    <div
                        style="background:linear-gradient(135deg,rgba(229,255,61,.06),rgba(229,255,61,.02));border-bottom:1px solid var(--gf-border);padding:16px 20px">
                        <div
                            style="font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--gf-accent);margin-bottom:6px">
                            <?= htmlspecialchars($s['gym_name']) ?>
                        </div>
                        <div style="font-weight:700;font-size:16px;line-height:1.3">
                            <?= htmlspecialchars($s['name']) ?>
                        </div>
                        <?php if ($s['share_description']): ?>
                            <div style="font-size:13px;color:var(--gf-text-muted);margin-top:6px;line-height:1.5">
                                <?= htmlspecialchars($s['share_description']) ?>
                            </div>
                        <?php endif ?>
                    </div>

                    <!-- Footer -->
                    <div style="padding:12px 20px;display:flex;align-items:center;justify-content:space-between">
                        <div style="font-size:12px;color:var(--gf-text-dim)">
                            <?= $s['total_duration'] ? formatDuration((int) $s['total_duration']) : '—' ?> &nbsp;·&nbsp;
                            <?= (new DateTime($s['created_at']))->format('d/m/Y') ?>
                        </div>
                        <button class="btn btn-primary btn-sm"
                            onclick="viewSession(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')">
                            Ver sesión
                        </button>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>

<!-- Session Detail Modal -->
<div class="modal-overlay" id="session-detail-modal">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-session-title">Sesión</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="modal-session-body" style="padding:20px 24px 24px;max-height:60vh;overflow-y:auto"></div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/api.js"></script>
<script>
    const BASE = window.GF_BASE;

    async function viewSession(id, name) {
        document.getElementById('modal-session-title').textContent = name;
        document.getElementById('modal-session-body').innerHTML = '<div style="text-align:center;padding:32px;color:var(--gf-text-muted)">Cargando…</div>';
        document.getElementById('session-detail-modal').classList.add('open');

        try {
            const data = await GF.get(`${BASE}/api/sessions.php?id=${id}&shared=1`);

            const blocks = Array.isArray(data.blocks_json) ? data.blocks_json
                : (typeof data.blocks_json === 'string' ? JSON.parse(data.blocks_json || '[]') : []);

            if (!blocks.length) {
                document.getElementById('modal-session-body').innerHTML = '<p style="color:var(--gf-text-muted)">Esta sesión no tiene bloques aun.</p>';
                return;
            }

            document.getElementById('modal-session-body').innerHTML = blocks.map((b, i) => `
            <div style="margin-bottom:16px;padding:14px 16px;background:var(--gf-surface);border:1px solid var(--gf-border);border-radius:12px">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-dim);margin-bottom:6px">Bloque ${i + 1}</div>
                ${b.exercises ? b.exercises.map(ex => `
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(255,255,255,.05)">
                        <span style="font-weight:600;font-size:13px">${ex.name || ex.exercise_name || '—'}</span>
                        <span style="font-size:12px;color:var(--gf-text-muted)">${ex.duration ? ex.duration + 's' : '—'}</span>
                    </div>
                `).join('') : '<span style="color:var(--gf-text-dim);font-size:13px">Sin ejercicios</span>'}
            </div>
        `).join('');
        } catch (e) {
            document.getElementById('modal-session-body').innerHTML = '<p style="color:#ef4444">Error al cargar la sesión.</p>';
        }
    }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php layout_end(); ?>