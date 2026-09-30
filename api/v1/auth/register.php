<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../includes/mailer.php';

wajib_metode('POST');
$pdo = db();
$body = body_json();

$nama = trim((string)($body['nama'] ?? ''));
$email = app_normalisasi_email((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if ($nama === '' || strlen($nama) > 100) {
    json_error(422, 'VALIDASI', 'Nama wajib diisi (maksimal 100 karakter).');
}
if (!app_email_valid($email)) {
    json_error(422, 'VALIDASI', 'Format email tidak valid.');
}
if (!app_password_valid($password)) {
    json_error(422, 'VALIDASI', 'Password minimal 8 karakter.');
}

$cek = $pdo->prepare('SELECT id FROM app_users WHERE email = :e');
$cek->execute(['e' => $email]);
if ($cek->fetch()) {
    json_error(409, 'EMAIL_TERDAFTAR', 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.');
}

$stmt = $pdo->prepare(
    'INSERT INTO app_users (nama, email, password_hash, status, sumber_daftar) VALUES (:n, :e, :h, :s, :src)'
);
$stmt->execute([
    'n' => $nama,
    'e' => $email,
    'h' => password_hash($password, PASSWORD_DEFAULT),
    's' => 'aktif',
    'src' => 'mobile',
]);
$userId = (int)$pdo->lastInsertId();

$kode = app_terbitkan_otp($userId, 'verify_email');
kirim_email($email, $nama, 'Kode Verifikasi ' . APP_NAME, template_email_otp($nama, $kode, 'verify_email'));

json_ok([
    'user_id' => $userId,
    'email' => $email,
    'pesan' => 'Registrasi berhasil. Kode verifikasi telah dikirim ke email Anda.',
], 201);
