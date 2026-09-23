<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

// ---------- Aksi: simpan (tambah/ubah) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    wajib_csrf_valid();

    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $urutan = (int)($_POST['urutan'] ?? 0);

    if ($nama === '') {
        set_flash('error', 'Nama jenjang wajib diisi.');
        redirect('jenjang.php');
    }

    $slugDasar = buat_slug($nama);
    $slug = slug_unik('jenjang', $slugDasar, null, null, $id ?: null);

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE jenjang SET nama=:nama, slug=:slug, deskripsi=:deskripsi, urutan=:urutan WHERE id=:id');
        $stmt->execute(['nama' => $nama, 'slug' => $slug, 'deskripsi' => $deskripsi, 'urutan' => $urutan, 'id' => $id]);
        catat_log($admin['id'], 'ubah_jenjang', $nama);
        set_flash('sukses', 'Jenjang "' . $nama . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO jenjang (nama, slug, deskripsi, urutan) VALUES (:nama, :slug, :deskripsi, :urutan)');
        $stmt->execute(['nama' => $nama, 'slug' => $slug, 'deskripsi' => $deskripsi, 'urutan' => $urutan]);
        catat_log($admin['id'], 'tambah_jenjang', $nama);
        set_flash('sukses', 'Jenjang "' . $nama . '" berhasil ditambahkan.');
    }
    redirect('jenjang.php');
}

// ---------- Aksi: hapus ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);

    $cek = $pdo->prepare('SELECT nama FROM jenjang WHERE id = :id');
    $cek->execute(['id' => $id]);
    $row = $cek->fetch();

    if ($row) {
        $stmt = $pdo->prepare('DELETE FROM jenjang WHERE id = :id');
        $stmt->execute(['id' => $id]);
        catat_log($admin['id'], 'hapus_jenjang', $row['nama']);
        set_flash('sukses', 'Jenjang "' . $row['nama'] . '" beserta seluruh mata pelajaran, kelas, dan kontennya berhasil dihapus.');
    }
    redirect('jenjang.php');
}

// ---------- Data untuk form edit ----------
$editId = (int)($_GET['edit'] ?? 0);
$dataEdit = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM jenjang WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $dataEdit = $stmt->fetch();
}

$daftarJenjang = $pdo->query(
    "SELECT j.*, (SELECT COUNT(*) FROM mapel m WHERE m.jenjang_id = j.id) AS jumlah_mapel
     FROM jenjang j ORDER BY j.urutan, j.nama"
)->fetchAll();

$judul_admin = 'Kelola Jenjang';
$menuAktif = 'jenjang';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="form-2col">
    <div class="panel">
        <div class="panel-head"><h2>Daftar Jenjang</h2></div>
        <?php if ($daftarJenjang): ?>
        <table class="tabel">
            <thead><tr><th>Nama</th><th>Mata Pelajaran</th><th>Urutan</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($daftarJenjang as $j): ?>
                <tr>
                    <td><strong><?= h($j['nama']) ?></strong><br><span style="color:var(--muted); font-size:12.5px;"><?= h($j['deskripsi']) ?></span></td>
                    <td><?= (int)$j['jumlah_mapel'] ?></td>
                    <td><?= (int)$j['urutan'] ?></td>
                    <td class="aksi">
                        <a href="?edit=<?= (int)$j['id'] ?>" class="btn btn-outline btn-sm">Ubah</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus jenjang &quot;<?= h(addslashes($j['nama'])) ?>&quot;? Semua mata pelajaran, kelas, dan konten di dalamnya juga akan terhapus.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$j['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="kosong-admin">Belum ada jenjang.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head"><h2><?= $dataEdit ? 'Ubah Jenjang' : 'Tambah Jenjang' ?></h2></div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int)($dataEdit['id'] ?? 0) ?>">
            <div class="form-row">
                <label for="nama">Nama Jenjang</label>
                <input type="text" id="nama" name="nama" required placeholder="contoh: SD, SMP, Kuliah, Umum"
                       value="<?= h($dataEdit['nama'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="deskripsi">Deskripsi singkat</label>
                <input type="text" id="deskripsi" name="deskripsi" placeholder="contoh: Sekolah Dasar — Kelas 1 sampai 6"
                       value="<?= h($dataEdit['deskripsi'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="urutan">Urutan tampil</label>
                <input type="number" id="urutan" name="urutan" value="<?= (int)($dataEdit['urutan'] ?? 0) ?>">
                <span class="bantuan">Angka lebih kecil tampil lebih dulu.</span>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $dataEdit ? 'Simpan Perubahan' : 'Tambah Jenjang' ?></button>
                <?php if ($dataEdit): ?>
                    <a href="jenjang.php" class="btn btn-outline">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
