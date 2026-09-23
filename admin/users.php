<?php
require_once __DIR__ . '/includes/bootstrap.php';
wajib_super_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    wajib_csrf_valid();

    $id = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $peran = ($_POST['peran'] ?? '') === 'super_admin' ? 'super_admin' : 'admin';
    $password = (string)($_POST['password'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if ($username === '' || $nama === '') {
        set_flash('error', 'Username dan nama wajib diisi.');
        redirect('users.php');
    }
    if (!$id && $password === '') {
        set_flash('error', 'Password wajib diisi untuk pengguna baru.');
        redirect('users.php');
    }
    if ($password !== '' && strlen($password) < 8) {
        set_flash('error', 'Password minimal 8 karakter.');
        redirect('users.php');
    }

    $cekUnik = $pdo->prepare('SELECT id FROM admin_users WHERE username = :u AND id != :id');
    $cekUnik->execute(['u' => $username, 'id' => $id]);
    if ($cekUnik->fetch()) {
        set_flash('error', 'Username sudah digunakan.');
        redirect('users.php');
    }

    if ($id > 0) {
        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE admin_users SET username=:u, nama=:n, peran=:p, aktif=:a, password_hash=:h WHERE id=:id');
            $stmt->execute(['u' => $username, 'n' => $nama, 'p' => $peran, 'a' => $aktif, 'h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE admin_users SET username=:u, nama=:n, peran=:p, aktif=:a WHERE id=:id');
            $stmt->execute(['u' => $username, 'n' => $nama, 'p' => $peran, 'a' => $aktif, 'id' => $id]);
        }
        catat_log($admin['id'], 'ubah_pengguna_admin', $username);
        set_flash('sukses', 'Pengguna "' . $nama . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, nama, peran, aktif, password_hash) VALUES (:u, :n, :p, :a, :h)');
        $stmt->execute(['u' => $username, 'n' => $nama, 'p' => $peran, 'a' => $aktif, 'h' => password_hash($password, PASSWORD_DEFAULT)]);
        catat_log($admin['id'], 'tambah_pengguna_admin', $username);
        set_flash('sukses', 'Pengguna "' . $nama . '" berhasil ditambahkan.');
    }
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$admin['id']) {
        set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        redirect('users.php');
    }
    $cek = $pdo->prepare('SELECT username FROM admin_users WHERE id = :id');
    $cek->execute(['id' => $id]);
    $row = $cek->fetch();
    if ($row) {
        $pdo->prepare('DELETE FROM admin_users WHERE id = :id')->execute(['id' => $id]);
        catat_log($admin['id'], 'hapus_pengguna_admin', $row['username']);
        set_flash('sukses', 'Pengguna "' . $row['username'] . '" berhasil dihapus.');
    }
    redirect('users.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$dataEdit = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $dataEdit = $stmt->fetch();
}

$daftarUser = $pdo->query('SELECT * FROM admin_users ORDER BY created_at')->fetchAll();

$judul_admin = 'Pengguna Admin';
$menuAktif = 'users';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="form-2col">
    <div class="panel">
        <div class="panel-head"><h2>Daftar Pengguna</h2></div>
        <table class="tabel">
            <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($daftarUser as $u): ?>
                <tr>
                    <td><?= h($u['nama']) ?></td>
                    <td><?= h($u['username']) ?></td>
                    <td><?= $u['peran'] === 'super_admin' ? 'Super Admin' : 'Admin' ?></td>
                    <td><span class="status-pill <?= $u['aktif'] ? 'published' : 'draft' ?>"><?= $u['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td class="aksi">
                        <a href="?edit=<?= (int)$u['id'] ?>" class="btn btn-outline btn-sm">Ubah</a>
                        <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus pengguna &quot;<?= h(addslashes($u['username'])) ?>&quot;?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <div class="panel-head"><h2><?= $dataEdit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></h2></div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int)($dataEdit['id'] ?? 0) ?>">
            <div class="form-row">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" required value="<?= h($dataEdit['nama'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?= h($dataEdit['username'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="password">Password <?= $dataEdit ? '(kosongkan jika tidak diubah)' : '' ?></label>
                <input type="password" id="password" name="password" minlength="8" <?= $dataEdit ? '' : 'required' ?>>
                <span class="bantuan">Minimal 8 karakter.</span>
            </div>
            <div class="form-row">
                <label for="peran">Peran</label>
                <select id="peran" name="peran">
                    <option value="admin" <?= ($dataEdit['peran'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin (kelola konten)</option>
                    <option value="super_admin" <?= ($dataEdit['peran'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin (akses penuh)</option>
                </select>
            </div>
            <div class="form-row">
                <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
                    <input type="checkbox" name="aktif" value="1" style="width:auto;" <?= ($dataEdit['aktif'] ?? 1) ? 'checked' : '' ?>>
                    Akun aktif (bisa login)
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $dataEdit ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></button>
                <?php if ($dataEdit): ?><a href="users.php" class="btn btn-outline">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
