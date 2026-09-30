<?php
require_once __DIR__ . '/../_bootstrap.php';

wajib_metode('POST');
$pdo = db();
$body = body_json();

$email = app_normalisasi_email((string)($body['email'] ?? ''));
$kode = trim((string)($body['code'] ?? ''));

if (!app_email_valid($email) || $kode === '') {
    json_error(422, 'VALIDASI', 'Email dan kode wajib diisi.');
}

$stmt = $pdo->prepare('SELECT * FROM app_users WHERE email = :e');
$stmt->execute(['e' => $email]);
$user = $stmt->fetch();

if (!$user) {
    json_error(400, 'KODE_TIDAK_VALID', 'Email atau kode tidak valid.');
}

if ($user['email_verified_at'] !== null) {
    $akses = app_buat_access_token($user);
    $refresh = app_terbitkan_refresh_token((int)$user['id'], $_SERVER['HTTP_USER_AGENT'] ?? null);
    json_ok([
        'pesan' => 'Email sudah terverifikasi sebelumnya.',
        'access_token' => $akses,
        'refresh_token' => $refresh,
        'user' => serialisasi_app_user($user),
    ]);
}

$hasil = app_verifikasi_otp((int)$user['id'], 'verify_email', $kode);
if (!$hasil['sukses']) {
    json_error(400, 'KODE_TIDAK_VALID', $hasil['pesan']);
}

$pdo->prepare('UPDATE app_users SET email_verified_at = NOW() WHERE id = :id')->execute(['id' => $user['id']]);
$user['email_verified_at'] = date('Y-m-d H:i:s');

$akses = app_buat_access_token($user);
$refresh = app_terbitkan_refresh_token((int)$user['id'], $_SERVER['HTTP_USER_AGENT'] ?? null);

json_ok([
    'pesan' => 'Email berhasil diverifikasi.',
    'access_token' => $akses,
    'refresh_token' => $refresh,
    'user' => serialisasi_app_user($user),
]);
