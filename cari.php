<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$kataKunci = trim($_GET['q'] ?? '');
$hasil = [];

if ($kataKunci !== '') {
    $sql = "SELECT t.*, k.nama AS kelas_nama, k.slug AS kelas_slug,
                   m.nama AS mapel_nama, m.slug AS mapel_slug,
                   j.nama AS jenjang_nama, j.slug AS jenjang_slug,
                   MATCH(t.judul, t.deskripsi) AGAINST(:kata1 IN NATURAL LANGUAGE MODE) AS relevansi
            FROM topik t
            JOIN kelas k ON k.id = t.kelas_id JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id
            WHERE t.status = 'published'
              AND (MATCH(t.judul, t.deskripsi) AGAINST(:kata2 IN NATURAL LANGUAGE MODE)
                   OR t.judul LIKE :like1 OR m.nama LIKE :like2)
            ORDER BY relevansi DESC, t.judul
            LIMIT 40";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'kata1' => $kataKunci, 'kata2' => $kataKunci,
        'like1' => '%' . $kataKunci . '%', 'like2' => '%' . $kataKunci . '%',
    ]);
    $hasil = $stmt->fetchAll();
}

$judul_halaman = 'Cari: ' . $kataKunci . ' — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
    <span class="pemisah">/</span>
    <span class="sekarang">Hasil pencarian</span>
</nav>

<div class="section-head">
    <div>
        <h2>Hasil untuk "<?= h($kataKunci) ?>"</h2>
        <p><?= count($hasil) ?> hasil ditemukan</p>
    </div>
</div>

<?php if ($kataKunci === ''): ?>
    <div class="kosong"><h3>Ketik kata kunci untuk mulai mencari</h3></div>
<?php elseif ($hasil): ?>
    <div class="grid-kartu">
        <?php foreach ($hasil as $t): ?>
            <?php include __DIR__ . '/includes/_kartu_topik.php'; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="kosong">
        <h3>Tidak ditemukan</h3>
        <p>Coba kata kunci lain, atau jelajahi katalog kami.</p>
        <p style="margin-top:14px;"><a href="<?= h(url_publik('katalog.php')) ?>" class="btn btn-primary">Jelajahi katalog</a></p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
