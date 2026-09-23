<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    http_response_code(403);
    echo json_encode(['sukses' => false, 'pesan' => 'Permintaan tidak valid.']);
    exit;
}

if (empty($_FILES['gambar']['name'])) {
    echo json_encode(['sukses' => false, 'pesan' => 'Tidak ada berkas yang diunggah.']);
    exit;
}

$file = $_FILES['gambar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['sukses' => false, 'pesan' => 'Gagal mengunggah berkas.']);
    exit;
}

$ukuranMb = $file['size'] / (1024 * 1024);
if ($ukuranMb > MAX_UPLOAD_GAMBAR_MB) {
    echo json_encode(['sukses' => false, 'pesan' => 'Ukuran gambar melebihi batas ' . MAX_UPLOAD_GAMBAR_MB . ' MB.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$mimeKeExt = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

if (!isset($mimeKeExt[$mime])) {
    echo json_encode(['sukses' => false, 'pesan' => 'Jenis berkas harus berupa gambar (PNG, JPG, WEBP, atau GIF).']);
    exit;
}

$namaFile = 'materi-' . substr(bin2hex(random_bytes(6)), 0, 12) . '.' . $mimeKeExt[$mime];

if (!is_dir(UPLOAD_DIR_MATERI)) {
    @mkdir(UPLOAD_DIR_MATERI, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR_MATERI . $namaFile)) {
    echo json_encode(['sukses' => false, 'pesan' => 'Gagal menyimpan gambar ke server.']);
    exit;
}

catat_log($admin['id'], 'unggah_gambar_materi', $namaFile);

echo json_encode(['sukses' => true, 'url' => url_publik(ltrim(UPLOAD_URL_MATERI, '/') . $namaFile)]);
