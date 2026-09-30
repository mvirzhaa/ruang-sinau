<?php
require_once __DIR__ . '/_bootstrap.php';

wajib_metode('GET');
$pdo = db();

$kataKunci = trim((string)($_GET['q'] ?? ''));
$hasil = [];

if ($kataKunci !== '') {
    $sql = "SELECT t.id, t.tipe, t.judul, t.slug, t.deskripsi, t.tingkat, t.jumlah_soal, t.emoji,
                   k.slug AS kelas_slug, k.nama AS kelas_nama,
                   m.nama AS mapel_nama, j.nama AS jenjang_nama,
                   MATCH(t.judul, t.deskripsi) AGAINST(:kata IN NATURAL LANGUAGE MODE) AS relevansi
            FROM topik t
            JOIN kelas k ON k.id = t.kelas_id JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id
            WHERE t.status = 'published'
              AND (MATCH(t.judul, t.deskripsi) AGAINST(:kata IN NATURAL LANGUAGE MODE)
                   OR t.judul LIKE :like OR m.nama LIKE :like)
            ORDER BY relevansi DESC, t.judul
            LIMIT 40";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['kata' => $kataKunci, 'like' => '%' . $kataKunci . '%']);
    $hasil = array_map(function (array $t): array {
        $t['id'] = (int)$t['id'];
        $t['judul'] = format_judul($t['judul']);
        $t['jumlah_soal'] = $t['jumlah_soal'] !== null ? (int)$t['jumlah_soal'] : null;
        unset($t['relevansi']);
        return $t;
    }, $stmt->fetchAll());
}

json_ok(['kata_kunci' => $kataKunci, 'hasil' => $hasil]);
