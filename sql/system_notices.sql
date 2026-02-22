-- GymFlow: System Notices
-- Run once: CREATE TABLE IF NOT EXISTS to support persistent banners shown on all pages.

CREATE TABLE IF NOT EXISTS system_notices (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message    TEXT NOT NULL,
    type       ENUM('info', 'warning', 'error') NOT NULL DEFAULT 'warning',
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,           -- user_id of the superadmin
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
