-- GymFlow — Support / Helpdesk tables
-- Run once: Get-Content sql/support_migration.sql | mysql -u root gymflow

CREATE TABLE IF NOT EXISTS support_tickets (
    id          INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id      INT(10) UNSIGNED NOT NULL,
    created_by  INT(10) UNSIGNED NOT NULL,
    subject     VARCHAR(200) NOT NULL,
    status      ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    priority    ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gym    (gym_id),
    INDEX idx_status (status),
    FOREIGN KEY (gym_id)     REFERENCES gyms(id)  ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
    id          INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT(10) UNSIGNED NOT NULL,
    user_id     INT(10) UNSIGNED NOT NULL,
    message     TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
