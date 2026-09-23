<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$jenjangSlug = trim($_GET['jenjang'] ?? '');
$tipe = in_array($_GET['tipe'] ?? '', ['latihan', 'materi'], true) ? $_GET['tipe'] : '';
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$perHalaman = 18;

$where = ["t.status = 'published'"];
$params = [];

if ($jenjangSlug !== '') {
    $where[] = 'j.slug = :jenjang';
    $params['jenjang'] = $jenjangSlug;
}
if ($tipe !== '') {
    $where[] = 't.tipe = :tipe';
    $params['tipe'] = $tipe;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) AS n FROM topik t
     JOIN kelas k ON k.id = t.kelas_id JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id
     WHERE $whereSql"
);
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['n'];
$totalHalaman = max(1, (int)ceil($total / $perHalaman));
$halaman = min($halaman, $totalHalaman);
$offset = ($halaman - 1) * $perHalaman;

$sql = "SELECT t.*, k.nama AS kelas_nama, k.slug AS kelas_slug,
               m.nama AS mapel_nama, m.slug AS mapel_slug,
               j.nama AS jenjang_nama, j.slug AS jenjang_slug
        FROM topik t
        JOIN kelas k ON k.id = t.kelas_id JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id
        WHERE $whereSql
        ORDER BY t.created_at DESC
        LIMIT $perHalaman OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$topikList = $stmt->fetchAll();

$jenjangList = $pdo->query('SELECT * FROM jenjang ORDER BY urutan, nama')->fetchAll();

$judul_halaman = 'Jelajahi — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
    <span class="pemisah">/</span>
    <span class="sekarang">Jelajahi</span>
</nav>

<div class="section-head">
    <div>
        <h2>Jelajahi Semua Konten</h2>
        <p><?= (int)$total ?> latihan &amp; materi tersedia</p>
    </div>
</div>

<form method="get" class="filter-bar">
    <select name="jenjang" onchange="this.form.submit()">
        <option value="">Semua jenjang</option>
        <?php foreach ($jenjangList as $j): ?>
            <option value="<?= h($j['slug']) ?>" <?= $jenjangSlug === $j['slug'] ? 'selected' : '' ?>><?= h($j['nama']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="tipe" onchange="this.form.submit()">
        <option value="">Semua jenis</option>
        <option value="latihan" <?= $tipe === 'latihan' ? 'selected' : '' ?>>Latihan soal</option>
        <option value="materi" <?= $tipe === 'materi' ? 'selected' : '' ?>>Materi</option>
    </select>
</form>

<?php if ($topikList): ?>
<div class="grid-kartu">
    <?php foreach ($topikList as $t): ?>
        <?php include __DIR__ . '/includes/_kartu_topik.php'; ?>
    <?php endforeach; ?>
</div>

<?php if ($totalHalaman > 1): ?>
<div style="display:flex; gap:8px; justify-content:center; margin-top:28px;">
    <?php for ($p = 1; $p <= $totalHalaman; $p++): ?>
        <a href="?jenjang=<?= h(urlencode($jenjangSlug)) ?>&tipe=<?= h(urlencode($tipe)) ?>&halaman=<?= $p ?>"
           class="btn <?= $p === $halaman ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $p ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php else: ?>
    <div class="kosong">
        <h3>Tidak ada konten yang cocok</h3>
        <p>Coba ubah filter jenjang atau jenis konten.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
