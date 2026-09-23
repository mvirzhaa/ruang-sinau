<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (admin_login_sedang()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf_valid();

    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $hasil = coba_login($username, $password);
        if ($hasil['sukses']) {
            redirect('index.php');
        }
        $error = $hasil['pesan'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk Admin — <?= h(APP_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= h(url_publik('assets/css/admin.css')) ?>">
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="mark">S</div>
        <h1>Masuk ke Panel Admin</h1>
        <p class="sub"><?= h(APP_NAME) ?> — khusus pengelola konten</p>

        <?php if ($error): ?>
            <div class="flash error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <div class="form-row">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:4px;">Masuk</button>
        </form>

        <p style="margin-top:20px; text-align:center;">
            <a href="<?= h(url_publik('index.php')) ?>" style="color:var(--muted); font-size:13.5px; font-weight:600;">← Kembali ke situs</a>
        </p>
    </div>
</div>
</body>
</html>
