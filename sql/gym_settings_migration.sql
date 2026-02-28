-- gym_settings table: generic key-value config per gym
CREATE TABLE IF NOT EXISTS gym_settings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id      INT UNSIGNED NOT NULL,
    setting_key VARCHAR(64)  NOT NULL,
    setting_value TEXT        NULL,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gym_setting (gym_id, setting_key),
    KEY idx_gym_key (gym_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default checkin window (15 min) — insert only if not already set
INSERT IGNORE INTO gym_settings (gym_id, setting_key, setting_value)
SELECT id, 'checkin_window_minutes', '15' FROM gyms WHERE active = 1;
