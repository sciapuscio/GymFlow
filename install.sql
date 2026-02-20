-- GymFlow Database Installation Script
-- Run this once to set up the database and seed data
-- Usage: mysql -u root < install.sql

CREATE DATABASE IF NOT EXISTS gymflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymflow;

-- Source schema and seed files
SOURCE sql/schema.sql;
SOURCE sql/seed.sql;

SELECT 'GymFlow installation complete!' AS status;
SELECT 'Default login: superadmin@gymflow.app / Admin123!' AS credentials;
