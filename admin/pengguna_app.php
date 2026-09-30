<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../includes/app_auth.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    wajib_csrf_valid();

    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $email = app_normalisasi_email($_POST['email'] ?? '');
    $status = ($_POST['status'] ?? '') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $password = (string)($_POST['password'] ?? '');

    if ($nama === '' || $email === '') {
        set_flash('error', 'Nama dan email wajib diisi.');
        redirect('pengguna_app.php');
    }
    if (!app_email_valid($email)) {
        set_flash('error', 'Format email tidak valid.');
        redirect('pengguna_app.php');
    }
    if (!$id && $password === '') {
        set_flash('error', 'Password wajib diisi untuk pengguna baru.');
        redirect('pengguna_app.php');
    }
    if ($password !== '' && !app_password_valid($password)) {
        set_flash('error', 'Password minimal 8 karakter.');
        redirect('pengguna_app.php');
    }

    $cekUnik = $pdo->prepare('SELECT id FROM app_users WHERE email = :e AND id != :id');
    $cekUnik->execute(['e' => $email, 'id' => $id]);
    if ($cekUnik->fetch()) {
        set_flash('error', 'Email sudah digunakan pengguna lain.');
        redirect('pengguna_app.php');
    }

    if ($id > 0) {
        $stmtLama = $pdo->prepare('SELECT status FROM app_users WHERE id = :id');
        $stmtLama->execute(['id' => $id]);
        $lama = $stmtLama->fetch();
        if (!$lama) {
            set_flash('error', 'Pengguna tidak ditemukan.');
            redirect('pengguna_app.php');
        }

        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE app_users SET nama=:n, email=:e, status=:s, password_hash=:h WHERE id=:id');
            $stmt->execute(['n' => $nama, 'e' => $email, 's' => $status, 'h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
            app_cabut_semua_refresh_token($id);
        } else {
            $stmt = $pdo->prepare('UPDATE app_users SET nama=:n, email=:e, status=:s WHERE id=:id');
            $stmt->execute(['n' => $nama, 'e' => $email, 's' => $status, 'id' => $id]);
        }
        if ($status === 'nonaktif' && $lama['status'] !== 'nonaktif') {
            app_cabut_semua_refresh_token($id);
        }
        catat_log($admin['id'], 'ubah_pengguna_app', $email);
        set_flash('sukses', 'Pengguna "' . $nama . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO app_users (nama, email, password_hash, status, email_verified_at, sumber_daftar)
             VALUES (:n, :e, :h, :s, NOW(), :src)'
        );
        $stmt->execute([
            'n' => $nama, 'e' => $email, 'h' => password_hash($password, PASSWORD_DEFAULT),
            's' => $status, 'src' => 'admin',
        ]);
        catat_log($admin['id'], 'tambah_pengguna_app', $email);
        set_flash('sukses', 'Pengguna "' . $nama . '" berhasil ditambahkan dan otomatis terverifikasi.');
    }
    redirect('pengguna_app.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'verifikasi') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE app_users SET email_verified_at = NOW() WHERE id = :id')->execute(['id' => $id]);
    catat_log($admin['id'], 'verifikasi_manual_pengguna_app', (string)$id);
    set_flash('sukses', 'Email pengguna berhasil diverifikasi secara manual.');
    redirect('pengguna_app.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $cek = $pdo->prepare('SELECT email FROM app_users WHERE id = :id');
    $cek->execute(['id' => $id]);
    $row = $cek->fetch();
    if ($row) {
        $pdo->prepare('DELETE FROM app_users WHERE id = :id')->execute(['id' => $id]);
        catat_log($admin['id'], 'hapus_pengguna_app', $row['email']);
        set_flash('sukses', 'Pengguna "' . $row['email'] . '" berhasil dihapus.');
    }
    redirect('pengguna_app.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$dataEdit = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $dataEdit = $stmt->fetch();
}

$cariKata = trim($_GET['cari'] ?? '');
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$perHalaman = 20;
$offset = ($halaman - 1) * $perHalaman;

$whereSql = '';
$params = [];
if ($cariKata !== '') {
    $whereSql = 'WHERE nama LIKE :cari OR email LIKE :cari';
    $params['cari'] = '%' . $cariKata . '%';
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM app_users $whereSql");
$totalStmt->execute($params);
$totalBaris = (int)$totalStmt->fetchColumn();
$totalHalaman = max(1, (int)ceil($totalBaris / $perHalaman));

$sql = "SELECT * FROM app_users $whereSql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perHalaman, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$daftarUser = $stmt->fetchAll();

$judul_admin = 'Pengguna Aplikasi';
$menuAktif = 'pengguna_app';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="form-2col">
    <div class="panel">
        <div class="panel-head">
            <h2>Daftar Pengguna Aplikasi Mobile</h2>
        </div>
        <form method="get" class="filter-bar" style="margin-bottom:14px;">
            <input type="text" name="cari" placeholder="Cari nama atau email…" value="<?= h($cariKata) ?>">
            <button type="submit" class="btn btn-outline btn-sm">Cari</button>
        </form>
        <table class="tabel">
            <thead><tr><th>Nama</th><th>Email</th><th>Status</th><th>Verifikasi</th><th>Sumber</th><th>Login Terakhir</th><th></th></tr></thead>
            <tbody>
            <?php if (!$daftarUser): ?>
                <tr><td colspan="7">Belum ada pengguna aplikasi.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftarUser as $u): ?>
                <tr>
                    <td><?= h($u['nama']) ?></td>
                    <td><?= h($u['email']) ?></td>
                    <td><span class="status-pill <?= $u['status'] === 'aktif' ? 'published' : 'draft' ?>"><?= $u['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td>
                        <?php if ($u['email_verified_at']): ?>
                            ✅ Terverifikasi
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="verifikasi">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">❌ Verifikasi manual</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?= $u['sumber_daftar'] === 'admin' ? 'Admin' : 'Mobile' ?></td>
                    <td><?= $u['last_login_at'] ? h(waktu_relatif($u['last_login_at'])) : '—' ?></td>
                    <td class="aksi">
                        <a href="?edit=<?= (int)$u['id'] ?>" class="btn btn-outline btn-sm">Ubah</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus pengguna &quot;<?= h(addslashes($u['email'])) ?>&quot;? Semua sesi login perangkat mereka juga akan dihapus.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalHalaman > 1): ?>
        <div class="paginasi" style="margin-top:14px; display:flex; gap:8px;">
            <?php for ($p = 1; $p <= $totalHalaman; $p++): ?>
                <a href="?halaman=<?= $p ?>&cari=<?= h(urlencode($cariKata)) ?>" class="btn btn-outline btn-sm <?= $p === $halaman ? 'aktif' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
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
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?= h($dataEdit['email'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="password">Password <?= $dataEdit ? '(kosongkan jika tidak diubah)' : '' ?></label>
                <input type="password" id="password" name="password" minlength="8" <?= $dataEdit ? '' : 'required' ?>>
                <span class="bantuan">Minimal 8 karakter. Mengubah password akan memutus semua sesi login pengguna di aplikasi mobile.</span>
            </div>
            <div class="form-row">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="aktif" <?= ($dataEdit['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif (bisa masuk ke aplikasi)</option>
                    <option value="nonaktif" <?= ($dataEdit['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif (diblokir)</option>
                </select>
            </div>
            <?php if (!$dataEdit): ?>
            <p class="bantuan">Pengguna yang ditambahkan langsung dari sini otomatis dianggap terverifikasi (tidak perlu kode OTP).</p>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $dataEdit ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></button>
                <?php if ($dataEdit): ?><a href="pengguna_app.php" class="btn btn-outline">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
