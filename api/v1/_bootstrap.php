<?php
/**
 * Wajib di-require di baris pertama setiap endpoint api/v1/*.php.
 * Menyiapkan header JSON, CORS, dan helper respons standar.
 *
 * Endpoint di sini tidak pernah memakai sesi cookie (auth berbasis Bearer
 * token JWT), jadi Access-Control-Allow-Origin: * aman dipakai — tidak ada
 * kredensial berbasis cookie yang bisa "dicuri" lintas origin.
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/app_auth.php';
require_once __DIR__ . '/../../includes/pembelian.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_ok(array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['sukses' => true, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(int $status, string $kode, string $pesan): never
{
    http_response_code($status);
    echo json_encode(['sukses' => false, 'error' => ['kode' => $kode, 'pesan' => $pesan]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Decode body JSON dari request, kembalikan array kosong jika tidak valid. */
function body_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function wajib_metode(string ...$diizinkan): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $diizinkan, true)) {
        json_error(405, 'METODE_TIDAK_DIIZINKAN', 'Metode HTTP tidak didukung untuk endpoint ini.');
    }
}

/** @return array Baris app_users milik pemilik access token — hentikan request (401) jika tidak valid. */
function wajib_auth_app(): array
{
    $user = app_user_dari_access_token();
    if (!$user) {
        json_error(401, 'TIDAK_TERAUTENTIKASI', 'Token akses tidak valid atau sudah kedaluwarsa.');
    }
    return $user;
}

function serialisasi_app_user(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'nama' => $user['nama'],
        'email' => $user['email'],
        'status' => $user['status'],
        'email_terverifikasi' => $user['email_verified_at'] !== null,
        'dibuat_pada' => $user['created_at'],
    ];
}
