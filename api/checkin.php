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

// ── GET — info pública del gym (pantalla de escaneo) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $qrToken = $_GET['gym_qr_token'] ?? '';
    if (!$qrToken)
        jsonError('gym_qr_token requerido', 400);

    // Try sede first, then gym
    $sedeStmt = db()->prepare("SELECT s.id AS sede_id, s.name AS sede_name, g.id, g.name, g.primary_color, g.logo_path FROM sedes s JOIN gyms g ON g.id = s.gym_id WHERE s.qr_token = ? AND s.active = 1 AND g.active = 1");
    $sedeStmt->execute([$qrToken]);
    $sedeRow = $sedeStmt->fetch();

    if ($sedeRow) {
        $gym = ['id' => $sedeRow['id'], 'name' => $sedeRow['name'], 'primary_color' => $sedeRow['primary_color'], 'logo_path' => $sedeRow['logo_path']];
        $sedeId = $sedeRow['sede_id'];
        $sedeName = $sedeRow['sede_name'];
    } else {
        $gymStmt = db()->prepare("SELECT id, name, primary_color, logo_path FROM gyms WHERE qr_token = ? AND active = 1");
        $gymStmt->execute([$qrToken]);
        $gym = $gymStmt->fetch();
        if (!$gym)
            jsonError('QR inválido o gym inactivo', 404);
        $sedeId = null;
        $sedeName = null;
    }

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
        'sede_id' => $sedeId,
        'sede_name' => $sedeName,
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
    $qrToken = trim($data['gym_qr_token'] ?? '');
    if (!$qrToken)
        jsonError('gym_qr_token requerido', 400);

    // 2. Resolver QR — puede ser de sede o de gym
    $sedeId = null;
    $sedeStmt = db()->prepare("SELECT s.id AS sede_id, g.id AS gym_id, g.name, g.checkin_window_minutes FROM sedes s JOIN gyms g ON g.id = s.gym_id WHERE s.qr_token = ? AND s.active = 1 AND g.active = 1");
    $sedeStmt->execute([$qrToken]);
    $sedeRow = $sedeStmt->fetch();

    if ($sedeRow) {
        $sedeId = (int) $sedeRow['sede_id'];
        $gym = ['id' => $sedeRow['gym_id'], 'name' => $sedeRow['name'], 'checkin_window_minutes' => $sedeRow['checkin_window_minutes'] ?? 30];
    } else {
        $gymStmt = db()->prepare("SELECT id, name, checkin_window_minutes FROM gyms WHERE qr_token = ? AND active = 1");
        $gymStmt->execute([$qrToken]);
        $gym = $gymStmt->fetch();
        if (!$gym)
            jsonError('QR inválido', 404);
    }
    $gymId = (int) $gym['id'];

    // 3. Verificar que el alumno pertenece a este gym
    if ((int) $member['gym_id'] !== $gymId) {
        jsonError('Este QR no corresponde a tu gimnasio.', 403);
    }

    // 4. Verificar membresía activa
    $ms = db()->prepare("
        SELECT id, sessions_used, sessions_limit, end_date, plan_id, all_sedes
        FROM member_memberships
        WHERE member_id = ? AND gym_id = ? AND end_date >= CURDATE()
        ORDER BY end_date DESC LIMIT 1
    ");
    $ms->execute([$member['id'], $gymId]);
    $membership = $ms->fetch();
    if (!$membership)
        jsonError('No tenés una membresía activa.', 403);

    // 4b. Validar acceso a sede (si el QR es de una sede y la membresía no es global)
    if ($sedeId && !(int) ($membership['all_sedes'] ?? 1)) {
        $sedeAccess = db()->prepare("
            SELECT id FROM member_membership_sedes
            WHERE membership_id = ? AND sede_id = ?
        ");
        $sedeAccess->execute([$membership['id'], $sedeId]);
        if (!$sedeAccess->fetch())
            jsonError('Tu membresía no está habilitada para esta sede.', 403);
    }

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

    // 6. Check duplicate per gym_session (if a live session is active)
    if ($sessionId) {
        $dup = db()->prepare("
            SELECT id FROM member_attendances
            WHERE member_id = ? AND gym_session_id = ?
        ");
        $dup->execute([$member['id'], $sessionId]);
        if ($dup->fetch())
            jsonError('Ya registraste tu presencia en esta sesión.', 409);
    }

    // 6b. Validar ventana de check-in: debe haber una clase de hoy que empiece
    //     en los próximos N minutos o que ya haya comenzado (y no terminado).
    //     Si el QR es de una sede, solo vale la clase de esa sede.
    $windowMin = (int) ($gym['checkin_window_minutes'] ?? 30);
    $sedeFilter = $sedeId ? ' AND mr.sede_id = ?' : '';
    $windowOpen = db()->prepare("
        SELECT mr.id AS reservation_id, ss.start_time, ss.end_time
        FROM member_reservations mr
        JOIN schedule_slots ss ON ss.id = mr.schedule_slot_id
        WHERE mr.member_id  = ?
          AND mr.gym_id     = ?
          AND mr.class_date  = CURDATE()
          AND mr.status      = 'reserved'
          AND ADDTIME(NOW(), SEC_TO_TIME(? * 60)) >= CONCAT(CURDATE(), ' ', ss.start_time)
          AND NOW() < CONCAT(CURDATE(), ' ', ss.end_time)
          {$sedeFilter}
        LIMIT 1
    ");
    $params = [$member['id'], $gymId, $windowMin];
    if ($sedeId)
        $params[] = $sedeId;
    $windowOpen->execute($params);
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
              {$sedeFilter}
            ORDER BY ss.start_time ASC
            LIMIT 1
        ");
        $params2 = [$member['id'], $gymId];
        if ($sedeId)
            $params2[] = $sedeId;
        $nextClass->execute($params2);
        $next = $nextClass->fetch();

        if ($next) {
            $openAt = date('H:i', strtotime($next['start_time']) - $windowMin * 60);
            jsonError("El check-in para tu próxima clase abre a las {$openAt}.", 403);
        } else {
            $msg = $sedeId
                ? 'No tenés ninguna clase reservada en esta sede en este momento.'
                : 'No tenés ninguna clase reservada en este momento. Reservá una clase desde la Grilla.';
            jsonError($msg, 403);
        }
    }

    // 6c. Evitar doble check-in en la MISMA CLASE (por si el alumno escanea dos veces)
    //     Cada clase es independiente — un alumno puede tener múltiples clases en el día.
    $alreadyDone = db()->prepare("
        SELECT id FROM member_reservations
        WHERE id = ? AND status IN ('present', 'attended')
    ");
    $alreadyDone->execute([$upcomingClass['reservation_id']]);
    if ($alreadyDone->fetch())
        jsonError('Ya registraste tu asistencia a esta clase.', 409);

    // 7. Registrar asistencia
    db()->prepare("
        INSERT INTO member_attendances
            (member_id, gym_session_id, gym_id, sede_id, membership_id, method)
        VALUES (?,?,?,?,?,?)
    ")->execute([
                $member['id'],
                $sessionId,
                $gymId,
                $sedeId,
                $membership['id'],
                'qr',
            ]);

    // 7b. Marcar la reserva como 'present' (la específica que habilitó el checkin)
    db()->prepare("
        UPDATE member_reservations
        SET status = 'present'
        WHERE id = ?
    ")->execute([$upcomingClass['reservation_id']]);

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
