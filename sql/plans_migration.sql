-- GymFlow — Business Model Migration
-- Run once against the gymflow database
-- Safe to run on existing data; uses ALTER IGNORE / IF NOT EXISTS patterns

USE gymflow;

-- 1. Add new plan values to gym_subscriptions.plan ENUM
--    MySQL requires re-specifying the full column definition
ALTER TABLE gym_subscriptions
    MODIFY COLUMN plan ENUM(
        'trial',
        'instructor',
        'gimnasio',
        'centro',
        'monthly',
        'annual'
    ) NOT NULL DEFAULT 'trial';

-- 2. Add extra_salas add-on column
ALTER TABLE gym_subscriptions
    ADD COLUMN IF NOT EXISTS extra_salas TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Paid add-on: extra salas beyond base plan limit';

-- 3. Add price reference column (informational, not enforced server-side)
ALTER TABLE gym_subscriptions
    ADD COLUMN IF NOT EXISTS price_ars INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Monthly price in ARS for billing reference';

-- 4. Migrate existing 'monthly' / 'annual' rows to 'gimnasio' as best-effort default
--    (Superadmin should review and correct per gym after migration)
UPDATE gym_subscriptions
SET plan = 'gimnasio'
WHERE plan IN ('monthly', 'annual');

-- 5. Update price_ars for existing rows based on plan
UPDATE gym_subscriptions SET price_ars = 0      WHERE plan = 'trial';
UPDATE gym_subscriptions SET price_ars = 12000  WHERE plan = 'instructor';
UPDATE gym_subscriptions SET price_ars = 29000  WHERE plan = 'gimnasio';
UPDATE gym_subscriptions SET price_ars = 55000  WHERE plan = 'centro';
