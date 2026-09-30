<?php
require_once __DIR__ . '/_bootstrap.php';

wajib_metode('GET', 'PATCH');
$pdo = db();
$user = wajib_auth_app();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_ok(['user' => serialisasi_app_user($user)]);
}

$body = body_json();
$namaBaru = isset($body['nama']) ? trim((string)$body['nama']) : null;
$passwordBaru = isset($body['password']) ? (string)$body['password'] : null;
$passwordSaatIni = (string)($body['password_saat_ini'] ?? '');

if ($namaBaru === null && $passwordBaru === null) {
    json_error(422, 'VALIDASI', 'Tidak ada perubahan yang dikirim.');
}

if ($namaBaru !== null) {
    if ($namaBaru === '' || strlen($namaBaru) > 100) {
        json_error(422, 'VALIDASI', 'Nama wajib diisi (maksimal 100 karakter).');
    }
    $pdo->prepare('UPDATE app_users SET nama = :n WHERE id = :id')->execute(['n' => $namaBaru, 'id' => $user['id']]);
    $user['nama'] = $namaBaru;
}

if ($passwordBaru !== null) {
    if (!password_verify($passwordSaatIni, $user['password_hash'])) {
        json_error(403, 'PASSWORD_SALAH', 'Password saat ini tidak cocok.');
    }
    if (!app_password_valid($passwordBaru)) {
        json_error(422, 'VALIDASI', 'Password baru minimal 8 karakter.');
    }
    $pdo->prepare('UPDATE app_users SET password_hash = :h WHERE id = :id')
        ->execute(['h' => password_hash($passwordBaru, PASSWORD_DEFAULT), 'id' => $user['id']]);
    // Ganti password sendiri: cabut sesi LAIN (refresh token) demi keamanan.
    app_cabut_semua_refresh_token((int)$user['id']);
}

json_ok(['user' => serialisasi_app_user($user), 'pesan' => 'Profil berhasil diperbarui.']);
