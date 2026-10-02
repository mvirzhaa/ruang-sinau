<?php
/**
 * Ruang Sinau — Konfigurasi Aplikasi (TEMPLATE)
 * ------------------------------------------------------------
 * Salin berkas ini menjadi config.php lalu isi sesuai kredensial hosting Anda:
 *   cp config/config.example.php config/config.php
 * config.php TIDAK ikut dilacak git (lihat .gitignore) — supaya kredensial
 * production tidak pernah tertimpa atau ter-commit secara tidak sengaja.
 * Sebaiknya set permission config.php menjadi 640 di server produksi.
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
define('MAX_UPLOAD_ZIP_MB', 80);         // ukuran maksimum zip untuk impor massal
define('UPLOAD_DIR_QUIZZES', __DIR__ . '/../uploads/quizzes/');
define('UPLOAD_DIR_MATERI', __DIR__ . '/../uploads/materi/');
define('UPLOAD_URL_QUIZZES', '/uploads/quizzes/');
define('UPLOAD_URL_MATERI', '/uploads/materi/');

// ---------- Keamanan ----------
define('LOGIN_MAX_ATTEMPTS', 5);         // percobaan login gagal sebelum dikunci sementara
define('LOGIN_LOCKOUT_MINUTES', 15);     // lama penguncian setelah gagal berkali-kali
define('SESSION_NAME', 'ruangsinau_admin_sid');

// ---------- API mobile: token JWT ----------
// PENTING: ganti JWT_SECRET dengan string acak panjang sebelum go-live
// (mis. hasil dari bin2hex(random_bytes(32)) ). Siapa saja yang tahu nilai
// ini bisa memalsukan token login — JANGAN pernah dibagikan / commit nilai asli.
define('JWT_SECRET', 'ganti-dengan-string-acak-yang-sangat-panjang-sebelum-produksi');
define('JWT_ISSUER', APP_NAME);
define('JWT_ACCESS_TTL_MENIT', 15);      // umur access token
define('JWT_REFRESH_TTL_HARI', 30);      // umur refresh token

// ---------- API mobile: OTP (verifikasi email & reset password) ----------
define('OTP_TTL_MENIT', 15);             // umur kode OTP
define('OTP_MAX_ATTEMPTS', 5);           // percobaan salah kode sebelum kode dianggap kedaluwarsa
define('APP_LOGIN_MAX_ATTEMPTS', 5);     // percobaan login/registrasi gagal sebelum dikunci sementara
define('APP_LOGIN_LOCKOUT_MINUTES', 15);

// ---------- Pengiriman email (verifikasi & reset password mobile) ----------
// Kosongkan SMTP_HOST untuk mode pengembangan: email tidak benar-benar dikirim,
// tapi isinya (termasuk kode OTP) ditulis ke storage/mail_log/*.html agar bisa ditest.
define('SMTP_HOST', '');                 // mis. smtp.gmail.com — isi sebelum go-live
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');            // 'tls' (STARTTLS) atau 'ssl'
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('MAIL_FROM_EMAIL', 'no-reply@ruangsinau.test');
define('MAIL_FROM_NAME', APP_NAME);

// ---------- Harga default per jenjang (topik berbayar di aplikasi mobile) ----------
// Dipakai sebagai nilai default saat menambahkan topik baru (satuan maupun impor massal) —
// admin tetap bisa mengubah manual per topik lewat form Tambah/Ubah Konten. Jenjang yang
// tidak terdaftar di sini (atau belum dibuat) tidak akan otomatis ditandai berbayar.
define('HARGA_DEFAULT_PER_JENJANG', [
    'SD'   => 15000,
    'SMP'  => 35000,
    'SMA'  => 50000,
    'Umum' => 75000,
]);

// ---------- Payment gateway: Midtrans Snap (pembelian topik mobile) ----------
// Daftar gratis di https://dashboard.sandbox.midtrans.com untuk kredensial sandbox
// (dipakai untuk testing — tidak ada uang asli yang berpindah), atau
// https://dashboard.midtrans.com untuk kredensial production saat go-live.
// Server Key dipakai server-side untuk membuat transaksi & verifikasi notifikasi —
// JANGAN pernah dibagikan/commit nilai asli. Client Key aman dipakai di app mobile.
define('MIDTRANS_SERVER_KEY', '');       // mis. SB-Mid-server-xxxxxxxxxxxxxxxxxxxx
define('MIDTRANS_CLIENT_KEY', '');       // mis. SB-Mid-client-xxxxxxxxxxxxxxxxxxxx
define('MIDTRANS_IS_PRODUCTION', false); // ganti true + isi kredensial production saat go-live

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
