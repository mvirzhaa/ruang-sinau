<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf_valid();

    $nama = trim($_POST['nama'] ?? '');
    $passwordLama = (string)($_POST['password_lama'] ?? '');
    $passwordBaru = (string)($_POST['password_baru'] ?? '');
    $passwordUlang = (string)($_POST['password_ulang'] ?? '');

    if ($nama === '') {
        set_flash('error', 'Nama wajib diisi.');
        redirect('profil.php');
    }

    $updateNama = $pdo->prepare('UPDATE admin_users SET nama = :nama WHERE id = :id');
    $updateNama->execute(['nama' => $nama, 'id' => $admin['id']]);

    if ($passwordBaru !== '' || $passwordLama !== '') {
        $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => $admin['id']]);
        $hashSaatIni = $stmt->fetch()['password_hash'];

        if (!password_verify($passwordLama, $hashSaatIni)) {
            set_flash('error', 'Password lama tidak sesuai. Nama tetap tersimpan, namun password tidak diubah.');
            redirect('profil.php');
        } elseif (strlen($passwordBaru) < 8) {
            set_flash('error', 'Password baru minimal 8 karakter.');
            redirect('profil.php');
        } elseif ($passwordBaru !== $passwordUlang) {
            set_flash('error', 'Konfirmasi password baru tidak cocok.');
            redirect('profil.php');
        } else {
            $upd = $pdo->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
            $upd->execute(['h' => password_hash($passwordBaru, PASSWORD_DEFAULT), 'id' => $admin['id']]);
            catat_log($admin['id'], 'ubah_password_sendiri', '');
            set_flash('sukses', 'Nama dan password berhasil diperbarui.');
            redirect('profil.php');
        }
    }

    catat_log($admin['id'], 'ubah_profil_sendiri', $nama);
    set_flash('sukses', 'Profil berhasil diperbarui.');
    redirect('profil.php');
}

$judul_admin = 'Profil Saya';
$menuAktif = 'profil';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="panel" style="max-width:480px;">
    <div class="panel-head"><h2>Profil Saya</h2></div>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="form-row">
            <label for="nama">Nama Lengkap</label>
            <input type="text" id="nama" name="nama" required value="<?= h($admin['nama']) ?>">
        </div>
        <div class="form-row">
            <label>Username</label>
            <input type="text" value="<?= h($admin['username']) ?>" disabled style="background:var(--bg); color:var(--muted);">
            <span class="bantuan">Username tidak dapat diubah sendiri. Hubungi Super Admin bila perlu.</span>
        </div>

        <hr style="border:none; border-top:1px solid var(--line); margin:6px 0;">
        <p style="margin:0; font-weight:700; font-size:14px;">Ubah Password (opsional)</p>

        <div class="form-row">
            <label for="password_lama">Password saat ini</label>
            <input type="password" id="password_lama" name="password_lama">
        </div>
        <div class="form-2col">
            <div class="form-row">
                <label for="password_baru">Password baru</label>
                <input type="password" id="password_baru" name="password_baru" minlength="8">
            </div>
            <div class="form-row">
                <label for="password_ulang">Ulangi password baru</label>
                <input type="password" id="password_ulang" name="password_ulang" minlength="8">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
