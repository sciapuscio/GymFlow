-- Migration: staff-driven temp PIN password reset
ALTER TABLE members
  ADD COLUMN temp_pin        VARCHAR(4)   DEFAULT NULL COMMENT '4-digit temp PIN set by staff',
  ADD COLUMN must_change_pwd TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = force password change on next login';
