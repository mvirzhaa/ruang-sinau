-- =========================================================
-- Ruang Sinau — Migrasi 003: Pembelian Satu-Kali per Topik (Mobile)
-- Jalankan pada database yang sudah ada (instalasi lama):
--   mysql -u USER -p NAMA_DATABASE < database/migration_003_pembelian.sql
-- Hanya jalankan SEKALI — kolom/tabel di sini belum ada di instalasi lama.
-- Untuk instalasi baru, perubahan ini sudah termasuk di schema.sql.
-- =========================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- 1. Tandai topik yang berbayar di aplikasi mobile (tidak memengaruhi situs web)
-- ---------------------------------------------------------
ALTER TABLE topik
    ADD COLUMN berbayar_mobile TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN harga INT UNSIGNED NULL AFTER berbayar_mobile;

-- ---------------------------------------------------------
-- 2. Transaksi pembelian topik oleh pengguna aplikasi mobile
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS pembelian (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    topik_id        INT UNSIGNED NOT NULL,
    harga_dibayar   INT UNSIGNED NOT NULL,
    metode          ENUM('manual','midtrans','xendit') NOT NULL DEFAULT 'manual',
    status          ENUM('menunggu','berhasil','ditolak','refund') NOT NULL DEFAULT 'menunggu',
    catatan         VARCHAR(255) NULL,
    diproses_oleh   INT UNSIGNED NULL,
    diproses_pada   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pembelian_user (user_id),
    KEY idx_pembelian_topik (topik_id),
    KEY idx_pembelian_status (status),
    CONSTRAINT fk_pembelian_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembelian_topik FOREIGN KEY (topik_id) REFERENCES topik(id) ON DELETE CASCADE,
    CONSTRAINT fk_pembelian_admin FOREIGN KEY (diproses_oleh) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
