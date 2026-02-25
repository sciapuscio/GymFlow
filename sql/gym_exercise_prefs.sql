-- GymFlow: gym_exercise_prefs
-- Stores per-gym overrides for exercises.
-- "No row" = ai_enabled (default). A row is inserted only when a gym disables an exercise for AI.

CREATE TABLE IF NOT EXISTS gym_exercise_prefs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id      INT UNSIGNED NOT NULL,
    exercise_id INT UNSIGNED NOT NULL,
    ai_enabled  TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gym_ex (gym_id, exercise_id),
    FOREIGN KEY (gym_id)      REFERENCES gyms(id)      ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB;
