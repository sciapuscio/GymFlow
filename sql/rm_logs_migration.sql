-- RM Calculator — Historial de cargas por ejercicio
-- Run once on DB

CREATE TABLE IF NOT EXISTS rm_logs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id      INT UNSIGNED NOT NULL,
    gym_id         INT UNSIGNED NOT NULL,
    session_id     INT UNSIGNED NULL,          -- gym_sessions.id (NULL si es entrada manual)
    exercise_id    INT UNSIGNED NULL,          -- exercises.id (NULL si nombre custom)
    exercise_name  VARCHAR(120) NOT NULL,
    weight_kg      DECIMAL(6,2) NOT NULL,
    reps           TINYINT UNSIGNED NOT NULL,
    rm_estimated   DECIMAL(6,2) NOT NULL,      -- Brzycki: weight × (36 / (37 - reps))
    logged_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member_exercise (member_id, exercise_name, logged_at),
    INDEX idx_member_session  (member_id, session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
