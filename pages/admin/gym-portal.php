<?php
/**
 * GymFlow Admin — Panel de Portada (bloques editoriales)
 * Permite al staff crear, reordenar y eliminar bloques de imagen / texto enriquecido
 * que se muestran en la pantalla Portada de la app Flutter.
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/layout.php';

$user = requireAuth('admin', 'superadmin', 'staff');
$gymId = (int) $user['gym_id'];

// ── POST handlers (AJAX) ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

    // ── Upload image block ───────────────────────────────────────────────────
    if ($action === 'upload_image' && !empty($_FILES['image'])) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['error' => 'Tipo de archivo no permitido']);
            exit;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['error' => 'El archivo supera 5 MB']);
            exit;
        }
        $dir = __DIR__ . '/../../storage/portal/';
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = 'portal_' . $gymId . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $name);
        $url = BASE_URL . '/storage/portal/' . $name;

        // Insert block
        $maxOrder = db()->prepare("SELECT COALESCE(MAX(sort_order),0) FROM gym_portal_blocks WHERE gym_id=?");
        $maxOrder->execute([$gymId]);
        $order = (int) $maxOrder->fetchColumn() + 10;

        $ins = db()->prepare("INSERT INTO gym_portal_blocks (gym_id, sort_order, type, content, caption, active) VALUES (?,?,?,?,?,1)");
        $ins->execute([$gymId, $order, 'image', $url, $_POST['caption'] ?? null]);
        echo json_encode(['success' => true, 'id' => db()->lastInsertId(), 'url' => $url]);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $act = $body['action'] ?? $action;

    // ── Add richtext block ───────────────────────────────────────────────────
    if ($act === 'add_richtext') {
        $content = $body['content'] ?? '';
        $maxOrder = db()->prepare("SELECT COALESCE(MAX(sort_order),0) FROM gym_portal_blocks WHERE gym_id=?");
        $maxOrder->execute([$gymId]);
        $order = (int) $maxOrder->fetchColumn() + 10;
        $ins = db()->prepare("INSERT INTO gym_portal_blocks (gym_id, sort_order, type, content, active) VALUES (?,?,?,?,1)");
        $ins->execute([$gymId, $order, 'richtext', $content]);
        echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
        exit;
    }

    // ── Update block ─────────────────────────────────────────────────────────
    if ($act === 'update') {
        $id = (int) ($body['id'] ?? 0);
        db()->prepare("UPDATE gym_portal_blocks SET content=?, caption=? WHERE id=? AND gym_id=?")
            ->execute([$body['content'] ?? '', $body['caption'] ?? null, $id, $gymId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Toggle active ────────────────────────────────────────────────────────
    if ($act === 'toggle') {
        $id = (int) ($body['id'] ?? 0);
        db()->prepare("UPDATE gym_portal_blocks SET active = 1 - active WHERE id=? AND gym_id=?")
            ->execute([$id, $gymId]);
        $row = db()->prepare("SELECT active FROM gym_portal_blocks WHERE id=?");
        $row->execute([$id]);
        echo json_encode(['success' => true, 'active' => (bool) $row->fetchColumn()]);
        exit;
    }

    // ── Delete ───────────────────────────────────────────────────────────────
    if ($act === 'delete') {
        $id = (int) ($body['id'] ?? 0);
        db()->prepare("DELETE FROM gym_portal_blocks WHERE id=? AND gym_id=?")->execute([$id, $gymId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── Reorder ──────────────────────────────────────────────────────────────
    if ($act === 'reorder') {
        $ids = $body['ids'] ?? [];
        foreach ($ids as $i => $id) {
            db()->prepare("UPDATE gym_portal_blocks SET sort_order=? WHERE id=? AND gym_id=?")
                ->execute([($i + 1) * 10, (int) $id, $gymId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['error' => 'Acción desconocida']);
    exit;
}

// ── Load current blocks ───────────────────────────────────────────────────────
$stmt = db()->prepare("SELECT * FROM gym_portal_blocks WHERE gym_id=? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$gymId]);
$blocks = $stmt->fetchAll();

layout_header('Portada — Bloques editoriales', 'admin', $user);
nav_section('Admin');
nav_item(BASE_URL . '/pages/admin/dashboard.php', 'Dashboard', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>', 'dashboard', 'gym-portal');
nav_section('CRM');
nav_item(BASE_URL . '/pages/admin/members.php', 'Alumnos', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>', 'members', 'gym-portal');
nav_item(BASE_URL . '/pages/admin/gym-portal.php', 'Portada App', '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', 'gym-portal', 'gym-portal');
layout_footer($user);
?>

<div class="page-header">
    <div>
        <h1 style="font-size:20px;font-weight:700">🖼️ Portada de la App</h1>
        <div style="font-size:12px;color:var(--gf-text-muted)">Contenido editorial que ven los alumnos en la pantalla
            principal</div>
    </div>
    <div class="flex gap-2 ml-auto">
        <button class="btn btn-secondary" onclick="addRichtext()">+ Texto</button>
        <button class="btn btn-primary" onclick="document.getElementById('img-upload-input').click()">+ Imagen</button>
        <input id="img-upload-input" type="file" accept="image/*" style="display:none"
            onchange="uploadImage(this.files[0])">
    </div>
</div>

<div class="page-body">

    <!-- Upload progress -->
    <div id="upload-status"
        style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:10px;background:rgba(0,245,212,.08);border:1px solid rgba(0,245,212,.2);font-size:13px;color:var(--gf-accent)">
        ⏳ Subiendo imagen…
    </div>

    <!-- Blocks list -->
    <div id="blocks-list" style="display:flex;flex-direction:column;gap:12px">
        <?php if (empty($blocks)): ?>
            <div id="empty-state" class="card" style="padding:48px;text-align:center;color:var(--gf-text-muted)">
                <div style="font-size:36px;margin-bottom:12px">🖼️</div>
                <div style="font-size:15px;font-weight:600;margin-bottom:6px">Sin bloques todavía</div>
                <div style="font-size:13px">Agregá una imagen o un texto para que los alumnos vean en la app.</div>
            </div>
        <?php else: ?>
            <?php foreach ($blocks as $b): ?>
                <?php echo renderBlock($b); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Richtext editor modal -->
    <div class="modal-overlay" id="richtext-modal">
        <div class="modal" style="max-width:680px">
            <div class="modal-header">
                <h3 class="modal-title" id="richtext-modal-title">Nuevo bloque de texto</h3>
                <button class="modal-close" onclick="closeRichtextModal()">✕</button>
            </div>
            <div class="modal-body">
                <div id="quill-editor"
                    style="min-height:220px;background:#111;border-radius:8px;color:#f0f0f0;font-size:14px"></div>
                <input type="hidden" id="editing-block-id">
                <div class="flex gap-2 mt-4">
                    <button class="btn btn-secondary flex-1" onclick="closeRichtextModal()">Cancelar</button>
                    <button class="btn btn-primary flex-1" onclick="saveRichtext()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image caption modal -->
    <div class="modal-overlay" id="img-caption-modal">
        <div class="modal" style="max-width:480px">
            <div class="modal-header">
                <h3 class="modal-title">Editar imagen</h3>
                <button class="modal-close"
                    onclick="document.getElementById('img-caption-modal').classList.remove('open')">✕</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom:12px">
                    <img id="img-caption-preview" src="" alt=""
                        style="width:100%;border-radius:8px;object-fit:cover;max-height:200px">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción (opcional)</label>
                    <input type="text" id="img-caption-input" class="input" placeholder="Texto debajo de la imagen…">
                </div>
                <input type="hidden" id="img-caption-block-id">
                <input type="hidden" id="img-caption-content">
                <div class="flex gap-2 mt-4">
                    <button class="btn btn-secondary flex-1"
                        onclick="document.getElementById('img-caption-modal').classList.remove('open')">Cancelar</button>
                    <button class="btn btn-primary flex-1" onclick="saveImgCaption()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Quill CDN -->
<link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<!-- SortableJS for drag-and-drop reorder -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<style>
    .block-card {
        background: var(--gf-surface);
        border: 1px solid var(--gf-border);
        border-radius: 14px;
        overflow: hidden;
        transition: border-color .2s, opacity .2s;
    }

    .block-card.inactive {
        opacity: .55;
    }

    .block-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        background: rgba(255, 255, 255, .03);
        border-bottom: 1px solid var(--gf-border);
        user-select: none;
        cursor: grab;
    }

    .block-card-header:active {
        cursor: grabbing;
    }

    .block-type-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .badge-image {
        background: rgba(99, 102, 241, .15);
        color: #818cf8;
    }

    .badge-richtext {
        background: rgba(0, 245, 212, .1);
        color: #00f5d4;
    }

    .block-preview {
        padding: 14px;
        font-size: 13px;
        color: var(--gf-text-muted);
        max-height: 240px;
        overflow: hidden;
    }

    .block-preview img {
        max-width: 100%;
        border-radius: 6px;
        max-height: 200px;
        object-fit: cover;
    }

    .ql-toolbar.ql-snow {
        background: #1a1a2a;
        border-color: rgba(255, 255, 255, .1);
    }

    .ql-container.ql-snow {
        border-color: rgba(255, 255, 255, .1);
    }

    .ql-snow .ql-stroke {
        stroke: #aaa;
    }

    .ql-snow .ql-fill {
        fill: #aaa;
    }

    .ql-snow .ql-picker {
        color: #aaa;
    }

    .ql-editor {
        color: #f0f0f0;
        min-height: 200px;
    }
</style>

<script>
    const BASE_URL = '<?php echo BASE_URL ?>';
    let quill = null;

    // ── Init Sortable ────────────────────────────────────────────────────────────
    function initSortable() {
        const list = document.getElementById('blocks-list');
        if (!list) return;
        Sortable.create(list, {
            animation: 180,
            handle: '.block-card-header',
            ghostClass: 'sortable-ghost',
            onEnd: async () => {
                const ids = [...list.querySelectorAll('[data-block-id]')].map(el => +el.dataset.blockId);
                await apiFetch('reorder', { ids });
            }
        });
    }
    document.addEventListener('DOMContentLoaded', initSortable);

    // ── API helper ───────────────────────────────────────────────────────────────
    async function apiFetch(action, body) {
        const res = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...body })
        });
        return res.json();
    }

    // ── Image upload ─────────────────────────────────────────────────────────────
    async function uploadImage(file) {
        if (!file) return;

        // Client-side size guard (5 MB) — matches server-side limit
        if (file.size > 5 * 1024 * 1024) {
            alert('La imagen supera 5 MB. Elegí una imagen más pequeña.');
            document.getElementById('img-upload-input').value = '';
            return;
        }

        const status = document.getElementById('upload-status');
        status.style.display = 'block';

        const fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('image', file);

        let data;
        try {
            const res = await fetch(window.location.pathname, { method: 'POST', body: fd });

            // nginx devuelve HTML cuando rechaza con 413 — no intentar JSON.parse
            if (res.status === 413) {
                status.style.display = 'none';
                alert('La imagen es demasiado grande para el servidor. Reducí su tamaño o usá una imagen menor a 5 MB.');
                document.getElementById('img-upload-input').value = '';
                return;
            }

            const text = await res.text();
            try {
                data = JSON.parse(text);
            } catch (_) {
                // Respuesta inesperada del servidor (no es JSON)
                status.style.display = 'none';
                alert('Error inesperado del servidor. Revisá los logs de PHP/nginx.');
                console.error('Respuesta no-JSON del servidor:', text.substring(0, 300));
                return;
            }
        } catch (networkErr) {
            status.style.display = 'none';
            alert('Error de red al subir la imagen. Verificá tu conexión.');
            return;
        }

        status.style.display = 'none';
        if (data.error) { alert(data.error); return; }
        location.reload();
    }

    // ── Richtext ─────────────────────────────────────────────────────────────────
    function addRichtext() {
        document.getElementById('richtext-modal-title').textContent = 'Nuevo bloque de texto';
        document.getElementById('editing-block-id').value = '';
        document.getElementById('richtext-modal').classList.add('open');
        initQuill('');
    }

    function editRichtext(id, content) {
        document.getElementById('richtext-modal-title').textContent = 'Editar texto';
        document.getElementById('editing-block-id').value = id;
        document.getElementById('richtext-modal').classList.add('open');
        initQuill(content);
    }

    function initQuill(html) {
        const el = document.getElementById('quill-editor');
        el.innerHTML = '';
        quill = new Quill(el, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ color: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });
        quill.clipboard.dangerouslyPasteHTML(html || '');
    }

    async function saveRichtext() {
        if (!quill) return;
        const content = quill.root.innerHTML;
        const id = document.getElementById('editing-block-id').value;
        if (id) {
            await apiFetch('update', { id: +id, content });
        } else {
            await apiFetch('add_richtext', { content });
        }
        location.reload();
    }

    function closeRichtextModal() {
        document.getElementById('richtext-modal').classList.remove('open');
    }

    // ── Image caption ─────────────────────────────────────────────────────────────
    function editImgCaption(id, url, caption) {
        document.getElementById('img-caption-block-id').value = id;
        document.getElementById('img-caption-content').value = url;
        document.getElementById('img-caption-input').value = caption;
        document.getElementById('img-caption-preview').src = url;
        document.getElementById('img-caption-modal').classList.add('open');
    }

    async function saveImgCaption() {
        const id = +document.getElementById('img-caption-block-id').value;
        const content = document.getElementById('img-caption-content').value;
        const caption = document.getElementById('img-caption-input').value;
        await apiFetch('update', { id, content, caption });
        location.reload();
    }

    // ── Toggle / Delete ───────────────────────────────────────────────────────────
    async function toggleBlock(id) {
        const card = document.querySelector(`[data-block-id="${id}"]`);
        const res = await apiFetch('toggle', { id });
        if (res.success) {
            card.classList.toggle('inactive', !res.active);
            const btn = card.querySelector('.toggle-btn');
            btn.textContent = res.active ? '● Activo' : '○ Inactivo';
            btn.style.color = res.active ? '#10b981' : '#666';
        }
    }

    async function deleteBlock(id) {
        if (!confirm('¿Eliminar este bloque? Esta acción es irreversible.')) return;
        await apiFetch('delete', { id });
        document.querySelector(`[data-block-id="${id}"]`).remove();
        const list = document.getElementById('blocks-list');
        if (!list.children.length) location.reload();
    }

    // Close modals on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
    });
</script>

<?php
function renderBlock(array $b): string
{
    $id = (int) $b['id'];
    $type = htmlspecialchars($b['type']);
    $content = htmlspecialchars($b['content'], ENT_QUOTES);
    $caption = htmlspecialchars($b['caption'] ?? '', ENT_QUOTES);
    $active = (bool) $b['active'];
    $badgeClass = $type === 'image' ? 'badge-image' : 'badge-richtext';
    $typeName = $type === 'image' ? 'Imagen' : 'Texto';
    $inactiveClass = $active ? '' : ' inactive';
    $toggleLabel = $active ? '● Activo' : '○ Inactivo';
    $toggleColor = $active ? '#10b981' : '#666';

    $preview = $type === 'image'
        ? "<img src=\"{$b['content']}\" alt=\"\" style=\"width:100%;border-radius:6px;object-fit:cover;max-height:200px\">"
        . ($b['caption'] ? "<div style='margin-top:6px;font-style:italic'>" . htmlspecialchars($b['caption']) . "</div>" : '')
        : $b['content']; // raw HTML for richtext

    $editBtn = $type === 'image'
        ? "<button class='btn btn-secondary btn-sm' onclick=\"editImgCaption({$id},'{$content}','{$caption}')\">✏️ Editar</button>"
        : "<button class='btn btn-secondary btn-sm' onclick=\"editRichtext({$id}, document.querySelector('[data-block-id={$id}] .block-preview').innerHTML)\">✏️ Editar</button>";

    return <<<HTML
<div class="block-card{$inactiveClass}" data-block-id="{$id}">
    <div class="block-card-header">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="rgba(255,255,255,.3)" style="flex-shrink:0">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
        </svg>
        <span class="block-type-badge {$badgeClass}">{$typeName}</span>
        <span style="flex:1;font-size:12px;color:var(--gf-text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            Bloque #{$id}
        </span>
        <button class="btn btn-ghost btn-sm toggle-btn" onclick="toggleBlock({$id})" style="color:{$toggleColor};font-size:12px">{$toggleLabel}</button>
        {$editBtn}
        <button class="btn btn-danger btn-sm" onclick="deleteBlock({$id})">✕</button>
    </div>
    <div class="block-preview">{$preview}</div>
</div>
HTML;
}

layout_end();
?>