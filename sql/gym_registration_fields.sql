/* GymFlow – DB migration: gym registration fields
   Run once:  mysql -u root gymflow < sql/gym_registration_fields.sql  */
ALTER TABLE gyms
  ADD COLUMN IF NOT EXISTS city              VARCHAR(80)  DEFAULT NULL AFTER slug,
  ADD COLUMN IF NOT EXISTS phone             VARCHAR(30)  DEFAULT NULL AFTER city,
  ADD COLUMN IF NOT EXISTS gym_type          VARCHAR(40)  DEFAULT NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS instructors_count VARCHAR(10)  DEFAULT NULL AFTER gym_type;
