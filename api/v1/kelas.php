<?php
require_once __DIR__ . '/_bootstrap.php';

wajib_metode('GET');
$pdo = db();

$slugMapel = trim((string)($_GET['mapel'] ?? ''));
$slugJenjang = trim((string)($_GET['jenjang'] ?? ''));
if ($slugMapel === '' || $slugJenjang === '') {
    json_error(422, 'VALIDASI', 'Parameter mapel dan jenjang wajib diisi.');
}

$stmt = $pdo->prepare(
    'SELECT m.*, j.nama AS jenjang_nama, j.slug AS jenjang_slug
     FROM mapel m JOIN jenjang j ON j.id = m.jenjang_id
     WHERE m.slug = :slug AND j.slug = :jenjang'
);
$stmt->execute(['slug' => $slugMapel, 'jenjang' => $slugJenjang]);
$mapel = $stmt->fetch();
if (!$mapel) {
    json_error(404, 'TIDAK_DITEMUKAN', 'Mata pelajaran tidak ditemukan.');
}

$stmt = $pdo->prepare(
    "SELECT k.id, k.nama, k.slug, k.urutan,
            (SELECT COUNT(*) FROM topik t WHERE t.kelas_id = k.id AND t.status='published') AS jumlah_konten
     FROM kelas k WHERE k.mapel_id = :id ORDER BY k.urutan, k.nama"
);
$stmt->execute(['id' => $mapel['id']]);
$kelasList = $stmt->fetchAll();

json_ok([
    'mapel' => [
        'id' => (int)$mapel['id'], 'nama' => $mapel['nama'], 'slug' => $mapel['slug'], 'emoji' => $mapel['emoji'],
        'jenjang_nama' => $mapel['jenjang_nama'], 'jenjang_slug' => $mapel['jenjang_slug'],
    ],
    'kelas' => $kelasList,
]);
