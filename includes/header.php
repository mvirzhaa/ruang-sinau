<?php
/**
 * Header situs publik. $judul_halaman & $deskripsi_halaman boleh
 * di-set oleh halaman pemanggil sebelum me-require file ini.
 */
$judul_halaman = $judul_halaman ?? APP_NAME . ' — Latihan Soal & Materi Belajar Gratis';
$deskripsi_halaman = $deskripsi_halaman ?? 'Latihan soal interaktif dan materi belajar gratis untuk SD, SMP, dan SMA. Terbuka untuk siapa saja yang ingin belajar.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($judul_halaman) ?></title>
<meta name="description" content="<?= h($deskripsi_halaman) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2222%22 fill=%22%232952E3%22/><text x=%2250%22 y=%2268%22 font-size=%2258%22 fill=%22white%22 text-anchor=%22middle%22 font-family=%22sans-serif%22 font-weight=%22800%22>S</text></svg>">
<link rel="stylesheet" href="<?= h(url_publik('assets/css/style.css')) ?>">
</head>
<body>

<header class="site-header">
    <div class="wrap">
        <a href="<?= h(url_publik('index.php')) ?>" class="brand">
            <span class="mark">S</span>
            <span><?= h(APP_NAME) ?></span>
        </a>

        <nav class="nav-main">
            <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
            <a href="<?= h(url_publik('katalog.php')) ?>">Jelajahi</a>
            <a href="<?= h(url_publik('tentang.php')) ?>">Tentang</a>
        </nav>

        <form action="<?= h(url_publik('cari.php')) ?>" method="get" class="search-mini">
            <input type="text" name="q" placeholder="Cari topik, mapel..." value="<?= h($_GET['q'] ?? '') ?>">
            <button type="submit" aria-label="Cari">🔍</button>
        </form>

        <button class="nav-toggle" onclick="document.querySelector('.nav-mobile').classList.toggle('terbuka')" aria-label="Menu">☰</button>
    </div>
    <div class="nav-mobile" style="display:none; border-top:1px solid var(--line);">
        <div class="wrap" style="display:flex; flex-direction:column; padding:10px 24px 16px; gap:4px;">
            <a href="<?= h(url_publik('index.php')) ?>" style="padding:8px 0; font-weight:600;">Beranda</a>
            <a href="<?= h(url_publik('katalog.php')) ?>" style="padding:8px 0; font-weight:600;">Jelajahi</a>
            <a href="<?= h(url_publik('tentang.php')) ?>" style="padding:8px 0; font-weight:600;">Tentang</a>
            <form action="<?= h(url_publik('cari.php')) ?>" method="get" style="margin-top:6px; display:flex; gap:8px;">
                <input type="text" name="q" placeholder="Cari..." style="flex:1; padding:9px 12px; border:1.5px solid var(--line); border-radius:8px;">
                <button type="submit" class="btn btn-primary btn-sm">Cari</button>
            </form>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.nav-toggle');
    var menu = document.querySelector('.nav-mobile');
    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        });
    }
});
</script>

<main>
<div class="wrap" style="padding-top:18px; padding-bottom:18px;">
<?php foreach (ambil_flash() as $f): ?>
    <div class="flash <?= h($f['tipe']) ?>"><?= h($f['pesan']) ?></div>
<?php endforeach; ?>
