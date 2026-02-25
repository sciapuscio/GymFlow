<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET — listar slots del gym ───────────────────────────────────────────────
if ($method === 'GET') {
    $user = requireAuth('instructor', 'admin', 'superadmin');
    $gymId = (int) $user['gym_id'];

    $stmt = db()->prepare(
        "SELECT ss.*, ss.label AS class_name, s.name AS sala_name
         FROM   schedule_slots ss
         LEFT JOIN salas s ON ss.sala_id = s.id
         WHERE  ss.gym_id = ?
         ORDER  BY ss.day_of_week, ss.start_time"
    );
    $stmt->execute([$gymId]);
    jsonResponse($stmt->fetchAll());
}

// ── POST — crear slot ────────────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireAuth('instructor', 'admin', 'superadmin');
    $gymId = (int) $user['gym_id'];
    $data = getBody();

    // Validaciones básicas
    if (!isset($data['day_of_week']) || !isset($data['start_time']) || !isset($data['end_time'])) {
        jsonError('Faltan campos obligatorios: day_of_week, start_time, end_time');
    }
    if (empty($data['sala_id'])) {
        jsonError('sala_id requerido');
    }

    // Verificar que la sala pertenece al gym
    $stmtCheck = db()->prepare("SELECT id FROM salas WHERE id = ? AND gym_id = ? AND active = 1");
    $stmtCheck->execute([(int) $data['sala_id'], $gymId]);
    if (!$stmtCheck->fetch()) {
        jsonError('Sala no encontrada o sin permisos', 403);
    }

    // Aceptar class_name o label indistintamente
    $label = trim($data['class_name'] ?? $data['label'] ?? '');

    db()->prepare(
        "INSERT INTO schedule_slots
            (gym_id, sala_id, instructor_id, day_of_week, start_time, end_time, label, recurrent)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    )->execute([
                $gymId,
                (int) $data['sala_id'],
                $user['id'],
                (int) $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $label ?: null,
            ]);

    $newId = (int) db()->lastInsertId();

    // Notify agenda displays BEFORE exit
    @file_get_contents('http://localhost:3001/internal/schedule-updated?gym_id=' . $gymId);

    jsonResponse(['id' => $newId, 'success' => true], 201);
}

// ── DELETE — eliminar slot ───────────────────────────────────────────────────
if ($method === 'DELETE' && $id) {
    $user = requireAuth('instructor', 'admin', 'superadmin');
    $gymId = (int) $user['gym_id'];

    db()->prepare("DELETE FROM schedule_slots WHERE id = ? AND gym_id = ?")
        ->execute([$id, $gymId]);

    // Notify agenda displays BEFORE exit
    @file_get_contents('http://localhost:3001/internal/schedule-updated?gym_id=' . $gymId);

    jsonResponse(['success' => true]);
}

jsonError('Método o parámetros no soportados', 405);
