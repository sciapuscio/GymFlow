<?php
/**
 * GymFlow — Check-in por QR (endpoint mobile)
 *
 * POST /api/checkin.php
 *   header : Authorization: Bearer <member_token>
 *   body   : { gym_qr_token: "uuid-del-qr-de-la-pared" }
 *
 *   → Valida que el miembro pertenece al gym del QR
 *   → Busca la sesión activa (si hay una en curso) o registra sin sesión
 *   → Verifica que el miembro tiene membresía activa con clases disponibles
 *   → Registra en member_attendances (method = 'qr')
 *   → Decrementa sessions_used en member_memberships
 *   → Devuelve { ok, credits_remaining, message }
 *
 * GET /api/checkin.php?gym_qr_token=<uuid>
 *   Sin auth — devuelve info pública del gym para mostrar en la pantalla
 *   de escaneo antes de que el alumno confirme.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

handleCors();
header('Content-Type: application/json; charset=utf-8');

// ── Helper: autenticar alumno por bearer token ───────────────────────────────
function getMemberFromToken(): ?array
{
    $token = getBearerToken();
    if (!$token)
        return null;

    $stmt = db()->prepare("
        SELECT m.*, mat.gym_id AS token_gym_id
        FROM member_auth_tokens mat
        JOIN members m ON m.id = mat.member_id
        WHERE mat.token = ? AND mat.expires_at > NOW() AND m.active = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

// ── GET — info pública del gym (pantalla de escaneo) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gymQr = $_GET['gym_qr_token'] ?? '';
    if (!$gymQr)
        jsonError('gym_qr_token requerido', 400);

    $gym = db()->prepare("SELECT id, name, primary_color, logo_path FROM gyms WHERE qr_token = ? AND active = 1");
    $gym->execute([$gymQr]);
    $gym = $gym->fetch();
    if (!$gym)
        jsonError('QR inválido o gym inactivo', 404);

    // Check if there's a live session right now
    $session = db()->prepare("
        SELECT s.id, s.name, sl.name AS sala_name, s.started_at
        FROM gym_sessions s
        JOIN salas sl ON sl.id = s.sala_id
        WHERE s.gym_id = ? AND s.status = 'live'
        ORDER BY s.started_at DESC LIMIT 1
    ");
    $session->execute([$gym['id']]);
    $liveSession = $session->fetch();

    jsonResponse([
        'gym' => $gym,
        'live_session' => $liveSession ?: null,
        'checkin_open' => true,
    ]);
}

// ── POST — registrar check-in ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Autenticar alumno
    $member = getMemberFromToken();
    if (!$member)
        jsonError('No autorizado. Iniciá sesión en la app.', 401);

    $data = getBody();
    $gymQr = trim($data['gym_qr_token'] ?? '');
    if (!$gymQr)
        jsonError('gym_qr_token requerido', 400);

    // 2. Resolver gym por QR
    $gym = db()->prepare("
        SELECT id, name, checkin_window_minutes
        FROM gyms WHERE qr_token = ? AND active = 1
    ");
    $gym->execute([$gymQr]);
    $gym = $gym->fetch();
    if (!$gym)
        jsonError('QR inválido', 404);
    $gymId = (int) $gym['id'];

    // 3. Verificar que el alumno pertenece a este gym
    if ((int) $member['gym_id'] !== $gymId) {
        jsonError('Este QR no corresponde a tu gimnasio.', 403);
    }

    // 4. Verificar membresía activa con clases disponibles
    $ms = db()->prepare("
        SELECT id, sessions_used, sessions_limit, end_date, plan_id
        FROM member_memberships
        WHERE member_id = ? AND gym_id = ? AND end_date >= CURDATE()
        ORDER BY end_date DESC LIMIT 1
    ");
    $ms->execute([$member['id'], $gymId]);
    $membership = $ms->fetch();

    if (!$membership)
        jsonError('No tenés una membresía activa.', 403);

    $sessionsLimit = (int) $membership['sessions_limit'];
    $sessionsUsed = (int) $membership['sessions_used'];

    if ($sessionsLimit > 0 && $sessionsUsed >= $sessionsLimit) {
        jsonError('Agotaste tus clases disponibles. Contactá al gym para renovar.', 403);
    }

    // 5. Buscar sesión activa (si hay)
    $session = db()->prepare("
        SELECT id FROM gym_sessions
        WHERE gym_id = ? AND status = 'live'
        ORDER BY started_at DESC LIMIT 1
    ");
    $session->execute([$gymId]);
    $sessionId = ($session->fetch())['id'] ?? null;

    // 6. Evitar doble check-in en el mismo día / misma sesión
    if ($sessionId) {
        $dup = db()->prepare("
            SELECT id FROM member_attendances
            WHERE member_id = ? AND gym_session_id = ?
        ");
        $dup->execute([$member['id'], $sessionId]);
    } else {
        // Sin sesión activa: prevenir más de 1 check-in por día
        $dup = db()->prepare("
            SELECT id FROM member_attendances
            WHERE member_id = ? AND gym_id = ? AND DATE(checked_in_at) = CURDATE()
        ");
        $dup->execute([$member['id'], $gymId]);
    }
    if ($dup->fetch())
        jsonError('Ya registraste tu presencia hoy.', 409);

    // 6b. Validar ventana de check-in: debe haber una clase de hoy que empiece
    //     en los próximos N minutos o que ya haya comenzado (y no terminado).
    $windowMin = (int) ($gym['checkin_window_minutes'] ?? 30);
    $windowOpen = db()->prepare("
        SELECT ss.start_time, ss.end_time
        FROM member_reservations mr
        JOIN schedule_slots ss ON ss.id = mr.schedule_slot_id
        WHERE mr.member_id  = ?
          AND mr.gym_id     = ?
          AND mr.class_date  = CURDATE()
          AND mr.status      = 'reserved'
          AND ADDTIME(NOW(), SEC_TO_TIME(? * 60)) >= CONCAT(CURDATE(), ' ', ss.start_time)
          AND NOW() < CONCAT(CURDATE(), ' ', ss.end_time)
        LIMIT 1
    ");
    $windowOpen->execute([$member['id'], $gymId, $windowMin]);
    $upcomingClass = $windowOpen->fetch();

    if (!$upcomingClass) {
        // Intentar dar info útil: próxima clase del día
        $nextClass = db()->prepare("
            SELECT ss.start_time
            FROM member_reservations mr
            JOIN schedule_slots ss ON ss.id = mr.schedule_slot_id
            WHERE mr.member_id  = ?
              AND mr.gym_id     = ?
              AND mr.class_date  = CURDATE()
              AND mr.status      = 'reserved'
              AND CONCAT(CURDATE(), ' ', ss.start_time) > NOW()
            ORDER BY ss.start_time ASC
            LIMIT 1
        ");
        $nextClass->execute([$member['id'], $gymId]);
        $next = $nextClass->fetch();

        if ($next) {
            $openAt = date('H:i', strtotime($next['start_time']) - $windowMin * 60);
            jsonError("El check-in para tu próxima clase abre a las {$openAt}.", 403);
        } else {
            jsonError('No tenés ninguna clase reservada en este momento. Reservá una clase desde la Grilla.', 403);
        }
    }

    // 7. Registrar asistencia
    db()->prepare("
        INSERT INTO member_attendances
            (member_id, gym_session_id, gym_id, membership_id, method)
        VALUES (?,?,?,?,?)
    ")->execute([
                $member['id'],
                $sessionId,
                $gymId,
                $membership['id'],
                'qr',
            ]);

    // 7b. Marcar la reserva de hoy como 'attended' (si existe)
    db()->prepare("
        UPDATE member_reservations
        SET status = 'attended'
        WHERE member_id = ? AND gym_id = ? AND class_date = CURDATE() AND status = 'reserved'
    ")->execute([$member['id'], $gymId]);

    // 8. Incrementar sessions_used
    db()->prepare("
        UPDATE member_memberships SET sessions_used = sessions_used + 1 WHERE id = ?
    ")->execute([$membership['id']]);

    $remaining = $sessionsLimit > 0
        ? $sessionsLimit - $sessionsUsed - 1
        : null;

    jsonResponse([
        'ok' => true,
        'message' => '¡Presente registrado! Buen entrenamiento 💪',
        'credits_remaining' => $remaining,  // null = plan ilimitado
        'checked_in_at' => date('Y-m-d H:i:s'),
        'session_id' => $sessionId,
        'gym_name' => $gym['name'],
    ]);
}

jsonError('Método no permitido', 405);
