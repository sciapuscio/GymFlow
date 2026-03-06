-- GymFlow — Session Sharing Module Migration
-- Compatible with MySQL 5.7+
-- Run once on local and production
-- NOTE: If columns already exist, comment out the ALTER TABLE lines.

-- 1. Add sharing fields to gym_sessions
ALTER TABLE gym_sessions
  ADD COLUMN shared            TINYINT(1) NOT NULL DEFAULT 0  AFTER name,
  ADD COLUMN share_description TEXT       NULL                AFTER shared;

-- 2. Instructor client roster
--    client_member_id → GymFlow mobile member (NULL if external/pending)
--    client_email     → always set; used to auto-link when member registers
CREATE TABLE IF NOT EXISTS instructor_clients (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gym_id           INT UNSIGNED  NOT NULL,
  client_member_id INT UNSIGNED  NULL,
  client_email     VARCHAR(191)  NOT NULL,
  client_name      VARCHAR(100)  NOT NULL,
  status           ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_instructor_client (gym_id, client_email),
  KEY idx_member (client_member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Per-session access grants
CREATE TABLE IF NOT EXISTS session_access_grants (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id  INT UNSIGNED NOT NULL,
  client_id   INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grant (session_id, client_id),
  KEY idx_session (session_id),
  KEY idx_client  (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
