<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../includes/mailer.php';

wajib_metode('POST');
$pdo = db();
$body = body_json();

$email = app_normalisasi_email((string)($body['email'] ?? ''));
if (!app_email_valid($email)) {
    json_error(422, 'VALIDASI', 'Format email tidak valid.');
}

$pesanGenerik = 'Jika email terdaftar, kode reset password telah dikirim.';

$stmt = $pdo->prepare('SELECT * FROM app_users WHERE email = :e');
$stmt->execute(['e' => $email]);
$user = $stmt->fetch();

if ($user && !app_otp_dibatasi((int)$user['id'], 'reset_password')) {
    $kode = app_terbitkan_otp((int)$user['id'], 'reset_password');
    kirim_email($email, $user['nama'], 'Kode Reset Password ' . APP_NAME, template_email_otp($user['nama'], $kode, 'reset_password'));
}

json_ok(['pesan' => $pesanGenerik]);
