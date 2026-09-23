<?php
require_once __DIR__ . '/includes/functions.php';
$judul_halaman = 'Halaman Tidak Ditemukan — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="kosong" style="padding:80px 24px;">
    <h3>404 — Halaman tidak ditemukan</h3>
    <p>Halaman yang Anda cari mungkin sudah dipindahkan atau tidak pernah ada.</p>
    <p style="margin-top:14px;"><a href="<?= h(url_publik('index.php')) ?>" class="btn btn-primary">Kembali ke Beranda</a></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
