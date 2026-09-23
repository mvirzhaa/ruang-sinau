<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$slugTopik = trim($_GET['slug'] ?? '');
$slugKelas = trim($_GET['kelas'] ?? '');

$stmt = $pdo->prepare(
    "SELECT t.*, k.nama AS kelas_nama, k.slug AS kelas_slug,
            m.nama AS mapel_nama, m.slug AS mapel_slug, m.emoji AS mapel_emoji,
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
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Hitung tampilan (best-effort, tidak menghentikan halaman jika gagal)
try {
    $upd = $pdo->prepare('UPDATE topik SET dilihat = dilihat + 1 WHERE id = :id');
    $upd->execute(['id' => $topik['id']]);
} catch (Throwable $e) {
    // abaikan — statistik bukan kebutuhan kritis
}

$judul_halaman = $topik['judul'] . ' — ' . APP_NAME;
$deskripsi_halaman = $topik['deskripsi'] ?: ($topik['judul'] . ' — ' . $topik['mapel_nama'] . ' ' . $topik['kelas_nama']);
require __DIR__ . '/includes/header.php';

$urlKelas = url_publik('kelas.php?slug=' . urlencode($topik['kelas_slug']) . '&mapel=' . urlencode($topik['mapel_slug']) . '&jenjang=' . urlencode($topik['jenjang_slug']));
?>

<nav class="breadcrumb">
    <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
    <span class="pemisah">/</span>
    <a href="<?= h(url_publik('jenjang.php?slug=' . urlencode($topik['jenjang_slug']))) ?>"><?= h($topik['jenjang_nama']) ?></a>
    <span class="pemisah">/</span>
    <a href="<?= h(url_publik('mapel.php?slug=' . urlencode($topik['mapel_slug']) . '&jenjang=' . urlencode($topik['jenjang_slug']))) ?>"><?= h($topik['mapel_nama']) ?></a>
    <span class="pemisah">/</span>
    <a href="<?= h($urlKelas) ?>"><?= h($topik['kelas_nama']) ?></a>
    <span class="pemisah">/</span>
    <span class="sekarang"><?= h($topik['judul']) ?></span>
</nav>

<div class="topik-header">
    <div class="meta">
        <?php if ($topik['tipe'] === 'materi'): ?>
            <span class="badge materi">📘 Materi</span>
        <?php else: ?>
            <span class="badge">📝 <?= (int)($topik['jumlah_soal'] ?? 0) ?: '—' ?> soal</span>
            <?php if (!empty($topik['tingkat'])): ?>
                <span class="badge <?= h($topik['tingkat']) ?>"><?= h(ucfirst($topik['tingkat'])) ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <span class="badge">👁 <?= (int)$topik['dilihat'] ?> kali dilihat</span>
    </div>
    <h1><?= h($topik['judul']) ?></h1>
    <?php if ($topik['deskripsi']): ?>
        <p><?= h($topik['deskripsi']) ?></p>
    <?php endif; ?>
</div>

<?php if ($topik['tipe'] === 'latihan'): ?>
    <?php if ($topik['file_path'] && file_exists(UPLOAD_DIR_QUIZZES . $topik['file_path'])): ?>
        <iframe
            class="frame-latihan"
            src="<?= h(url_publik(ltrim(UPLOAD_URL_QUIZZES, '/') . $topik['file_path'])) ?>"
            title="<?= h($topik['judul']) ?>"
            sandbox="allow-scripts allow-downloads allow-popups allow-modals allow-same-origin"
            loading="lazy">
        </iframe>
        <p style="color:var(--muted); font-size:13.5px; margin-top:10px;">
            Latihan tidak tampil dengan baik? <a href="<?= h(url_publik(ltrim(UPLOAD_URL_QUIZZES, '/') . $topik['file_path'])) ?>" target="_blank" rel="noopener" style="color:var(--primary); font-weight:700;">Buka di tab baru →</a>
        </p>
    <?php else: ?>
        <div class="kosong">
            <h3>Berkas latihan tidak ditemukan</h3>
            <p>Berkas latihan soal untuk topik ini belum diunggah atau sudah dipindahkan. Hubungi pengelola situs.</p>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="materi-konten">
        <?= $topik['konten'] ?: '<p>Belum ada konten materi.</p>' ?>
    </div>
<?php endif; ?>

<div style="margin-top:24px;">
    <a href="<?= h($urlKelas) ?>" class="btn btn-outline">← Kembali ke <?= h($topik['kelas_nama']) ?></a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
