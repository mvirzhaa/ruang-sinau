<?php
require_once __DIR__ . '/_bootstrap.php';

wajib_metode('GET');
$pdo = db();

$slugJenjang = trim((string)($_GET['jenjang'] ?? ''));
if ($slugJenjang === '') {
    json_error(422, 'VALIDASI', 'Parameter jenjang wajib diisi.');
}

$stmt = $pdo->prepare('SELECT * FROM jenjang WHERE slug = :slug');
$stmt->execute(['slug' => $slugJenjang]);
$jenjang = $stmt->fetch();
if (!$jenjang) {
    json_error(404, 'TIDAK_DITEMUKAN', 'Jenjang tidak ditemukan.');
}

$stmt = $pdo->prepare(
    "SELECT m.id, m.nama, m.slug, m.emoji, m.urutan,
            (SELECT COUNT(*) FROM topik t JOIN kelas k ON k.id = t.kelas_id WHERE k.mapel_id = m.id AND t.status='published') AS jumlah_konten
     FROM mapel m
     WHERE m.jenjang_id = :id
     ORDER BY m.urutan, m.nama"
);
$stmt->execute(['id' => $jenjang['id']]);
$mapelList = $stmt->fetchAll();

json_ok([
    'jenjang' => ['id' => (int)$jenjang['id'], 'nama' => $jenjang['nama'], 'slug' => $jenjang['slug']],
    'mapel' => $mapelList,
]);
