<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('instructor', 'admin', 'superadmin');
$gymId = (int) $user['gym_id'];

$stmtSalas = db()->prepare("SELECT id, name FROM salas WHERE gym_id = ? AND active = 1");
$stmtSalas->execute([$gymId]);
$salas = $stmtSalas->fetchAll();

// Slug del gym para la cartelera
$gymSlug = db()->prepare("SELECT slug FROM gyms WHERE id = ?");
$gymSlug->execute([$gymId]);
$gymSlug = $gymSlug->fetchColumn() ?: '';

$stmtSlots = db()->prepare("SELECT ss.*, ss.label AS class_name, gs.name as session_name, gs.id as session_id, s.name as sala_name FROM schedule_slots ss JOIN salas s ON ss.sala_id = s.id LEFT JOIN gym_sessions gs ON ss.session_id = gs.id WHERE s.gym_id = ? ORDER BY ss.day_of_week, ss.start_time");
$stmtSlots->execute([$gymId]);
$slots = $stmtSlots->fetchAll();

$slotsByDay = array_fill(0, 7, []);
foreach ($slots as $slot) {
    $slotsByDay[(int) $slot['day_of_week']][] = $slot;
}
$days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

layout_header('Programación Semanal', 'scheduler', $user);
nav_section('Instructor');
nav_item(BASE_URL . '/pages/instructor/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'scheduler');
nav_item(BASE_URL . '/pages/instructor/builder.php', 'Builder', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>', 'builder', 'scheduler');
nav_item(BASE_URL . '/pages/instructor/sessions.php', 'Sesiones', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>', 'sessions', 'scheduler');
nav_item(BASE_URL . '/pages/instructor/library.php', 'Biblioteca', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>', 'library', 'scheduler');
nav_item(BASE_URL . '/pages/instructor/profile.php', 'Mi Perfil', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>', 'profile', 'scheduler');
nav_item(BASE_URL . '/pages/instructor/scheduler.php', 'Programación', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'scheduler', 'scheduler');
layout_footer($user);
?>

<div class="page-header">
    <h1 style="font-size:20px;font-weight:700">Programación Semanal</h1>
    <div class="flex gap-2 ml-auto">
        <select id="sala-filter" class="form-control" style="width:auto;padding:8px 12px;font-size:13px"
            onchange="filterBySala(this.value)">
            <option value="">Todas las salas</option>
            <?php foreach ($salas as $s): ?>
                <option value="<?php echo $s['id'] ?>">
                    <?php echo htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($gymSlug): ?>
            <a class="btn btn-secondary" id="cartelera-btn"
                href="<?= BASE_URL ?>/pages/display/agenda.php?gym=<?= htmlspecialchars($gymSlug) ?>" target="_blank"
                style="display:inline-flex;align-items:center;gap:7px;text-decoration:none">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Cartelera
            </a>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="document.getElementById('slot-modal').classList.add('open')">+
            Agregar</button>
    </div>
</div>

<style>
    .schedule-slot {
        position: relative;
    }

    .slot-actions {
        display: none;
        position: absolute;
        top: 5px;
        right: 5px;
        gap: 4px;
        z-index: 2;
    }

    .schedule-slot:hover .slot-actions {
        display: flex;
    }

    .slot-btn {
        width: 26px;
        height: 26px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background .15s, transform .1s;
        padding: 0;
    }

    .slot-btn:hover {
        transform: scale(1.12);
    }

    .slot-btn-edit {
        background: rgba(0, 245, 212, .15);
        color: var(--gf-accent);
    }

    .slot-btn-edit:hover {
        background: rgba(0, 245, 212, .3);
    }

    .slot-btn-del {
        background: rgba(255, 80, 80, .12);
        color: #ff6b6b;
    }

    .slot-btn-del:hover {
        background: rgba(255, 80, 80, .28);
    }
</style>

<div class="page-body">
    <!-- Weekly grid -->
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:12px;min-height:500px">
        <?php foreach ($days as $dayIdx => $dayLabel): ?>
            <div class="card" style="padding:12px;min-height:200px">
                <div
                    style="font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:var(--gf-text-muted);margin-bottom:12px">
                    <?php echo $dayLabel ?>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px" id="day-<?php echo $dayIdx ?>">
                    <?php foreach ($slotsByDay[$dayIdx] as $slot): ?>
                        <div class="schedule-slot" data-sala="<?php echo $slot['sala_id'] ?>"
                            style="padding:8px 10px;background:var(--gf-accent-dim);border:1px solid rgba(0,245,212,.2);border-radius:8px">

                            <!-- Action buttons (visible on hover) -->
                            <div class="slot-actions">
                                <button class="slot-btn slot-btn-edit" title="Editar"
                                    onclick="editSlot(<?= $slot['id'] ?>,<?= $slot['day_of_week'] ?>,'<?= addslashes(substr($slot['start_time'], 0, 5)) ?>','<?= addslashes(substr($slot['end_time'], 0, 5)) ?>',<?= (int) $slot['sala_id'] ?>,'<?= addslashes($slot['class_name'] ?? $slot['session_name'] ?? '') ?>')">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button class="slot-btn slot-btn-del" title="Eliminar" onclick="deleteSlot(<?= $slot['id'] ?>)">
                                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>

                            <div style="font-size:11px;color:var(--gf-accent);font-weight:700">
                                <?php echo substr($slot['start_time'], 0, 5) ?>
                            </div>
                            <div
                                style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?php echo htmlspecialchars($slot['class_name'] ?? $slot['session_name'] ?? 'Clase') ?>
                            </div>
                            <div style="font-size:10px;color:var(--gf-text-dim)">
                                <?php echo htmlspecialchars($slot['sala_name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Add slot button -->
                    <button class="btn btn-ghost btn-sm"
                        style="width:100%;font-size:11px;border-style:dashed;margin-top:4px"
                        onclick="openAddSlot(<?php echo $dayIdx ?>)">+</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add / Edit Slot Modal -->
<div class="modal-overlay" id="slot-modal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3 class="modal-title" id="slot-modal-title">Agregar al Horario</h3>
            <button class="modal-close" onclick="closeSlotModal()">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form onsubmit="saveSlot(event)">
            <input type="hidden" id="slot-editing-id" value="">
            <div class="form-group">
                <label class="form-label">Día</label>
                <select class="form-control" id="slot-day">
                    <?php foreach ($days as $i => $d): ?>
                        <option value="<?php echo $i ?>">
                            <?php echo $d ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="param-row">
                <div class="form-group"><label class="form-label">Inicio</label><input type="time" class="form-control"
                        id="slot-start" value="08:00" required></div>
                <div class="form-group"><label class="form-label">Fin</label><input type="time" class="form-control"
                        id="slot-end" value="09:00" required></div>
            </div>
            <div class="form-group">
                <label class="form-label">Sala</label>
                <select class="form-control" id="slot-sala" required>
                    <?php foreach ($salas as $s): ?>
                        <option value="<?php echo $s['id'] ?>">
                            <?php echo htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Nombre de la clase</label><input class="form-control"
                    id="slot-name" placeholder="CrossFit, HIIT, Yoga..." required></div>
            <button type="submit" class="btn btn-primary" id="slot-submit-btn"
                style="width:100%;margin-top:8px">Agregar</button>
        </form>
    </div>
</div>

<script src="<?php echo BASE_URL ?>/assets/js/api.js"></script>
<script>
    const BASE = window.GF_BASE;

    function closeSlotModal() {
        document.getElementById('slot-modal').classList.remove('open');
        document.getElementById('slot-editing-id').value = '';
        document.getElementById('slot-modal-title').textContent = 'Agregar al Horario';
        document.getElementById('slot-submit-btn').textContent = 'Agregar';
    }

    function openAddSlot(dayIdx) {
        document.getElementById('slot-editing-id').value = '';
        document.getElementById('slot-modal-title').textContent = 'Agregar al Horario';
        document.getElementById('slot-submit-btn').textContent = 'Agregar';
        document.getElementById('slot-day').value = dayIdx;
        document.getElementById('slot-start').value = '08:00';
        document.getElementById('slot-end').value = '09:00';
        document.getElementById('slot-name').value = '';
        document.getElementById('slot-modal').classList.add('open');
    }

    function editSlot(id, day, start, end, salaId, name) {
        document.getElementById('slot-editing-id').value = id;
        document.getElementById('slot-modal-title').textContent = 'Editar Clase';
        document.getElementById('slot-submit-btn').textContent = 'Guardar cambios';
        document.getElementById('slot-day').value = day;
        document.getElementById('slot-start').value = start;
        document.getElementById('slot-end').value = end;
        document.getElementById('slot-sala').value = salaId;
        document.getElementById('slot-name').value = name;
        document.getElementById('slot-modal').classList.add('open');
    }

    async function saveSlot(e) {
        e.preventDefault();
        const editId = document.getElementById('slot-editing-id').value;
        const data = {
            day_of_week: +document.getElementById('slot-day').value,
            start_time: document.getElementById('slot-start').value,
            end_time: document.getElementById('slot-end').value,
            sala_id: +document.getElementById('slot-sala').value,
            class_name: document.getElementById('slot-name').value,
        };
        try {
            if (editId) {
                await GF.put(BASE + '/api/schedules.php?id=' + editId, data);
                showToast('Clase actualizada', 'success');
            } else {
                await GF.post(BASE + '/api/schedules.php', data);
                showToast('Clase agregada al horario', 'success');
            }
            location.reload();
        } catch (err) {
            console.error('saveSlot error:', err);
            showToast('Error al guardar: ' + (err?.message || 'revisa la consola'), 'error');
        }
    }

    async function deleteSlot(id) {
        if (!confirm('¿Eliminar esta clase del horario?')) return;
        try {
            await GF.delete(BASE + '/api/schedules.php?id=' + id);
            showToast('Clase eliminada', 'success');
            location.reload();
        } catch (err) {
            console.error('deleteSlot error:', err);
            showToast('Error al eliminar: ' + (err?.message || 'revisa la consola'), 'error');
        }
    }

    function filterBySala(salaId) {
        document.querySelectorAll('.schedule-slot').forEach(el => {
            el.style.display = (!salaId || el.dataset.sala == salaId) ? '' : 'none';
        });
        // Sync cartelera URL with active sala filter
        const btn = document.getElementById('cartelera-btn');
        if (btn) {
            const url = new URL(btn.href);
            if (salaId) {
                url.searchParams.set('sala', salaId);
            } else {
                url.searchParams.delete('sala');
            }
            btn.href = url.toString();
        }
    }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) closeSlotModal(); });
    });
</script>

<?php layout_end(); ?>