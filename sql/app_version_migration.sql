-- ── App Version Config ─────────────────────────────────────────────────────
-- Stores global minimum app version required. Only one row (id=1).
CREATE TABLE IF NOT EXISTS app_config (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    `key`         VARCHAR(100) UNIQUE NOT NULL,
    `value`       VARCHAR(512)        NOT NULL DEFAULT '',
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed initial values
INSERT INTO app_config (`key`, `value`) VALUES
    ('min_app_version', '1.0.0'),
    ('android_store_url', 'https://play.google.com/store/apps/details?id=com.gymflow.client'),
    ('ios_store_url',     'https://apps.apple.com/app/gymflow/xxxxx')
ON DUPLICATE KEY UPDATE `key` = `key`;
