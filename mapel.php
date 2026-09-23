<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$slugMapel = trim($_GET['slug'] ?? '');
$slugJenjang = trim($_GET['jenjang'] ?? '');

$stmt = $pdo->prepare(
    'SELECT m.*, j.nama AS jenjang_nama, j.slug AS jenjang_slug
     FROM mapel m JOIN jenjang j ON j.id = m.jenjang_id
     WHERE m.slug = :slug AND j.slug = :jenjang'
);
$stmt->execute(['slug' => $slugMapel, 'jenjang' => $slugJenjang]);
$mapel = $stmt->fetch();

if (!$mapel) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT k.*,
            (SELECT COUNT(*) FROM topik t WHERE t.kelas_id = k.id AND t.status='published') AS jumlah_konten
     FROM kelas k WHERE k.mapel_id = :id ORDER BY k.urutan, k.nama"
);
$stmt->execute(['id' => $mapel['id']]);
$kelasList = $stmt->fetchAll();

$judul_halaman = $mapel['nama'] . ' ' . $mapel['jenjang_nama'] . ' — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
    <span class="pemisah">/</span>
    <a href="<?= h(url_publik('jenjang.php?slug=' . urlencode($mapel['jenjang_slug']))) ?>"><?= h($mapel['jenjang_nama']) ?></a>
    <span class="pemisah">/</span>
    <span class="sekarang"><?= h($mapel['nama']) ?></span>
</nav>

<div class="section-head">
    <div>
        <h2><?= h($mapel['emoji']) ?> <?= h($mapel['nama']) ?></h2>
        <p>Pilih kelas untuk melihat latihan soal dan materi</p>
    </div>
</div>

<?php if ($kelasList): ?>
<div class="grid-kartu">
    <?php foreach ($kelasList as $k): ?>
        <a href="<?= h(url_publik('kelas.php?slug=' . urlencode($k['slug']) . '&mapel=' . urlencode($mapel['slug']) . '&jenjang=' . urlencode($mapel['jenjang_slug']))) ?>" class="kartu">
            <span class="emoji">🎓</span>
            <h3><?= h($k['nama']) ?></h3>
            <p class="ket"><?= (int)$k['jumlah_konten'] ?> konten tersedia</p>
        </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="kosong">
        <h3>Belum ada kelas</h3>
        <p>Pengelola belum menambahkan kelas untuk mata pelajaran ini.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
