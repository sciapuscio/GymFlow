-- GymFlow — Push Notification Log
-- Run once on local and production

CREATE TABLE IF NOT EXISTS push_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id     INT UNSIGNED NOT NULL,
    title      VARCHAR(100)  NOT NULL,
    body       TEXT          NOT NULL,
    sent       INT UNSIGNED  NOT NULL DEFAULT 0,
    failed     INT UNSIGNED  NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gym (gym_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
