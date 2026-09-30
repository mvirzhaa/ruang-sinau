<?php
require_once __DIR__ . '/../_bootstrap.php';

wajib_metode('POST');
$pdo = db();
$body = body_json();

$email = app_normalisasi_email((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if (!app_email_valid($email) || $password === '') {
    json_error(422, 'VALIDASI', 'Email dan password wajib diisi.');
}

if (app_login_terkunci($email)) {
    json_error(429, 'TERKUNCI', 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . APP_LOGIN_LOCKOUT_MINUTES . ' menit.');
}

$stmt = $pdo->prepare('SELECT * FROM app_users WHERE email = :e');
$stmt->execute(['e' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    app_catat_percobaan_gagal($email);
    json_error(401, 'KREDENSIAL_SALAH', 'Email atau password salah.');
}

app_bersihkan_percobaan_gagal($email);

if ($user['status'] !== 'aktif') {
    json_error(403, 'AKUN_NONAKTIF', 'Akun Anda telah dinonaktifkan. Hubungi pengelola.');
}

if ($user['email_verified_at'] === null) {
    json_error(403, 'EMAIL_BELUM_VERIFIKASI', 'Email belum diverifikasi. Cek kode OTP yang dikirim ke email Anda.');
}

$pdo->prepare('UPDATE app_users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);

if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $pdo->prepare('UPDATE app_users SET password_hash = :h WHERE id = :id')
        ->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
}

$akses = app_buat_access_token($user);
$refresh = app_terbitkan_refresh_token((int)$user['id'], $_SERVER['HTTP_USER_AGENT'] ?? null);

json_ok([
    'access_token' => $akses,
    'refresh_token' => $refresh,
    'user' => serialisasi_app_user($user),
]);
