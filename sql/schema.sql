-- GymFlow Database Schema
-- MySQL 8.0+ / PHP 8.2+

CREATE DATABASE IF NOT EXISTS gymflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymflow;

-- ============================================================
-- CORE: Multi-tenant Gym Structure
-- ============================================================

CREATE TABLE gyms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    logo_path VARCHAR(255) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#00f5d4',
    secondary_color VARCHAR(7) DEFAULT '#ff6b35',
    font_family VARCHAR(60) DEFAULT 'Inter',
    font_display VARCHAR(60) DEFAULT 'Bebas Neue',
    spotify_mode ENUM('gym','instructor','disabled') DEFAULT 'disabled',
    spotify_client_id VARCHAR(100) DEFAULT NULL,
    spotify_client_secret VARCHAR(100) DEFAULT NULL,
    default_equipment_json JSON DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE salas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    display_code VARCHAR(20) NOT NULL UNIQUE,
    logo_path VARCHAR(255) DEFAULT NULL,
    bg_color VARCHAR(7) DEFAULT NULL,
    accent_color VARCHAR(7) DEFAULT NULL,
    equipment_json JSON DEFAULT NULL,
    current_session_id INT UNSIGNED DEFAULT NULL,
    last_sync_at TIMESTAMP NULL DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- USERS & AUTH
-- ============================================================

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','instructor') NOT NULL DEFAULT 'instructor',
    avatar_path VARCHAR(255) DEFAULT NULL,
    active TINYINT(1) DEFAULT 1,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE instructor_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    spotify_access_token TEXT DEFAULT NULL,
    spotify_refresh_token VARCHAR(400) DEFAULT NULL,
    spotify_expires_at TIMESTAMP NULL DEFAULT NULL,
    preferences_json JSON DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sessions_auth (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- EXERCISE LIBRARY
-- ============================================================

CREATE TABLE exercises (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    name_es VARCHAR(150) DEFAULT NULL,
    muscle_group ENUM('chest','back','shoulders','arms','core','legs','glutes','full_body','cardio') NOT NULL DEFAULT 'full_body',
    equipment JSON DEFAULT NULL,
    level ENUM('beginner','intermediate','advanced','all') DEFAULT 'all',
    tags_json JSON DEFAULT NULL,
    duration_rec INT DEFAULT 30 COMMENT 'seconds',
    description TEXT DEFAULT NULL,
    video_url VARCHAR(255) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    is_global TINYINT(1) DEFAULT 0 COMMENT '1 = visible in all gyms',
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- BLOCKS
-- ============================================================

CREATE TABLE blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    type ENUM('interval','tabata','amrap','emom','fortime','series','circuit','rest','briefing') NOT NULL,
    config_json JSON NOT NULL COMMENT 'rounds, times, etc.',
    exercises_json JSON DEFAULT NULL COMMENT 'array of exercise objects',
    is_shared TINYINT(1) DEFAULT 0,
    share_mode ENUM('copy','common') DEFAULT 'copy',
    version SMALLINT DEFAULT 1,
    tags_json JSON DEFAULT NULL,
    total_duration INT DEFAULT NULL COMMENT 'computed seconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TEMPLATES
-- ============================================================

CREATE TABLE templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    blocks_json JSON NOT NULL COMMENT 'ordered array of block snapshots',
    is_shared TINYINT(1) DEFAULT 0,
    share_mode ENUM('copy','common') DEFAULT 'copy',
    version SMALLINT DEFAULT 1,
    tags_json JSON DEFAULT NULL,
    total_duration INT DEFAULT NULL COMMENT 'computed seconds',
    class_level ENUM('beginner','intermediate','advanced','mixed') DEFAULT 'mixed',
    max_participants INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SESSIONS (LIVE INSTANCES)
-- ============================================================

CREATE TABLE gym_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED NOT NULL,
    sala_id INT UNSIGNED DEFAULT NULL,
    instructor_id INT UNSIGNED NOT NULL,
    template_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    blocks_json JSON NOT NULL COMMENT 'full snapshot of blocks at session creation',
    status ENUM('idle','playing','paused','finished') DEFAULT 'idle',
    current_block_index SMALLINT DEFAULT 0,
    current_block_elapsed INT DEFAULT 0 COMMENT 'seconds',
    spotify_playlist_uri VARCHAR(255) DEFAULT NULL,
    scheduled_at TIMESTAMP NULL DEFAULT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- REAL-TIME SYNC STATE
-- ============================================================

CREATE TABLE sync_state (
    sala_id INT UNSIGNED NOT NULL PRIMARY KEY,
    session_id INT UNSIGNED DEFAULT NULL,
    state_json JSON DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- WEEKLY SCHEDULER
-- ============================================================

CREATE TABLE schedule_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED NOT NULL,
    sala_id INT UNSIGNED DEFAULT NULL,
    session_id INT UNSIGNED DEFAULT NULL,
    instructor_id INT UNSIGNED DEFAULT NULL,
    day_of_week TINYINT DEFAULT NULL COMMENT '0=Sun, 1=Mon...6=Sat',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    label VARCHAR(100) DEFAULT NULL,
    color VARCHAR(7) DEFAULT NULL,
    recurrent TINYINT(1) DEFAULT 1,
    date_override DATE DEFAULT NULL COMMENT 'for one-off slots',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gym_id) REFERENCES gyms(id) ON DELETE CASCADE,
    FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SHARING SYSTEM
-- ============================================================

CREATE TABLE shared_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('block','template','session') NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    shared_by INT UNSIGNED NOT NULL,
    target_type ENUM('user','gym') DEFAULT 'user',
    target_id INT UNSIGNED NOT NULL,
    share_mode ENUM('copy','common') DEFAULT 'copy',
    permissions_json JSON DEFAULT NULL,
    message VARCHAR(255) DEFAULT NULL,
    accepted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- INDEXES
-- ============================================================

CREATE INDEX idx_users_gym ON users(gym_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_exercises_gym ON exercises(gym_id);
CREATE INDEX idx_exercises_muscle ON exercises(muscle_group);
CREATE INDEX idx_blocks_gym ON blocks(gym_id);
CREATE INDEX idx_templates_gym ON templates(gym_id);
CREATE INDEX idx_sessions_gym ON gym_sessions(gym_id);
CREATE INDEX idx_sessions_sala ON gym_sessions(sala_id);
CREATE INDEX idx_sessions_instructor ON gym_sessions(instructor_id);
CREATE INDEX idx_schedule_gym ON schedule_slots(gym_id);
CREATE INDEX idx_shared_items ON shared_items(item_type, item_id);
