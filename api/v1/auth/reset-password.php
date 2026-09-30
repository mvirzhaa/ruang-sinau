<?php
require_once __DIR__ . '/../_bootstrap.php';

wajib_metode('POST');
$pdo = db();
$body = body_json();

$email = app_normalisasi_email((string)($body['email'] ?? ''));
$kode = trim((string)($body['code'] ?? ''));
$passwordBaru = (string)($body['new_password'] ?? '');

if (!app_email_valid($email) || $kode === '') {
    json_error(422, 'VALIDASI', 'Email dan kode wajib diisi.');
}
if (!app_password_valid($passwordBaru)) {
    json_error(422, 'VALIDASI', 'Password baru minimal 8 karakter.');
}

$stmt = $pdo->prepare('SELECT * FROM app_users WHERE email = :e');
$stmt->execute(['e' => $email]);
$user = $stmt->fetch();

if (!$user) {
    json_error(400, 'KODE_TIDAK_VALID', 'Kode tidak valid atau sudah kedaluwarsa.');
}

$hasil = app_verifikasi_otp((int)$user['id'], 'reset_password', $kode);
if (!$hasil['sukses']) {
    json_error(400, 'KODE_TIDAK_VALID', $hasil['pesan']);
}

$pdo->prepare('UPDATE app_users SET password_hash = :h WHERE id = :id')
    ->execute(['h' => password_hash($passwordBaru, PASSWORD_DEFAULT), 'id' => $user['id']]);

// Password diganti — cabut semua sesi lama demi keamanan, wajib login ulang.
app_cabut_semua_refresh_token((int)$user['id']);

json_ok(['pesan' => 'Password berhasil diubah. Silakan masuk dengan password baru.']);
