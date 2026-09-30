<?php
require_once __DIR__ . '/_bootstrap.php';

wajib_metode('GET');
$pdo = db();

$slugKelas = trim((string)($_GET['kelas'] ?? ''));
$slugMapel = trim((string)($_GET['mapel'] ?? ''));
$slugJenjang = trim((string)($_GET['jenjang'] ?? ''));
$filterTipe = in_array($_GET['tipe'] ?? '', ['latihan', 'materi'], true) ? $_GET['tipe'] : '';

if ($slugKelas === '' || $slugMapel === '' || $slugJenjang === '') {
    json_error(422, 'VALIDASI', 'Parameter kelas, mapel, dan jenjang wajib diisi.');
}

$stmt = $pdo->prepare(
    'SELECT k.*, m.nama AS mapel_nama, m.slug AS mapel_slug, j.nama AS jenjang_nama, j.slug AS jenjang_slug
     FROM kelas k JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id
     WHERE k.slug = :slug AND m.slug = :mapel AND j.slug = :jenjang'
);
$stmt->execute(['slug' => $slugKelas, 'mapel' => $slugMapel, 'jenjang' => $slugJenjang]);
$kelas = $stmt->fetch();
if (!$kelas) {
    json_error(404, 'TIDAK_DITEMUKAN', 'Kelas tidak ditemukan.');
}

$sql = "SELECT id, tipe, judul, slug, deskripsi, tingkat, jumlah_soal, emoji, dilihat
        FROM topik WHERE kelas_id = :kelas_id AND status = 'published'";
$params = ['kelas_id' => $kelas['id']];
if ($filterTipe) {
    $sql .= ' AND tipe = :tipe';
    $params['tipe'] = $filterTipe;
}
$sql .= ' ORDER BY tipe DESC, judul';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$topikList = array_map(function (array $t): array {
    $t['judul'] = format_judul($t['judul']);
    $t['id'] = (int)$t['id'];
    $t['jumlah_soal'] = $t['jumlah_soal'] !== null ? (int)$t['jumlah_soal'] : null;
    $t['dilihat'] = (int)$t['dilihat'];
    return $t;
}, $stmt->fetchAll());

json_ok([
    'kelas' => [
        'id' => (int)$kelas['id'], 'nama' => $kelas['nama'], 'slug' => $kelas['slug'],
        'mapel_nama' => $kelas['mapel_nama'], 'mapel_slug' => $kelas['mapel_slug'],
        'jenjang_nama' => $kelas['jenjang_nama'], 'jenjang_slug' => $kelas['jenjang_slug'],
    ],
    'topik' => $topikList,
]);
