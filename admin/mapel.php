<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    wajib_csrf_valid();

    $id = (int)($_POST['id'] ?? 0);
    $jenjangId = (int)($_POST['jenjang_id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $emoji = trim($_POST['emoji'] ?? '') ?: '📘';
    $urutan = (int)($_POST['urutan'] ?? 0);

    if ($nama === '' || $jenjangId <= 0) {
        set_flash('error', 'Jenjang dan nama mata pelajaran wajib diisi.');
        redirect('mapel.php');
    }

    $slugDasar = buat_slug($nama);
    $slug = slug_unik('mapel', $slugDasar, 'jenjang_id', $jenjangId, $id ?: null);

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE mapel SET jenjang_id=:jid, nama=:nama, slug=:slug, emoji=:emoji, urutan=:urutan WHERE id=:id');
        $stmt->execute(['jid' => $jenjangId, 'nama' => $nama, 'slug' => $slug, 'emoji' => $emoji, 'urutan' => $urutan, 'id' => $id]);
        catat_log($admin['id'], 'ubah_mapel', $nama);
        set_flash('sukses', 'Mata pelajaran "' . $nama . '" berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare('INSERT INTO mapel (jenjang_id, nama, slug, emoji, urutan) VALUES (:jid, :nama, :slug, :emoji, :urutan)');
        $stmt->execute(['jid' => $jenjangId, 'nama' => $nama, 'slug' => $slug, 'emoji' => $emoji, 'urutan' => $urutan]);
        catat_log($admin['id'], 'tambah_mapel', $nama);
        set_flash('sukses', 'Mata pelajaran "' . $nama . '" berhasil ditambahkan.');
    }
    redirect('mapel.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $cek = $pdo->prepare('SELECT nama FROM mapel WHERE id = :id');
    $cek->execute(['id' => $id]);
    $row = $cek->fetch();
    if ($row) {
        $stmt = $pdo->prepare('DELETE FROM mapel WHERE id = :id');
        $stmt->execute(['id' => $id]);
        catat_log($admin['id'], 'hapus_mapel', $row['nama']);
        set_flash('sukses', 'Mata pelajaran "' . $row['nama'] . '" beserta kelas dan kontennya berhasil dihapus.');
    }
    redirect('mapel.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$dataEdit = null;
if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM mapel WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $dataEdit = $stmt->fetch();
}

$jenjangList = $pdo->query('SELECT * FROM jenjang ORDER BY urutan, nama')->fetchAll();

$filterJenjang = (int)($_GET['jenjang_id'] ?? 0);
$sql = "SELECT m.*, j.nama AS jenjang_nama,
               (SELECT COUNT(*) FROM kelas k WHERE k.mapel_id = m.id) AS jumlah_kelas
        FROM mapel m JOIN jenjang j ON j.id = m.jenjang_id";
$params = [];
if ($filterJenjang) {
    $sql .= ' WHERE m.jenjang_id = :jid';
    $params['jid'] = $filterJenjang;
}
$sql .= ' ORDER BY j.urutan, m.urutan, m.nama';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarMapel = $stmt->fetchAll();

$judul_admin = 'Kelola Mata Pelajaran';
$menuAktif = 'mapel';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="form-2col">
    <div class="panel">
        <div class="panel-head">
            <h2>Daftar Mata Pelajaran</h2>
            <form method="get" style="margin:0;">
                <select name="jenjang_id" onchange="this.form.submit()">
                    <option value="0">Semua jenjang</option>
                    <?php foreach ($jenjangList as $j): ?>
                        <option value="<?= (int)$j['id'] ?>" <?= $filterJenjang === (int)$j['id'] ? 'selected' : '' ?>><?= h($j['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php if ($daftarMapel): ?>
        <table class="tabel">
            <thead><tr><th>Mata Pelajaran</th><th>Jenjang</th><th>Kelas</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($daftarMapel as $m): ?>
                <tr>
                    <td><?= h($m['emoji']) ?> <strong><?= h($m['nama']) ?></strong></td>
                    <td><?= h($m['jenjang_nama']) ?></td>
                    <td><?= (int)$m['jumlah_kelas'] ?></td>
                    <td class="aksi">
                        <a href="?edit=<?= (int)$m['id'] ?>" class="btn btn-outline btn-sm">Ubah</a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Hapus mata pelajaran &quot;<?= h(addslashes($m['nama'])) ?>&quot;? Semua kelas dan konten di dalamnya juga akan terhapus.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="kosong-admin">Belum ada mata pelajaran.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head"><h2><?= $dataEdit ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran' ?></h2></div>
        <?php if (!$jenjangList): ?>
            <p class="kosong-admin">Tambahkan jenjang terlebih dahulu sebelum membuat mata pelajaran.</p>
        <?php else: ?>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= (int)($dataEdit['id'] ?? 0) ?>">
            <div class="form-row">
                <label for="jenjang_id">Jenjang</label>
                <select id="jenjang_id" name="jenjang_id" required>
                    <option value="">— pilih jenjang —</option>
                    <?php foreach ($jenjangList as $j): ?>
                        <option value="<?= (int)$j['id'] ?>" <?= (int)($dataEdit['jenjang_id'] ?? 0) === (int)$j['id'] ? 'selected' : '' ?>><?= h($j['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="nama">Nama Mata Pelajaran</label>
                <input type="text" id="nama" name="nama" required placeholder="contoh: Matematika, Bahasa Indonesia"
                       value="<?= h($dataEdit['nama'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="emoji">Ikon (emoji)</label>
                <input type="text" id="emoji" name="emoji" maxlength="10" placeholder="📘"
                       value="<?= h($dataEdit['emoji'] ?? '📘') ?>">
            </div>
            <div class="form-row">
                <label for="urutan">Urutan tampil</label>
                <input type="number" id="urutan" name="urutan" value="<?= (int)($dataEdit['urutan'] ?? 0) ?>">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $dataEdit ? 'Simpan Perubahan' : 'Tambah Mata Pelajaran' ?></button>
                <?php if ($dataEdit): ?><a href="mapel.php" class="btn btn-outline">Batal</a><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
