<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$slugKelas = trim($_GET['slug'] ?? '');
$slugMapel = trim($_GET['mapel'] ?? '');
$slugJenjang = trim($_GET['jenjang'] ?? '');
$filterTipe = in_array($_GET['tipe'] ?? '', ['latihan', 'materi'], true) ? $_GET['tipe'] : '';

$stmt = $pdo->prepare(
    'SELECT k.*, m.nama AS mapel_nama, m.slug AS mapel_slug, m.emoji AS mapel_emoji,
            j.nama AS jenjang_nama, j.slug AS jenjang_slug
     FROM kelas k
     JOIN mapel m ON m.id = k.mapel_id
     JOIN jenjang j ON j.id = m.jenjang_id
     WHERE k.slug = :slug AND m.slug = :mapel AND j.slug = :jenjang'
);
$stmt->execute(['slug' => $slugKelas, 'mapel' => $slugMapel, 'jenjang' => $slugJenjang]);
$kelas = $stmt->fetch();

if (!$kelas) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$sql = "SELECT t.*, k.nama AS kelas_nama, k.slug AS kelas_slug,
               m.nama AS mapel_nama, m.slug AS mapel_slug,
               j.nama AS jenjang_nama, j.slug AS jenjang_slug
        FROM topik t
        JOIN kelas k ON k.id = t.kelas_id
        JOIN mapel m ON m.id = k.mapel_id
        JOIN jenjang j ON j.id = m.jenjang_id
        WHERE t.kelas_id = :kelas_id AND t.status = 'published'";
$params = ['kelas_id' => $kelas['id']];
if ($filterTipe) {
    $sql .= ' AND t.tipe = :tipe';
    $params['tipe'] = $filterTipe;
}
$sql .= ' ORDER BY t.tipe DESC, t.judul';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$topikList = $stmt->fetchAll();

$judul_halaman = $kelas['nama'] . ' — ' . $kelas['mapel_nama'] . ' — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
    <span class="pemisah">/</span>
    <a href="<?= h(url_publik('jenjang.php?slug=' . urlencode($kelas['jenjang_slug']))) ?>"><?= h($kelas['jenjang_nama']) ?></a>
    <span class="pemisah">/</span>
    <a href="<?= h(url_publik('mapel.php?slug=' . urlencode($kelas['mapel_slug']) . '&jenjang=' . urlencode($kelas['jenjang_slug']))) ?>"><?= h($kelas['mapel_nama']) ?></a>
    <span class="pemisah">/</span>
    <span class="sekarang"><?= h($kelas['nama']) ?></span>
</nav>

<div class="section-head">
    <div>
        <h2><?= h($kelas['mapel_emoji']) ?> <?= h($kelas['mapel_nama']) ?> — <?= h($kelas['nama']) ?></h2>
        <p><?= count($topikList) ?> konten tersedia untuk kelas ini</p>
    </div>
</div>

<form method="get" class="filter-bar">
    <input type="hidden" name="slug" value="<?= h($slugKelas) ?>">
    <input type="hidden" name="mapel" value="<?= h($slugMapel) ?>">
    <input type="hidden" name="jenjang" value="<?= h($slugJenjang) ?>">
    <select name="tipe" onchange="this.form.submit()">
        <option value="">Semua jenis</option>
        <option value="latihan" <?= $filterTipe === 'latihan' ? 'selected' : '' ?>>Latihan soal</option>
        <option value="materi" <?= $filterTipe === 'materi' ? 'selected' : '' ?>>Materi</option>
    </select>
</form>

<?php if ($topikList): ?>
<div class="grid-kartu">
    <?php foreach ($topikList as $t): ?>
        <?php include __DIR__ . '/includes/_kartu_topik.php'; ?>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="kosong">
        <h3>Belum ada konten</h3>
        <p>Pengelola belum menambahkan latihan soal atau materi untuk kelas ini.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
