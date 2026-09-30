<?php
require_once __DIR__ . '/../_bootstrap.php';

wajib_metode('POST');
$body = body_json();
$refreshToken = (string)($body['refresh_token'] ?? '');

if ($refreshToken !== '') {
    app_cabut_refresh_token($refreshToken);
}

json_ok(['pesan' => 'Berhasil keluar.']);
