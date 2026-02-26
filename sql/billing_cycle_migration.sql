-- Agregar columna billing_cycle a gym_subscriptions
-- Valores: 'monthly' (por defecto) | 'annual'
ALTER TABLE gym_subscriptions
    ADD COLUMN billing_cycle ENUM('monthly', 'annual') NOT NULL DEFAULT 'monthly'
    AFTER plan;
