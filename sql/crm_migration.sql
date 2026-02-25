-- GymFlow CRM Module — Migration
-- Run once against the gymflow database.
-- Adds: members, membership_plans, member_memberships, member_attendances

USE gymflow;

-- ============================================================
-- MEMBERS (alumnos del gym — distinto de users = staff)
-- ============================================================

CREATE TABLE IF NOT EXISTS members (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id        INT UNSIGNED NOT NULL,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) DEFAULT NULL,
    phone         VARCHAR(30)  DEFAULT NULL,
    birth_date    DATE         DEFAULT NULL,
    avatar_path   VARCHAR(255) DEFAULT NULL,
    notes         TEXT         DEFAULT NULL,
    active        TINYINT(1)   DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
    INDEX idx_members_gym (gym_id),
    INDEX idx_members_email (email)
) ENGINE=InnoDB;

-- ============================================================
-- MEMBERSHIP PLANS (planes que ofrece el gym)
-- ============================================================

CREATE TABLE IF NOT EXISTS membership_plans (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id         INT UNSIGNED NOT NULL,
    name           VARCHAR(100) NOT NULL,
    description    TEXT         DEFAULT NULL,
    price          DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency       VARCHAR(3)   DEFAULT 'ARS',
    duration_days  SMALLINT     NOT NULL DEFAULT 30 COMMENT 'duración en días del período',
    sessions_limit SMALLINT     DEFAULT NULL COMMENT 'NULL = ilimitado',
    active         TINYINT(1)   DEFAULT 1,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
    INDEX idx_plans_gym (gym_id)
) ENGINE=InnoDB;

-- ============================================================
-- MEMBER MEMBERSHIPS (instancia de alumno + plan)
-- ============================================================

CREATE TABLE IF NOT EXISTS member_memberships (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id         INT UNSIGNED NOT NULL,
    member_id      INT UNSIGNED NOT NULL,
    plan_id        INT UNSIGNED DEFAULT NULL COMMENT 'NULL = plan libre/manual',
    start_date     DATE         NOT NULL,
    end_date       DATE         NOT NULL,
    sessions_used  SMALLINT     DEFAULT 0,
    sessions_limit SMALLINT     DEFAULT NULL COMMENT 'copied from plan at time of creation',
    amount_due     DECIMAL(10,2) DEFAULT 0,
    amount_paid    DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('pending','paid','partial','overdue') DEFAULT 'pending',
    payment_date   DATE         DEFAULT NULL,
    payment_method VARCHAR(50)  DEFAULT NULL COMMENT 'efectivo, transferencia, MP, etc.',
    notes          TEXT         DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id)    REFERENCES gyms(id)            ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id)         ON DELETE CASCADE,
    FOREIGN KEY (plan_id)   REFERENCES membership_plans(id) ON DELETE SET NULL,
    INDEX idx_memberships_gym    (gym_id),
    INDEX idx_memberships_member (member_id),
    INDEX idx_memberships_status (payment_status),
    INDEX idx_memberships_end    (end_date)
) ENGINE=InnoDB;

-- ============================================================
-- MEMBER ATTENDANCES (asistencia por clase)
-- ============================================================

CREATE TABLE IF NOT EXISTS member_attendances (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id          INT UNSIGNED NOT NULL,
    member_id       INT UNSIGNED NOT NULL,
    membership_id   INT UNSIGNED DEFAULT NULL COMMENT 'membresía activa al momento del check-in',
    gym_session_id  INT UNSIGNED DEFAULT NULL COMMENT 'sesión en vivo a la que asistió',
    checked_in_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    method          ENUM('manual','qr') DEFAULT 'manual',
    notes           VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (gym_id)         REFERENCES gyms(id)         ON DELETE CASCADE,
    FOREIGN KEY (member_id)      REFERENCES members(id)      ON DELETE CASCADE,
    FOREIGN KEY (membership_id)  REFERENCES member_memberships(id) ON DELETE SET NULL,
    FOREIGN KEY (gym_session_id) REFERENCES gym_sessions(id) ON DELETE SET NULL,
    INDEX idx_attendances_gym     (gym_id),
    INDEX idx_attendances_member  (member_id),
    INDEX idx_attendances_session (gym_session_id),
    INDEX idx_attendances_date    (checked_in_at)
) ENGINE=InnoDB;
