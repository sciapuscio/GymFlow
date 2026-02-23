-- OTP 2FA migration
ALTER TABLE users
    ADD COLUMN otp_secret  VARCHAR(32) NULL DEFAULT NULL AFTER password_hash,
    ADD COLUMN otp_enabled TINYINT(1)  NOT NULL DEFAULT 0 AFTER otp_secret;
