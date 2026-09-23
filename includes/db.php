<?php
/**
 * Koneksi database (PDO) — digunakan di seluruh aplikasi.
 * Selalu gunakan prepared statement, jangan pernah menyisipkan
 * input pengguna langsung ke dalam query SQL.
 */

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // gunakan prepared statement asli MySQL
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jangan pernah menampilkan detail koneksi/kredensial ke pengguna
            error_log('Koneksi database gagal: ' . $e->getMessage());
            http_response_code(500);
            if (APP_ENV === 'production') {
                die('Situs sedang mengalami gangguan. Silakan coba beberapa saat lagi.');
            }
            die('Koneksi database gagal: ' . $e->getMessage());
        }
    }

    return $pdo;
}
