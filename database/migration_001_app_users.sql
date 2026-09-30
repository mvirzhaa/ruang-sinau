-- =========================================================
-- Ruang Sinau — Migrasi 001: Akun Pengguna Aplikasi Mobile
-- Jalankan pada database yang sudah ada (instalasi lama):
--   mysql -u USER -p NAMA_DATABASE < database/migration_001_app_users.sql
-- Aman dijalankan berulang kali (semua CREATE TABLE pakai IF NOT EXISTS).
-- Untuk instalasi baru, tabel-tabel ini sudah termasuk di schema.sql.
-- =========================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- 1. Akun pengguna aplikasi mobile (terpisah dari admin_users)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama                VARCHAR(100) NOT NULL,
    email               VARCHAR(190) NOT NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    status              ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    email_verified_at   DATETIME NULL,
    sumber_daftar       ENUM('mobile','admin') NOT NULL DEFAULT 'mobile',
    last_login_at       DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_app_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 2. Refresh token (rotasi, bisa dicabut) untuk sesi mobile
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_user_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    token_hash      CHAR(64) NOT NULL,
    user_agent      VARCHAR(255) NULL,
    expires_at      DATETIME NOT NULL,
    revoked_at      DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_app_tokens_user (user_id),
    KEY idx_app_tokens_hash (token_hash),
    CONSTRAINT fk_app_tokens_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 3. Kode OTP (verifikasi email & reset password)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_email_otp (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    purpose         ENUM('verify_email','reset_password') NOT NULL,
    code_hash       CHAR(64) NOT NULL,
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at      DATETIME NOT NULL,
    consumed_at     DATETIME NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_app_otp_lookup (user_id, purpose, consumed_at),
    CONSTRAINT fk_app_otp_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 4. Proteksi brute-force login/registrasi/OTP mobile (per email + IP)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_login_attempts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(190) NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    waktu       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_app_attempts_lookup (email, ip_address, waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
