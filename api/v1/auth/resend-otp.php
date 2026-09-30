<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../includes/mailer.php';

wajib_metode('POST');
$pdo = db();
$body = body_json();

$email = app_normalisasi_email((string)($body['email'] ?? ''));
$purpose = (string)($body['purpose'] ?? '');

if (!app_email_valid($email) || !in_array($purpose, ['verify_email', 'reset_password'], true)) {
    json_error(422, 'VALIDASI', 'Email dan tujuan (purpose) wajib diisi dengan benar.');
}

$pesanGenerik = 'Jika email terdaftar, kode baru telah dikirim.';

$stmt = $pdo->prepare('SELECT * FROM app_users WHERE email = :e');
$stmt->execute(['e' => $email]);
$user = $stmt->fetch();

if (!$user) {
    json_ok(['pesan' => $pesanGenerik]);
}

if ($purpose === 'verify_email' && $user['email_verified_at'] !== null) {
    json_ok(['pesan' => 'Email ini sudah terverifikasi. Silakan masuk.']);
}

if (app_otp_dibatasi((int)$user['id'], $purpose)) {
    json_error(429, 'TERLALU_BANYAK_PERMINTAAN', 'Terlalu banyak permintaan kode. Coba lagi dalam beberapa menit.');
}

$kode = app_terbitkan_otp((int)$user['id'], $purpose);
$subjek = $purpose === 'verify_email' ? 'Kode Verifikasi ' . APP_NAME : 'Kode Reset Password ' . APP_NAME;
kirim_email($email, $user['nama'], $subjek, template_email_otp($user['nama'], $kode, $purpose));

json_ok(['pesan' => $pesanGenerik]);
