<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = (int) $user['gym_id'];

$stmt = db()->prepare("SELECT * FROM exercises WHERE gym_id IS NULL OR gym_id = ? ORDER BY muscle_group, name");
$stmt->execute([$gymId]);
$exercises = $stmt->fetchAll();

$byMuscle = [];
foreach ($exercises as $ex) {
    $byMuscle[$ex['muscle_group']][] = $ex;
}

layout_header('Biblioteca de Ejercicios', 'library', $user);
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'library');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'library');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'library');
nav_item(BASE_URL . '/pages/instructor/library.php', 'Biblioteca', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>', 'library', 'library');
nav_item(BASE_URL . '/pages/instructor/profile.php', 'Mi Perfil', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'profile', 'library');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'library');
layout_footer($user);
?>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Biblioteca de Ejercicios</h1>
    <div class="ml-auto flex gap-2">
        <div class="search-box" style="width:260px">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" class="form-control" id="lib-search" placeholder="Buscar ejercicio..."
                oninput="searchLib(this.value)" style="padding-left:36px">
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('add-ex-modal').classList.add('open')">+
            Ejercicio</button>
    </div>
</div>

<div class="page-body">
    <!-- Muscle group tabs -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px">
        <button class="muscle-chip active" data-muscle="all" onclick="filterLib(this,'all')">Todos (
            <?php echo count($exercises) ?>)
        </button>
        <?php foreach ($byMuscle as $muscle => $exs): ?>
            <button class="muscle-chip" data-muscle="<?php echo $muscle ?>"
                onclick="filterLib(this,'<?php echo $muscle ?>')">
                <?php echo ucfirst(str_replace('_', ' ', $muscle)) ?> (
                <?php echo count($exs) ?>)
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Exercise cards -->
    <div id="lib-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
        <?php foreach ($exercises as $ex):
            $muscleColors = ['chest' => '#ef4444', 'back' => '#3b82f6', 'shoulders' => '#8b5cf6', 'arms' => '#ec4899', 'core' => '#f59e0b', 'legs' => '#10b981', 'glutes' => '#f97316', 'full_body' => '#00f5d4', 'cardio' => '#06b6d4'];
            $col = $muscleColors[$ex['muscle_group']] ?? '#888';
            ?>
            <div class="exercise-card" data-muscle="<?php echo $ex['muscle_group'] ?>"
                data-name="<?php echo strtolower($ex['name']) ?>">
                <div style="display:flex;align-items:start;justify-content:space-between;margin-bottom:12px">
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:15px;margin-bottom:4px">
                            <?php echo htmlspecialchars($ex['name']) ?>
                        </div>
                        <?php if ($ex['name_es'] && $ex['name_es'] !== $ex['name']): ?>
                            <div style="font-size:12px;color:var(--gf-text-muted)">
                                <?php echo htmlspecialchars($ex['name_es']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="badge"
                        style="background:<?php echo $col ?>26;color:<?php echo $col ?>;border:none;font-size:10px;padding:3px 8px">
                        <?php echo htmlspecialchars(str_replace('_', ' ', $ex['muscle_group'])) ?>
                    </span>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                    <span class="badge badge-muted" style="font-size:11px">
                        <?php echo $ex['level'] ?? 'all' ?>
                    </span>
                    <span class="badge badge-muted" style="font-size:11px">⏱
                        <?php echo $ex['duration_rec'] ?>s recomendado
                    </span>
                    <?php if ($ex['unilateral'] ?? null): ?><span class="badge badge-muted"
                            style="font-size:11px">Unilateral</span>
                    <?php endif; ?>
                </div>
                <?php
                $equip = json_decode($ex['equipment'] ?? '[]', true);
                if ($equip && $equip !== ['none']):
                    ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <?php foreach (array_slice($equip, 0, 3) as $e): ?>
                            <span
                                style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:6px;padding:2px 8px;font-size:11px;color:var(--gf-text-muted)">
                                <?php echo htmlspecialchars($e) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top:12px;display:flex;justify-content:flex-end">
                    <button class="btn btn-ghost btn-sm"
                        onclick="addToBuilder(<?php echo $ex['id'] ?>, '<?php echo htmlspecialchars(addslashes($ex['name'])) ?>')">+
                        Builder</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Exercise Modal -->
<div class="modal-overlay" id="add-ex-modal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h3 class="modal-title">Nuevo Ejercicio</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').classList.remove('open')"><svg
                    width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg></button>
        </div>
        <form onsubmit="createExercise(event)">
            <div class="param-row">
                <div class="form-group"><label class="form-label">Nombre (EN)</label><input class="form-control"
                        id="ex-name" required></div>
                <div class="form-group"><label class="form-label">Nombre (ES)</label><input class="form-control"
                        id="ex-name-es"></div>
            </div>
            <div class="param-row">
                <div class="form-group">
                    <label class="form-label">Músculo</label>
                    <select class="form-control" id="ex-muscle">
                        <?php foreach (array_keys($byMuscle) as $m): ?>
                            <option value="<?php echo $m ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $m)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nivel</label>
                    <select class="form-control" id="ex-level">
                        <option value="beginner">Principiante</option>
                        <option value="intermediate" selected>Intermedio</option>
                        <option value="advanced">Avanzado</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Duración recomendada (s)</label><input type="number"
                    class="form-control" id="ex-dur" value="40" min="5"></div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Crear Ejercicio</button>
        </form>
    </div>
</div>

<style>
    .exercise-card {
        background: var(--gf-surface);
        border: 1px solid var(--gf-border);
        border-radius: 14px;
        padding: 16px;
        transition: all .2s;
    }

    .exercise-card:hover {
        border-color: rgba(0, 245, 212, .3);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, .3);
    }

    .exercise-card[style*="display:none"] {
        display: none !important;
    }
</style>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    let currentMuscle = 'all';

    function filterLib(btn, muscle) {
        document.querySelectorAll('.muscle-chip').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentMuscle = muscle;
        filterCards();
    }

    function searchLib(q) { filterCards(q); }

    function filterCards(q = document.getElementById('lib-search')?.value || '') {
        document.querySelectorAll('.exercise-card').forEach(card => {
            const matchMuscle = currentMuscle === 'all' || card.dataset.muscle === currentMuscle;
            const matchName = !q || card.dataset.name.includes(q.toLowerCase());
            card.style.display = (matchMuscle && matchName) ? '' : 'none';
        });
    }

    async function createExercise(e) {
        e.preventDefault();
        const data = {
            name: document.getElementById('ex-name').value,
            name_es: document.getElementById('ex-name-es').value,
            muscle_group: document.getElementById('ex-muscle').value,
            level: document.getElementById('ex-level').value,
            duration_rec: +document.getElementById('ex-dur').value,
            gym_specific: true
        };
        await GF.post(window.GF_BASE + '/api/exercises.php', data);
        showToast('Ejercicio creado', 'success');
        location.reload();
    }

    function addToBuilder(id, name) {
        showToast(`"${name}" → abrí el Builder para arrastrarlo`, 'info');
    }

    // Close modals on backdrop
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php layout_end(); ?>