<?php
/**
 * Ruang Sinau — Konfigurasi Aplikasi
 * ------------------------------------------------------------
 * Salin nilai di bawah sesuai kredensial hosting Anda.
 * JANGAN unggah file ini ke repositori publik / tempat terbuka.
 * Sebaiknya set permission file ini menjadi 640 di server produksi.
 * ------------------------------------------------------------
 */

// ---------- Database ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'ruang_sinau');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------- Aplikasi ----------
define('APP_NAME', 'Ruang Sinau');
define('APP_URL', 'http://localhost/ruang-sinau'); // ganti ke URL asli saat go-live, TANPA garis miring di akhir
define('APP_ENV', 'development'); // ganti ke 'production' saat go-live (mematikan tampilan error PHP)

// ---------- Unggah file ----------
define('MAX_UPLOAD_LATIHAN_MB', 5);      // ukuran maksimum file HTML latihan soal
define('MAX_UPLOAD_GAMBAR_MB', 3);       // ukuran maksimum gambar pada materi
define('UPLOAD_DIR_QUIZZES', __DIR__ . '/../uploads/quizzes/');
define('UPLOAD_DIR_MATERI', __DIR__ . '/../uploads/materi/');
define('UPLOAD_URL_QUIZZES', '/uploads/quizzes/');
define('UPLOAD_URL_MATERI', '/uploads/materi/');

// ---------- Keamanan ----------
define('LOGIN_MAX_ATTEMPTS', 5);         // percobaan login gagal sebelum dikunci sementara
define('LOGIN_LOCKOUT_MINUTES', 15);     // lama penguncian setelah gagal berkali-kali
define('SESSION_NAME', 'ruangsinau_admin_sid');

// ---------- Zona waktu ----------
date_default_timezone_set('Asia/Jakarta');

// ---------- Penanganan error ----------
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
