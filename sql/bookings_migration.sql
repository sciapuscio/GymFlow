-- GymFlow — Bookings Migration
-- Ejecutar en producción ANTES de desplegar la feature de reservas.
-- Safe to run multiple times (all statements use IF NOT EXISTS / IF NOT EXISTS check).

USE gymflow;

-- ── 1. Tabla de reservas (ya definida en mobile_checkin_migration.sql) ─────────
-- CREATE TABLE IF NOT EXISTS por si no fue ejecutada antes.
CREATE TABLE IF NOT EXISTS member_reservations (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id           INT UNSIGNED NOT NULL,
    member_id        INT UNSIGNED NOT NULL,
    schedule_slot_id INT UNSIGNED DEFAULT NULL COMMENT 'slot semanal recurrente',
    class_date       DATE NOT NULL               COMMENT 'fecha concreta de la clase',
    class_time       TIME NOT NULL               COMMENT 'horario copiado del slot',
    status           ENUM('reserved','attended','cancelled','absent') DEFAULT 'reserved',
    cancel_deadline  TIMESTAMP NULL              COMMENT 'hasta cuándo puede cancelar',
    cancelled_at     TIMESTAMP NULL,
    cancel_reason    VARCHAR(100) DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id)           REFERENCES gyms(id)           ON DELETE CASCADE,
    FOREIGN KEY (member_id)        REFERENCES members(id)         ON DELETE CASCADE,
    FOREIGN KEY (schedule_slot_id) REFERENCES schedule_slots(id) ON DELETE SET NULL,
    UNIQUE KEY no_dup_reservation  (member_id, schedule_slot_id, class_date),
    INDEX idx_res_gym    (gym_id),
    INDEX idx_res_member (member_id),
    INDEX idx_res_date   (class_date),
    INDEX idx_res_status (status)
) ENGINE=InnoDB;

-- ── 2. Capacidad por slot (nueva columna en schedule_slots) ──────────────────
-- NULL = sin límite de participantes.
-- Nota: no usar IF NOT EXISTS — solo ejecutar una vez.
ALTER TABLE schedule_slots
    ADD COLUMN capacity SMALLINT DEFAULT NULL
    COMMENT 'Máximo de alumnos por clase. NULL = sin límite';

-- ── Verificación post-migración ───────────────────────────────────────────────
-- Ejecutar para confirmar que todo quedó bien:
-- SHOW COLUMNS FROM schedule_slots LIKE 'capacity';
-- SHOW CREATE TABLE member_reservations;
