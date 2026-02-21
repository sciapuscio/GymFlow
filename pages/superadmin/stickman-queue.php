<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('superadmin');

// ── Dynamically parse exercise-poses.js to get all names with stickman ────────
$posesFile = file_get_contents(__DIR__ . '/../../assets/js/exercise-poses.js');

// Extract everything inside EXERCISE_ARCHETYPE = { ... };
// Match the block: const EXERCISE_ARCHETYPE = { ... };
preg_match('/const EXERCISE_ARCHETYPE\s*=\s*\{([^}]+)\}/s', $posesFile, $m);
$HAS_STICKMAN = [];
if (!empty($m[1])) {
    // Match all quoted keys: 'Key Name' or "Key Name"
    preg_match_all("/['\"]([^'\"]+)['\"]\s*:/", $m[1], $keys);
    $HAS_STICKMAN = array_unique($keys[1]);
}

// Build placeholders for NOT IN clause (check both name and name_es)
$phs = implode(',', array_fill(0, count($HAS_STICKMAN), '?'));
$stmt = db()->prepare(
    "SELECT e.*, g.name as gym_name, u.name as creator_name
     FROM exercises e
     LEFT JOIN gyms g ON g.id = e.gym_id
     LEFT JOIN users u ON u.id = e.created_by
     WHERE e.name NOT IN ($phs)
       AND (e.name_es IS NULL OR e.name_es NOT IN ($phs))
     ORDER BY e.is_global DESC, g.name, e.name"
);
// bind params twice (for name and name_es IN checks)
$stmt->execute(array_merge($HAS_STICKMAN, $HAS_STICKMAN));
$exercises = $stmt->fetchAll();

$muscleLabels = [
    'chest' => 'Pecho',
    'back' => 'Espalda',
    'shoulders' => 'Hombros',
    'arms' => 'Brazos',
    'core' => 'Core',
    'legs' => 'Piernas',
    'glutes' => 'Glúteos',
    'full_body' => 'Cuerpo completo',
    'cardio' => 'Cardio',
];

layout_header('Stickman pendientes — SuperAdmin', 'superadmin', $user);
nav_section('Super Admin');
nav_item(BASE_URL . '/pages/superadmin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'superadmin', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/gyms.php', 'Gimnasios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/></svg>', 'gyms', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/users.php', 'Usuarios', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'users', 'superadmin');
nav_item(BASE_URL . '/pages/superadmin/stickman-queue.php', 'Stickman', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'stickman', 'superadmin');
layout_footer($user);
?>

<div class="page-header">
    <div>
        <h1 style="font-size:20px;font-weight:700">🕴 Cola de Stickman</h1>
        <div style="font-size:12px;color:var(--gf-text-muted)">
            Ejercicios sin animación stickman —
            <strong style="color:var(--gf-accent)">
                <?php echo count($exercises) ?>
            </strong> pendientes
        </div>
    </div>
    <div class="flex gap-2 ml-auto">
        <input type="text" id="search" class="form-control" style="width:220px" placeholder="Buscar ejercicio..."
            oninput="filterTable(this.value)">
    </div>
</div>

<div class="page-body">
    <div class="card">
        <?php if (empty($exercises)): ?>
            <div class="empty-state">🎉 Todos los ejercicios tienen stickman</div>
        <?php else: ?>
            <div class="table-wrap">
                <table id="ex-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Nombre ES</th>
                            <th>Músculo</th>
                            <th>Origen</th>
                            <th>Creado por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exercises as $ex): ?>
                            <tr data-name="<?php echo strtolower(htmlspecialchars($ex['name'] . ' ' . $ex['name_es'])) ?>">
                                <td>
                                    <div style="font-weight:600">
                                        <?php echo htmlspecialchars($ex['name']) ?>
                                    </div>
                                </td>
                                <td style="color:var(--gf-text-muted);font-size:13px">
                                    <?php echo htmlspecialchars($ex['name_es'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="badge badge-accent" style="font-size:11px">
                                        <?php echo $muscleLabels[$ex['muscle_group']] ?? $ex['muscle_group'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ex['is_global']): ?>
                                        <span class="badge badge-orange" style="font-size:11px">Global</span>
                                    <?php else: ?>
                                        <span style="font-size:13px;color:var(--gf-text-muted)">
                                            <?php echo htmlspecialchars($ex['gym_name'] ?? 'Sin gym') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px;color:var(--gf-text-muted)">
                                    <?php echo htmlspecialchars($ex['creator_name'] ?? '—') ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm"
                                        onclick="copyName('<?php echo addslashes($ex['name']) ?>', '<?php echo addslashes($ex['name_es'] ?? '') ?>')"
                                        title="Copiar para agregar al stickman">
                                        📋 Copiar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick reference: archetypes available -->
    <div class="card" style="margin-top:20px">
        <div
            style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--gf-text-muted);text-transform:uppercase;letter-spacing:.05em">
            Arquetipos disponibles en exercise-poses.js
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php
            $archetypes = ['squat', 'lunge', 'hinge', 'push_h', 'push_v', 'pull_v', 'pull_h', 'swing', 'core_dyn', 'core_iso', 'mc', 'run', 'jump', 'bike', 'row', 'olympic'];
            foreach ($archetypes as $a):
                ?>
                <span
                    style="background:rgba(0,245,212,.07);border:1px solid rgba(0,245,212,.18);color:var(--gf-accent);padding:4px 12px;border-radius:8px;font-size:12px;font-family:monospace">
                    <?php echo $a ?>
                </span>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:10px;font-size:12px;color:rgba(255,255,255,.35)">
            Para agregar animación a un ejercicio nuevo, agregarlo a <code>EXERCISE_ARCHETYPE</code> en
            <code>assets/js/exercise-poses.js</code> mapeando su nombre a uno de estos arquetipos.
        </div>
    </div>
</div>

<div id="copy-toast"
    style="position:fixed;bottom:28px;right:28px;background:#1e2130;border:1px solid var(--gf-border);border-radius:10px;padding:12px 20px;font-size:13px;display:none;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.4)">
    ✓ Copiado
</div>

<script>
    function filterTable(q) {
        q = q.toLowerCase();
        document.querySelectorAll('#ex-table tbody tr').forEach(r => {
            r.style.display = r.dataset.name.includes(q) ? '' : 'none';
        });
    }

    function copyName(name, nameEs) {
        const text = nameEs ? `'${name}': 'squat', '${nameEs}': 'squat',` : `'${name}': 'squat',`;
        navigator.clipboard.writeText(text).then(() => {
            const t = document.getElementById('copy-toast');
            t.textContent = `✓ Copiado: ${name}`;
            t.style.display = 'block';
            setTimeout(() => t.style.display = 'none', 2000);
        });
    }
</script>

<?php layout_end(); ?>