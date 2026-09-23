<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

$stat = [
    'jenjang' => (int)$pdo->query('SELECT COUNT(*) AS n FROM jenjang')->fetch()['n'],
    'mapel'   => (int)$pdo->query('SELECT COUNT(*) AS n FROM mapel')->fetch()['n'],
    'kelas'   => (int)$pdo->query('SELECT COUNT(*) AS n FROM kelas')->fetch()['n'],
    'topik'   => (int)$pdo->query("SELECT COUNT(*) AS n FROM topik")->fetch()['n'],
    'published' => (int)$pdo->query("SELECT COUNT(*) AS n FROM topik WHERE status='published'")->fetch()['n'],
    'draft'   => (int)$pdo->query("SELECT COUNT(*) AS n FROM topik WHERE status='draft'")->fetch()['n'],
    'dilihat' => (int)$pdo->query('SELECT COALESCE(SUM(dilihat),0) AS n FROM topik')->fetch()['n'],
];

$terpopuler = $pdo->query(
    "SELECT t.judul, t.slug, t.dilihat, t.tipe, k.slug AS kelas_slug, m.nama AS mapel_nama
     FROM topik t JOIN kelas k ON k.id = t.kelas_id JOIN mapel m ON m.id = k.mapel_id
     WHERE t.status = 'published' ORDER BY t.dilihat DESC LIMIT 6"
)->fetchAll();

$terbaru = $pdo->query(
    "SELECT t.judul, t.status, t.tipe, t.created_at, m.nama AS mapel_nama
     FROM topik t JOIN kelas k ON k.id = t.kelas_id JOIN mapel m ON m.id = k.mapel_id
     ORDER BY t.created_at DESC LIMIT 6"
)->fetchAll();

$judul_admin = 'Dasbor';
$menuAktif = 'dasbor';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="stat-grid">
    <div class="stat-card"><div class="angka"><?= $stat['topik'] ?></div><div class="label">Total Konten</div></div>
    <div class="stat-card"><div class="angka"><?= $stat['published'] ?></div><div class="label">Dipublikasikan</div></div>
    <div class="stat-card"><div class="angka"><?= $stat['draft'] ?></div><div class="label">Draf</div></div>
    <div class="stat-card"><div class="angka"><?= $stat['dilihat'] ?></div><div class="label">Total Dilihat</div></div>
    <div class="stat-card"><div class="angka"><?= $stat['mapel'] ?></div><div class="label">Mata Pelajaran</div></div>
    <div class="stat-card"><div class="angka"><?= $stat['kelas'] ?></div><div class="label">Kelas</div></div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Mulai cepat</h2>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="topik_form.php" class="btn btn-primary">+ Tambah Latihan / Materi</a>
        <a href="jenjang.php" class="btn btn-outline">Kelola Jenjang</a>
        <a href="mapel.php" class="btn btn-outline">Kelola Mata Pelajaran</a>
        <a href="kelas.php" class="btn btn-outline">Kelola Kelas</a>
    </div>
</div>

<div class="form-2col">
    <div class="panel">
        <div class="panel-head"><h2>Paling banyak dilihat</h2></div>
        <?php if ($terpopuler): ?>
        <table class="tabel">
            <?php foreach ($terpopuler as $t): ?>
            <tr>
                <td>
                    <strong><?= h($t['judul']) ?></strong><br>
                    <span style="color:var(--muted); font-size:12.5px;"><?= h($t['mapel_nama']) ?></span>
                </td>
                <td style="text-align:right; white-space:nowrap;">👁 <?= (int)$t['dilihat'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
            <p class="kosong-admin">Belum ada data.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Baru ditambahkan</h2></div>
        <?php if ($terbaru): ?>
        <table class="tabel">
            <?php foreach ($terbaru as $t): ?>
            <tr>
                <td>
                    <strong><?= h($t['judul']) ?></strong><br>
                    <span style="color:var(--muted); font-size:12.5px;"><?= h($t['mapel_nama']) ?> · <?= waktu_relatif($t['created_at']) ?></span>
                </td>
                <td style="text-align:right;">
                    <span class="status-pill <?= h($t['status']) ?>"><?= $t['status'] === 'published' ? 'Terbit' : 'Draf' ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
            <p class="kosong-admin">Belum ada data.</p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
