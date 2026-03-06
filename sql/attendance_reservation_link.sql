-- GymFlow — Link member_attendances to member_reservations
-- Run once against the gymflow database.

USE gymflow;

ALTER TABLE member_attendances
    ADD COLUMN reservation_id INT UNSIGNED DEFAULT NULL
        COMMENT 'reserva que habilitó el check-in (member_reservations.id)'
        AFTER gym_session_id,
    ADD CONSTRAINT fk_attendance_reservation
        FOREIGN KEY (reservation_id) REFERENCES member_reservations(id)
        ON DELETE SET NULL;

CREATE INDEX idx_attendances_reservation ON member_attendances(reservation_id);
