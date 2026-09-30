<?php
$judul_admin = $judul_admin ?? 'Dasbor';
$menuAktif = $menuAktif ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($judul_admin) ?> — Admin <?= h(APP_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= h(url_publik('assets/css/admin.css')) ?>">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar">
        <div class="brand">
            <span class="mark">S</span>
            <span><?= h(APP_NAME) ?></span>
        </div>
        <nav>
            <a href="index.php" class="<?= $menuAktif === 'dasbor' ? 'aktif' : '' ?>">📊 Dasbor</a>

            <div class="grup-label">Konten</div>
            <a href="topik.php" class="<?= $menuAktif === 'topik' ? 'aktif' : '' ?>">📝 Latihan &amp; Materi</a>
            <a href="impor_massal.php" class="<?= $menuAktif === 'impor' ? 'aktif' : '' ?>">📦 Impor Massal</a>
            <a href="jenjang.php" class="<?= $menuAktif === 'jenjang' ? 'aktif' : '' ?>">🏫 Jenjang</a>
            <a href="mapel.php" class="<?= $menuAktif === 'mapel' ? 'aktif' : '' ?>">📘 Mata Pelajaran</a>
            <a href="kelas.php" class="<?= $menuAktif === 'kelas' ? 'aktif' : '' ?>">🎓 Kelas</a>

            <div class="grup-label">Akun</div>
            <a href="profil.php" class="<?= $menuAktif === 'profil' ? 'aktif' : '' ?>">👤 Profil Saya</a>
            <a href="pengguna_app.php" class="<?= $menuAktif === 'pengguna_app' ? 'aktif' : '' ?>">📱 Pengguna Aplikasi</a>
            <?php if ($admin['peran'] === 'super_admin'): ?>
            <a href="users.php" class="<?= $menuAktif === 'users' ? 'aktif' : '' ?>">🔑 Pengguna Admin</a>
            <a href="log.php" class="<?= $menuAktif === 'log' ? 'aktif' : '' ?>">🛡️ Log Aktivitas</a>
            <?php endif; ?>
        </nav>
        <div class="lihat-situs">
            <a href="<?= h(url_publik('index.php')) ?>" target="_blank" class="btn btn-outline btn-sm" style="width:100%;">↗ Lihat Situs</a>
        </div>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <h1><?= h($judul_admin) ?></h1>
            <div class="admin-user">
                <div class="avatar"><?= h(strtoupper(substr($admin['nama'], 0, 1))) ?></div>
                <span><?= h($admin['nama']) ?></span>
                <a href="logout.php" class="btn btn-outline btn-sm">Keluar</a>
            </div>
        </div>
        <div class="admin-content">
            <?php foreach (ambil_flash() as $f): ?>
                <div class="flash <?= h($f['tipe']) ?>"><?= h($f['pesan']) ?></div>
            <?php endforeach; ?>
