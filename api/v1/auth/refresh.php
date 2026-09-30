<?php
require_once __DIR__ . '/../_bootstrap.php';

wajib_metode('POST');
$pdo = db();
$body = body_json();

$refreshToken = (string)($body['refresh_token'] ?? '');
if ($refreshToken === '') {
    json_error(422, 'VALIDASI', 'refresh_token wajib diisi.');
}

$tokenRow = app_ambil_refresh_token_valid($refreshToken);
if (!$tokenRow) {
    json_error(401, 'REFRESH_TIDAK_VALID', 'Sesi sudah berakhir. Silakan masuk kembali.');
}

$stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = :id');
$stmt->execute(['id' => $tokenRow['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['status'] !== 'aktif') {
    app_cabut_refresh_token_by_id((int)$tokenRow['id']);
    json_error(401, 'REFRESH_TIDAK_VALID', 'Sesi sudah berakhir. Silakan masuk kembali.');
}

// Rotasi: cabut token lama, terbitkan pasangan token baru.
app_cabut_refresh_token_by_id((int)$tokenRow['id']);
$aksesBaru = app_buat_access_token($user);
$refreshBaru = app_terbitkan_refresh_token((int)$user['id'], $_SERVER['HTTP_USER_AGENT'] ?? null);

json_ok([
    'access_token' => $aksesBaru,
    'refresh_token' => $refreshBaru,
]);
