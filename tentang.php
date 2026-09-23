<?php
require_once __DIR__ . '/includes/functions.php';
$judul_halaman = 'Tentang — ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= h(url_publik('index.php')) ?>">Beranda</a>
    <span class="pemisah">/</span>
    <span class="sekarang">Tentang</span>
</nav>

<div class="materi-konten">
    <h1 style="margin-top:0;">Tentang <?= h(APP_NAME) ?></h1>
    <p><?= h(APP_NAME) ?> adalah ruang belajar terbuka yang menyediakan latihan soal interaktif dan
       materi pelajaran secara gratis, untuk siapa saja yang ingin berlatih — mulai dari siswa SD,
       SMP, SMA, hingga siapa pun yang sedang belajar kembali.</p>

    <h3>Bagaimana cara kerjanya?</h3>
    <p>Setiap latihan soal dan materi dikelompokkan berdasarkan jenjang, mata pelajaran, dan kelas,
       supaya mudah ditemukan. Latihan soal bersifat interaktif — langsung ada penilaian dan
       sertifikat di akhir sesi — dan bisa dikerjakan berulang kali tanpa batas.</p>

    <h3>Ingin berkontribusi?</h3>
    <p>Situs ini dikelola dan terus diperluas oleh timnya. Jika Anda ingin menambahkan materi atau
       latihan soal, hubungi pengelola situs.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
