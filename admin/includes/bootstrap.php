<?php
/**
 * Wajib di-require di baris pertama setiap halaman admin (kecuali login.php).
 * Memastikan sesi admin valid sebelum kode halaman berjalan.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

wajib_login();
$admin = admin_saat_ini();
