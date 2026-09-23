<?php
require_once __DIR__ . '/includes/functions.php';

$pdo = db();

$jenjangList = $pdo->query('SELECT * FROM jenjang ORDER BY urutan, nama')->fetchAll();

$warnaJenjang = ['primary', 'accent', 'teal'];

$terbaru = $pdo->query(
    "SELECT t.*, k.nama AS kelas_nama, k.slug AS kelas_slug,
            m.nama AS mapel_nama, m.slug AS mapel_slug,
            j.nama AS jenjang_nama, j.slug AS jenjang_slug
     FROM topik t
     JOIN kelas k ON k.id = t.kelas_id
     JOIN mapel m ON m.id = k.mapel_id
     JOIN jenjang j ON j.id = m.jenjang_id
     WHERE t.status = 'published'
     ORDER BY t.created_at DESC
     LIMIT 8"
)->fetchAll();

$terpopuler = $pdo->query(
    "SELECT t.*, k.nama AS kelas_nama, k.slug AS kelas_slug,
            m.nama AS mapel_nama, m.slug AS mapel_slug,
            j.nama AS jenjang_nama, j.slug AS jenjang_slug
     FROM topik t
     JOIN kelas k ON k.id = t.kelas_id
     JOIN mapel m ON m.id = k.mapel_id
     JOIN jenjang j ON j.id = m.jenjang_id
     WHERE t.status = 'published'
     ORDER BY t.dilihat DESC, t.created_at DESC
     LIMIT 4"
)->fetchAll();

$totalTopik = (int)$pdo->query("SELECT COUNT(*) AS n FROM topik WHERE status='published'")->fetch()['n'];
$totalMapel = (int)$pdo->query("SELECT COUNT(*) AS n FROM mapel")->fetch()['n'];

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <p class="hero-eyebrow">Gratis &amp; terbuka untuk siapa saja</p>
    <h1>Belajar dan latihan soal, tanpa batas jenjang atau biaya.</h1>
    <p class="lead">
        <?= h(APP_NAME) ?> mengumpulkan latihan soal interaktif dan materi belajar dari SD sampai SMA
        dalam satu tempat — siap dipakai siapa saja yang ingin berlatih, kapan pun dan di mana pun.
    </p>

    <div class="jenjang-picker">
        <?php foreach ($jenjangList as $i => $j): ?>
        <a href="<?= h(url_publik('jenjang.php?slug=' . urlencode($j['slug']))) ?>"
           class="jenjang-card" data-warna="<?= h($warnaJenjang[$i % 3]) ?>">
            <span class="angka">Jenjang</span>
            <h3><?= h($j['nama']) ?></h3>
            <p><?= h($j['deskripsi'] ?: 'Lihat mata pelajaran yang tersedia') ?></p>
        </a>
        <?php endforeach; ?>
        <?php if (!$jenjangList): ?>
            <div class="kosong">Belum ada jenjang. Tambahkan lewat panel admin.</div>
        <?php endif; ?>
    </div>
</section>

<?php if ($terpopuler): ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2>Paling banyak dipelajari</h2>
            <p>Latihan dan materi yang paling sering dibuka</p>
        </div>
        <a href="<?= h(url_publik('katalog.php')) ?>" class="lihat-semua">Lihat semua →</a>
    </div>
    <div class="grid-kartu">
        <?php foreach ($terpopuler as $t): ?>
            <?php include __DIR__ . '/includes/_kartu_topik.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($terbaru): ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2>Baru ditambahkan</h2>
            <p>Konten terbaru dari pengelola <?= h(APP_NAME) ?></p>
        </div>
        <a href="<?= h(url_publik('katalog.php')) ?>" class="lihat-semua">Lihat semua →</a>
    </div>
    <div class="grid-kartu">
        <?php foreach (array_slice($terbaru, 0, 8) as $t): ?>
            <?php include __DIR__ . '/includes/_kartu_topik.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php else: ?>
<section class="section">
    <div class="kosong">
        <h3>Belum ada konten yang dipublikasikan</h3>
        <p>Masuk ke panel admin untuk menambahkan mata pelajaran, kelas, dan latihan soal pertama Anda.</p>
    </div>
</section>
<?php endif; ?>

<section class="section" style="padding-bottom:56px;">
    <div class="kosong" style="text-align:left; display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap;">
        <div>
            <h3 style="margin-bottom:4px;">Punya materi atau latihan soal sendiri?</h3>
            <p style="margin:0;">Saat ini tersedia <?= (int)$totalTopik ?> konten di <?= (int)$totalMapel ?> mata pelajaran. Tim pengelola dapat menambah lebih banyak lagi lewat panel admin.</p>
        </div>
        <a href="<?= h(url_publik('admin/login.php')) ?>" class="btn btn-outline">Masuk sebagai pengelola</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
