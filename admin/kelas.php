<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    wajib_csrf_valid();

    $id = (int)($_POST['id'] ?? 0);
    $mapelId = (int)($_POST['mapel_id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $urutan = (int)($_POST['urutan'] ?? 0);

    if ($nama === '' || $mapelId <= 0) {
        set_flash('error', 'Mata pelajaran dan nama kelas wajib diisi.');
        redirect('kelas.php');
    }

    $slugDasar = buat_slug($nama);
    $slug = slug_unik('kelas', $slugDasar, 'mapel_id', $mapelId, $id ?: null);

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE kelas SET mapel_id=:mid, nama=:nama, slug=:slug, urutan=:urutan WHERE id=:id');
        $stmt->execute(['mid' => $mapelId, 'nama' => $nama, 'slug' => $slug, 'urutan' => $urutan, 'id' => $id]);
        catat_log($admin['id'], 'ubah_kelas', $nama);
        set_flash('sukses', 'Kelas "' . $nama . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO kelas (mapel_id, nama, slug, urutan) VALUES (:mid, :nama, :slug, :urutan)');
        $stmt->execute(['mid' => $mapelId, 'nama' => $nama, 'slug' => $slug, 'urutan' => $urutan]);
        catat_log($admin['id'], 'tambah_kelas', $nama);
        set_flash('sukses', 'Kelas "' . $nama . '" berhasil ditambahkan.');
    }
    redirect('kelas.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $cek = $pdo->prepare('SELECT nama FROM kelas WHERE id = :id');
    $cek->execute(['id' => $id]);
    $row = $cek->fetch();
    if ($row) {
        $stmt = $pdo->prepare('DELETE FROM kelas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        catat_log($admin['id'], 'hapus_kelas', $row['nama']);
        set_flash('sukses', 'Kelas "' . $row['nama'] . '" beserta kontennya berhasil dihapus.');
    }
    redirect('kelas.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$dataEdit = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM kelas WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $dataEdit = $stmt->fetch();
}

$mapelList = $pdo->query(
    "SELECT m.*, j.nama AS jenjang_nama FROM mapel m JOIN jenjang j ON j.id = m.jenjang_id ORDER BY j.urutan, m.urutan, m.nama"
)->fetchAll();

$filterMapel = (int)($_GET['mapel_id'] ?? 0);
$sql = "SELECT k.*, m.nama AS mapel_nama, j.nama AS jenjang_nama,
               (SELECT COUNT(*) FROM topik t WHERE t.kelas_id = k.id) AS jumlah_topik
        FROM kelas k JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id";
$params = [];
if ($filterMapel) {
    $sql .= ' WHERE k.mapel_id = :mid';
    $params['mid'] = $filterMapel;
}
$sql .= ' ORDER BY j.urutan, m.urutan, k.urutan, k.nama';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarKelas = $stmt->fetchAll();

$judul_admin = 'Kelola Kelas';
$menuAktif = 'kelas';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="form-2col">
    <div class="panel">
        <div class="panel-head">
            <h2>Daftar Kelas</h2>
            <form method="get" style="margin:0;">
                <select name="mapel_id" onchange="this.form.submit()">
                    <option value="0">Semua mata pelajaran</option>
                    <?php foreach ($mapelList as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= $filterMapel === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['jenjang_nama']) ?> — <?= h($m['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if ($daftarKelas): ?>
        <table class="tabel">
            <thead><tr><th>Kelas</th><th>Mata Pelajaran</th><th>Konten</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($daftarKelas as $k): ?>
                <tr>
                    <td><strong><?= h($k['nama']) ?></strong></td>
                    <td><?= h($k['jenjang_nama']) ?> — <?= h($k['mapel_nama']) ?></td>
                    <td><?= (int)$k['jumlah_topik'] ?></td>
                    <td class="aksi">
                        <a href="?edit=<?= (int)$k['id'] ?>" class="btn btn-outline btn-sm">Ubah</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus kelas &quot;<?= h(addslashes($k['nama'])) ?>&quot;? Semua konten di dalamnya juga akan terhapus.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="kosong-admin">Belum ada kelas.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head"><h2><?= $dataEdit ? 'Ubah Kelas' : 'Tambah Kelas' ?></h2></div>
        <?php if (!$mapelList): ?>
            <p class="kosong-admin">Tambahkan mata pelajaran terlebih dahulu sebelum membuat kelas.</p>
        <?php else: ?>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int)($dataEdit['id'] ?? 0) ?>">
            <div class="form-row">
                <label for="mapel_id">Mata Pelajaran</label>
                <select id="mapel_id" name="mapel_id" required>
                    <option value="">— pilih mata pelajaran —</option>
                    <?php foreach ($mapelList as $m): ?>
                        <option value="<?= (int)$m['id'] ?>" <?= (int)($dataEdit['mapel_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>><?= h($m['jenjang_nama']) ?> — <?= h($m['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="nama">Nama Kelas</label>
                <input type="text" id="nama" name="nama" required placeholder="contoh: Kelas 4, Kelas 10"
                       value="<?= h($dataEdit['nama'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="urutan">Urutan tampil</label>
                <input type="number" id="urutan" name="urutan" value="<?= (int)($dataEdit['urutan'] ?? 0) ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $dataEdit ? 'Simpan Perubahan' : 'Tambah Kelas' ?></button>
                <?php if ($dataEdit): ?><a href="kelas.php" class="btn btn-outline">Batal</a><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
