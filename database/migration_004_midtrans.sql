-- =========================================================
-- Ruang Sinau — Migrasi 004: Kolom Integrasi Midtrans untuk Pembelian
-- Jalankan pada database yang sudah ada (instalasi lama):
--   mysql -u USER -p NAMA_DATABASE < database/migration_004_midtrans.sql
-- Hanya jalankan SEKALI — kolom di sini belum ada di instalasi lama.
-- Untuk instalasi baru, perubahan ini sudah termasuk di schema.sql.
-- =========================================================

SET NAMES utf8mb4;

-- order_id: dikirim ke Midtrans (format 'PMB-{id pembelian}', selalu unik).
-- snap_token & payment_url: dipakai app mobile untuk menampilkan halaman pembayaran,
-- dan untuk mengembalikan transaksi yang sama jika user membuka ulang sebelum bayar.
ALTER TABLE pembelian
    ADD COLUMN order_id    VARCHAR(64)  NULL UNIQUE AFTER metode,
    ADD COLUMN snap_token  VARCHAR(255) NULL AFTER order_id,
    ADD COLUMN payment_url VARCHAR(255) NULL AFTER snap_token;
