-- GymFlow — Mobile Check-in Foundation Migration
-- Adds: gym QR token, member auth tokens, member reservations

USE gymflow;

-- ── 1. QR token único por gym (se imprime en la pared) ───────────────────────
-- El QR codifica: https://app.gymflow.com.ar/checkin?gym=<qr_token>
-- Es estático y se puede regenerar desde el admin.

ALTER TABLE gyms
    ADD COLUMN IF NOT EXISTS qr_token CHAR(36) UNIQUE DEFAULT NULL COMMENT 'UUID imprimible para check-in por QR';

-- Asigna UUID a los gyms que no tienen token aún
UPDATE gyms SET qr_token = UUID() WHERE qr_token IS NULL;

-- ── 2. Auth tokens para alumnos (Flutter app) ────────────────────────────────
-- Distinto de sessions_auth que es solo para staff.
-- El alumno hace login con email+password → recibe un bearer token.
-- La app lo guarda localmente y lo envía en cada request.

CREATE TABLE IF NOT EXISTS member_auth_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id   INT UNSIGNED NOT NULL,
    gym_id      INT UNSIGNED NOT NULL,
    token       VARCHAR(128) NOT NULL UNIQUE,
    device_name VARCHAR(100) DEFAULT NULL COMMENT 'iPhone de Juan / Flutter SDK',
    expires_at  TIMESTAMP NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id)  ON DELETE CASCADE,
    FOREIGN KEY (gym_id)    REFERENCES gyms(id)      ON DELETE CASCADE,
    INDEX idx_member_tokens_token     (token),
    INDEX idx_member_tokens_member    (member_id),
    INDEX idx_member_tokens_expires   (expires_at)
) ENGINE=InnoDB;

-- ── 3. Credenciales de alumno (email + password) ─────────────────────────────
-- Solo para alumnos que quieran usar la app.
-- No todos los members necesitan credenciales (pueden ser solo registros del admin).

ALTER TABLE members
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL COMMENT 'NULL = no tiene acceso a la app',
    ADD COLUMN IF NOT EXISTS qr_token      CHAR(36) UNIQUE DEFAULT NULL COMMENT 'QR personal del alumno (alternativa al QR del gym)';

-- Asigna QR personal a alumnos actuales
UPDATE members SET qr_token = UUID() WHERE qr_token IS NULL;

-- ── 4. Reservas de clases ────────────────────────────────────────────────────
-- Permite que el alumno reserve/cancele clases desde la app.
-- cancel_deadline = horario de clase - X minutos (configurable por gym).

CREATE TABLE IF NOT EXISTS member_reservations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id           INT UNSIGNED NOT NULL,
    member_id        INT UNSIGNED NOT NULL,
    schedule_slot_id INT UNSIGNED DEFAULT NULL COMMENT 'slot semanal recurrente',
    class_date       DATE NOT NULL COMMENT 'fecha concreta de la clase',
    class_time       TIME NOT NULL COMMENT 'hora de la clase (copiada del slot)',
    status           ENUM('reserved','attended','cancelled','absent') DEFAULT 'reserved',
    cancel_deadline  TIMESTAMP NULL COMMENT 'hasta cuándo puede cancelar sin penalidad',
    cancelled_at     TIMESTAMP NULL,
    cancel_reason    VARCHAR(100) DEFAULT NULL,
    attendance_id    INT UNSIGNED DEFAULT NULL COMMENT 'FK a member_attendances al confirmar presencia',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id)           REFERENCES gyms(id)            ON DELETE CASCADE,
    FOREIGN KEY (member_id)        REFERENCES members(id)          ON DELETE CASCADE,
    FOREIGN KEY (schedule_slot_id) REFERENCES schedule_slots(id)  ON DELETE SET NULL,
    FOREIGN KEY (attendance_id)    REFERENCES member_attendances(id) ON DELETE SET NULL,
    UNIQUE KEY no_dup_reservation  (member_id, schedule_slot_id, class_date),
    INDEX idx_reservations_gym     (gym_id),
    INDEX idx_reservations_member  (member_id),
    INDEX idx_reservations_date    (class_date),
    INDEX idx_reservations_status  (status)
) ENGINE=InnoDB;

-- ── 5. Config de check-in por gym ───────────────────────────────────────────
-- Guarda cuántos minutos antes de la clase cierra el check-in,
-- y cuántos minutos antes es el limite para cancelar sin penalidad.

ALTER TABLE gyms
    ADD COLUMN IF NOT EXISTS checkin_window_minutes   SMALLINT DEFAULT 30  COMMENT 'minutos antes de la clase en que el QR está activo',
    ADD COLUMN IF NOT EXISTS cancel_cutoff_minutes    SMALLINT DEFAULT 120 COMMENT 'minutos antes de la clase límite para cancelar sin ausencia';
