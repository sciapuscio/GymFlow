-- GymFlow Multi-Sede Migration (MySQL compatible)
-- Compatible con MySQL 5.7+ y MariaDB
-- Ejecutar en producción en la BD gymflow

-- 1. Tabla sedes
CREATE TABLE IF NOT EXISTS sedes (
    id          INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id      INT(10) UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    address     VARCHAR(255) DEFAULT NULL,
    qr_token    CHAR(36) NOT NULL,
    active      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_qr_sedes (qr_token),
    FOREIGN KEY fk_sedes_gym (gym_id) REFERENCES gyms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. sede_id en salas (ignorar error si ya existe)
SET @dbname = DATABASE();
SET @tablename = 'salas';
SET @columnname = 'sede_id';
SET @preparedStatement = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE salas ADD COLUMN sede_id INT(10) UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. sede_id en schedule_slots
SET @tablename = 'schedule_slots';
SET @preparedStatement = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE schedule_slots ADD COLUMN sede_id INT(10) UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4. sede_id en member_reservations
SET @tablename = 'member_reservations';
SET @preparedStatement = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE member_reservations ADD COLUMN sede_id INT(10) UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 5. sede_id en member_attendances
SET @tablename = 'member_attendances';
SET @preparedStatement = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE member_attendances ADD COLUMN sede_id INT(10) UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 6. all_sedes flag en member_memberships
SET @tablename = 'member_memberships';
SET @columnname = 'all_sedes';
SET @preparedStatement = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE member_memberships ADD COLUMN all_sedes TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 7. Tabla pivot membresía-sedes
CREATE TABLE IF NOT EXISTS member_membership_sedes (
    id            INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membership_id INT(10) UNSIGNED NOT NULL,
    sede_id       INT(10) UNSIGNED NOT NULL,
    UNIQUE KEY uq_mem_sede (membership_id, sede_id),
    FOREIGN KEY fk_mms_sede (sede_id) REFERENCES sedes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. sede_id_preferred en members
SET @tablename = 'members';
SET @columnname = 'sede_id_preferred';
SET @preparedStatement = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE members ADD COLUMN sede_id_preferred INT(10) UNSIGNED NULL DEFAULT NULL',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
