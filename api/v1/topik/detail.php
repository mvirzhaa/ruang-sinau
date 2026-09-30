<?php
require_once __DIR__ . '/../_bootstrap.php';

wajib_metode('GET');
$pdo = db();

$slugTopik = trim((string)($_GET['slug'] ?? ''));
$slugKelas = trim((string)($_GET['kelas'] ?? ''));
if ($slugTopik === '' || $slugKelas === '') {
    json_error(422, 'VALIDASI', 'Parameter slug dan kelas wajib diisi.');
}

$stmt = $pdo->prepare(
    "SELECT t.*, k.nama AS kelas_nama, k.slug AS kelas_slug,
            m.nama AS mapel_nama, m.slug AS mapel_slug,
            j.nama AS jenjang_nama, j.slug AS jenjang_slug
     FROM topik t
     JOIN kelas k ON k.id = t.kelas_id
     JOIN mapel m ON m.id = k.mapel_id
     JOIN jenjang j ON j.id = m.jenjang_id
     WHERE t.slug = :slug AND k.slug = :kelas AND t.status = 'published'"
);
$stmt->execute(['slug' => $slugTopik, 'kelas' => $slugKelas]);
$topik = $stmt->fetch();

if (!$topik) {
    json_error(404, 'TIDAK_DITEMUKAN', 'Topik tidak ditemukan.');
}

try {
    $pdo->prepare('UPDATE topik SET dilihat = dilihat + 1 WHERE id = :id')->execute(['id' => $topik['id']]);
} catch (Throwable $e) {
    // abaikan — statistik bukan kebutuhan kritis
}

$fileUrl = null;
if ($topik['tipe'] === 'latihan' && $topik['file_path'] && file_exists(UPLOAD_DIR_QUIZZES . $topik['file_path'])) {
    $fileUrl = url_publik(ltrim(UPLOAD_URL_QUIZZES, '/') . $topik['file_path']);
}

json_ok([
    'topik' => [
        'id' => (int)$topik['id'],
        'tipe' => $topik['tipe'],
        'judul' => format_judul($topik['judul']),
        'slug' => $topik['slug'],
        'deskripsi' => $topik['deskripsi'],
        'tingkat' => $topik['tingkat'],
        'jumlah_soal' => $topik['jumlah_soal'] !== null ? (int)$topik['jumlah_soal'] : null,
        'emoji' => $topik['emoji'],
        'dilihat' => (int)$topik['dilihat'] + 1,
        'file_url' => $fileUrl,
        'konten' => $topik['tipe'] === 'materi' ? ($topik['konten'] ?: '') : null,
        'kelas_nama' => $topik['kelas_nama'],
        'kelas_slug' => $topik['kelas_slug'],
        'mapel_nama' => $topik['mapel_nama'],
        'mapel_slug' => $topik['mapel_slug'],
        'jenjang_nama' => $topik['jenjang_nama'],
        'jenjang_slug' => $topik['jenjang_slug'],
    ],
]);
