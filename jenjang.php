<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$slug = trim($_GET['slug'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM jenjang WHERE slug = :slug');
$stmt->execute(['slug' => $slug]);
$jenjang = $stmt->fetch();

if (!$jenjang) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT m.*,
            (SELECT COUNT(*) FROM topik t JOIN kelas k ON k.id = t.kelas_id WHERE k.mapel_id = m.id AND t.status='published') AS jumlah_konten
     FROM mapel m
     WHERE m.jenjang_id = :id
     ORDER BY m.urutan, m.nama"
);
$stmt->execute(['id' => $jenjang['id']]);
$mapelList = $stmt->fetchAll();

$judul_halaman = $jenjang['nama'] . ' — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
    <span class="pemisah">/</span>
    <span class="sekarang"><?= h($jenjang['nama']) ?></span>
</nav>

<div class="section-head">
    <div>
        <h2>Mata Pelajaran — <?= h($jenjang['nama']) ?></h2>
        <p><?= h($jenjang['deskripsi'] ?: 'Pilih mata pelajaran untuk mulai berlatih') ?></p>
    </div>
</div>

<?php if ($mapelList): ?>
<div class="grid-kartu">
    <?php foreach ($mapelList as $m): ?>
        <a href="<?= h(url_publik('mapel.php?slug=' . urlencode($m['slug']) . '&jenjang=' . urlencode($jenjang['slug']))) ?>" class="kartu">
            <span class="emoji"><?= h($m['emoji'] ?: '📘') ?></span>
            <h3><?= h($m['nama']) ?></h3>
            <p class="ket"><?= (int)$m['jumlah_konten'] ?> konten tersedia</p>
        </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="kosong">
        <h3>Belum ada mata pelajaran</h3>
        <p>Pengelola belum menambahkan mata pelajaran untuk jenjang ini.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
