-- =========================================================
-- Ruang Sinau — Skema Basis Data
-- Platform latihan soal & materi belajar gratis untuk semua
-- =========================================================
-- Import file ini melalui phpMyAdmin, Adminer, atau CLI:
--   mysql -u USER -p NAMA_DATABASE < schema.sql
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ---------------------------------------------------------
-- 1. Pengguna admin (pengelola konten)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    nama            VARCHAR(100) NOT NULL,
    peran           ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
    aktif           TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at   DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 2. Jenjang pendidikan (SD, SMP, SMA, Umum, dst — dinamis)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS jenjang (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama        VARCHAR(60)  NOT NULL,
    slug        VARCHAR(60)  NOT NULL UNIQUE,
    deskripsi   VARCHAR(255) NULL,
    urutan      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 3. Mata pelajaran per jenjang
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS mapel (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jenjang_id  INT UNSIGNED NOT NULL,
    nama        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL,
    emoji       VARCHAR(10)  NULL DEFAULT '📘',
    urutan      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mapel_slug (jenjang_id, slug),
    CONSTRAINT fk_mapel_jenjang FOREIGN KEY (jenjang_id) REFERENCES jenjang(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 4. Kelas / tingkat per mata pelajaran
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS kelas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mapel_id    INT UNSIGNED NOT NULL,
    nama        VARCHAR(60)  NOT NULL,
    slug        VARCHAR(60)  NOT NULL,
    urutan      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_kelas_slug (mapel_id, slug),
    CONSTRAINT fk_kelas_mapel FOREIGN KEY (mapel_id) REFERENCES mapel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 5. Topik — satuan konten: latihan soal (html) ATAU materi (teks kaya)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS topik (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kelas_id        INT UNSIGNED NOT NULL,
    tipe            ENUM('latihan','materi') NOT NULL DEFAULT 'latihan',
    judul           VARCHAR(200) NOT NULL,
    slug            VARCHAR(220) NOT NULL,
    deskripsi       VARCHAR(500) NULL,
    tingkat         ENUM('mudah','sedang','sulit') NULL,
    jumlah_soal     SMALLINT UNSIGNED NULL,
    file_path       VARCHAR(255) NULL,      -- relatif ke /uploads/quizzes/ (tipe=latihan)
    konten          LONGTEXT NULL,          -- html materi yang sudah disanitasi (tipe=materi)
    emoji           VARCHAR(10) NULL DEFAULT '📝',
    status          ENUM('draft','published') NOT NULL DEFAULT 'draft',
    dilihat         INT UNSIGNED NOT NULL DEFAULT 0,
    dibuat_oleh     INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_topik_slug (kelas_id, slug),
    KEY idx_topik_status (status),
    KEY idx_topik_tipe (tipe),
    CONSTRAINT fk_topik_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_topik_admin FOREIGN KEY (dibuat_oleh) REFERENCES admin_users(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_topik_cari (judul, deskripsi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 6. Percobaan login yang gagal (proteksi brute-force sederhana)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    waktu       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_lookup (username, ip_address, waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- 7. Log aktivitas admin (jejak audit)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS log_aktivitas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id    INT UNSIGNED NULL,
    aksi        VARCHAR(255) NOT NULL,
    detail      VARCHAR(500) NULL,
    ip_address  VARCHAR(45) NULL,
    waktu       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================
-- Akun admin awal
-- Username: admin   Password: GantiSegera123!
-- PENTING: ganti password ini segera setelah login pertama
-- (hash di bawah dibuat dengan password_hash('GantiSegera123!', PASSWORD_DEFAULT))
-- =========================================================
INSERT INTO admin_users (username, password_hash, nama, peran) VALUES
('admin', '$2y$10$pXLlMNXsuqApEAdl7BHuru1rSLe.tyoGc6OPlst3cRXBs1stx3aS2', 'Wajrasena', 'super_admin');

-- =========================================================
-- Data awal: Jenjang
-- =========================================================
INSERT INTO jenjang (nama, slug, deskripsi, urutan) VALUES
('SD', 'sd', 'Sekolah Dasar — Kelas 1 sampai 6', 1),
('SMP', 'smp', 'Sekolah Menengah Pertama — Kelas 7 sampai 9', 2),
('SMA', 'sma', 'Sekolah Menengah Atas — Kelas 10 sampai 12', 3);
