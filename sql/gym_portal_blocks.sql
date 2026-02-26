-- GymFlow — Portada editorial blocks
-- Run this migration once in your database.

CREATE TABLE IF NOT EXISTS gym_portal_blocks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    gym_id      INT NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    type        ENUM('image','richtext') NOT NULL,
    content     LONGTEXT,          -- image URL or HTML string
    caption     VARCHAR(500),      -- optional subtitle for images
    active      TINYINT(1)  NOT NULL DEFAULT 1,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gym_order (gym_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
