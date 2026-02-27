-- Migration: add device tokens table + notified_30min flag
-- Run once on both local XAMPP and production

-- 1. Device tokens table
CREATE TABLE IF NOT EXISTS `member_device_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`  INT UNSIGNED NOT NULL,
  `fcm_token`  VARCHAR(512) NOT NULL,
  `platform`   VARCHAR(10)  NOT NULL DEFAULT 'android',
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_token` (`member_id`, `fcm_token`),
  CONSTRAINT `fk_mdt_member`
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Flag to prevent re-sending the 30-min reminder
ALTER TABLE `member_reservations`
  ADD COLUMN `notified_30min` TINYINT(1) NOT NULL DEFAULT 0;
