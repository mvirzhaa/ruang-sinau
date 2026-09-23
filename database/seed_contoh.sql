-- =========================================================
-- Ruang Sinau — Data Contoh (opsional)
-- Import SETELAH schema.sql untuk melihat contoh konten nyata:
-- satu latihan soal asli (Matematika Kelas 4 SD — Komposisi & Dekomposisi
-- Bangun Datar) sudah disertakan di uploads/quizzes/ dan didaftarkan di sini.
--
--   mysql -u USER -p NAMA_DATABASE < database/seed_contoh.sql
--
-- Aman dihapus/diabaikan jika Anda ingin mulai dari kosong sama sekali.
-- =========================================================

SET NAMES utf8mb4;

-- Mata pelajaran Matematika untuk jenjang SD (asumsi jenjang 'sd' sudah ada dari schema.sql)
INSERT INTO mapel (jenjang_id, nama, slug, emoji, urutan)
SELECT id, 'Matematika', 'matematika', '🔢', 1 FROM jenjang WHERE slug = 'sd';

INSERT INTO mapel (jenjang_id, nama, slug, emoji, urutan)
SELECT id, 'Bahasa Indonesia', 'bahasa-indonesia', '📖', 2 FROM jenjang WHERE slug = 'sd';

INSERT INTO mapel (jenjang_id, nama, slug, emoji, urutan)
SELECT id, 'Bahasa Inggris', 'bahasa-inggris', '🇬🇧', 3 FROM jenjang WHERE slug = 'sd';

-- Kelas 1 sampai 6 untuk Matematika SD
INSERT INTO kelas (mapel_id, nama, slug, urutan)
SELECT m.id, CONCAT('Kelas ', n.angka), CONCAT('kelas-', n.angka), n.angka
FROM mapel m
JOIN jenjang j ON j.id = m.jenjang_id
JOIN (SELECT 1 AS angka UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) n
WHERE m.slug = 'matematika' AND j.slug = 'sd';

-- Contoh latihan soal nyata: Komposisi & Dekomposisi Bangun Datar (Matematika Kelas 4 SD)
-- Berkasnya ada di uploads/quizzes/latihan_komposisi_dekomposisi_kelas4.html
INSERT INTO topik (kelas_id, tipe, judul, slug, deskripsi, tingkat, jumlah_soal, file_path, emoji, status)
SELECT k.id, 'latihan',
       'Latihan Komposisi & Dekomposisi Bangun Datar',
       'komposisi-dekomposisi-bangun-datar',
       'Latihan interaktif 50 soal tentang menyusun (komposisi) dan mengurai (dekomposisi) berbagai bangun datar, lengkap dengan sertifikat di akhir sesi.',
       'sedang', 50,
       'latihan_komposisi_dekomposisi_kelas4.html',
       '📐', 'published'
FROM kelas k
JOIN mapel m ON m.id = k.mapel_id
JOIN jenjang j ON j.id = m.jenjang_id
WHERE m.slug = 'matematika' AND j.slug = 'sd' AND k.slug = 'kelas-4';

-- Contoh materi (artikel) sebagai ilustrasi tipe konten non-latihan
INSERT INTO topik (kelas_id, tipe, judul, slug, deskripsi, konten, emoji, status)
SELECT k.id, 'materi',
       'Mengenal Komposisi dan Dekomposisi Bangun Datar',
       'mengenal-komposisi-dekomposisi',
       'Ringkasan konsep dasar sebelum mengerjakan latihan soal.',
       '<h2>Apa itu Komposisi dan Dekomposisi?</h2><p><strong>Komposisi</strong> adalah proses menyusun atau menggabungkan beberapa bangun datar menjadi satu bangun baru. <strong>Dekomposisi</strong> adalah kebalikannya: menguraikan satu bangun datar menjadi beberapa bagian yang lebih kecil.</p><h3>Contoh Komposisi</h3><p>Dua segitiga siku-siku yang sama besar, jika digabungkan pada sisi miringnya, akan membentuk sebuah persegi panjang.</p><h3>Contoh Dekomposisi</h3><p>Sebuah persegi dapat diuraikan menjadi dua segitiga siku-siku yang sama besar dengan menariknya garis diagonal.</p><p>Yuk, coba latihan soalnya di halaman berikutnya!</p>',
       '📘', 'published'
FROM kelas k
JOIN mapel m ON m.id = k.mapel_id
JOIN jenjang j ON j.id = m.jenjang_id
WHERE m.slug = 'matematika' AND j.slug = 'sd' AND k.slug = 'kelas-4';
