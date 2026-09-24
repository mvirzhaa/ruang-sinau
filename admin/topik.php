<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

// ---------- Aksi: ubah status publikasi ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle_status') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT judul, status FROM topik WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if ($row) {
        $baru = $row['status'] === 'published' ? 'draft' : 'published';
        $upd = $pdo->prepare('UPDATE topik SET status = :s WHERE id = :id');
        $upd->execute(['s' => $baru, 'id' => $id]);
        catat_log($admin['id'], 'ubah_status_topik', $row['judul'] . ' → ' . $baru);
        set_flash('sukses', 'Status "' . $row['judul'] . '" diubah menjadi ' . ($baru === 'published' ? 'Terbit' : 'Draf') . '.');
    }
    redirect('topik.php');
}

// ---------- Aksi: hapus ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT judul, tipe, file_path FROM topik WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if ($row) {
        $del = $pdo->prepare('DELETE FROM topik WHERE id = :id');
        $del->execute(['id' => $id]);

        if ($row['tipe'] === 'latihan' && $row['file_path']) {
            $lokasiFile = UPLOAD_DIR_QUIZZES . $row['file_path'];
            if (is_file($lokasiFile)) {
                @unlink($lokasiFile);
            }
        }

        catat_log($admin['id'], 'hapus_topik', $row['judul']);
        set_flash('sukses', '"' . $row['judul'] . '" berhasil dihapus.');
    }
    redirect('topik.php');
}

// ---------- Filter & daftar ----------
$filterTipe = in_array($_GET['tipe'] ?? '', ['latihan', 'materi'], true) ? $_GET['tipe'] : '';
$filterStatus = in_array($_GET['status'] ?? '', ['published', 'draft'], true) ? $_GET['status'] : '';
$kataKunci = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];
if ($filterTipe) { $where[] = 't.tipe = :tipe'; $params['tipe'] = $filterTipe; }
if ($filterStatus) { $where[] = 't.status = :status'; $params['status'] = $filterStatus; }
if ($kataKunci !== '') { $where[] = 't.judul LIKE :q'; $params['q'] = '%' . $kataKunci . '%'; }
$whereSql = implode(' AND ', $where);

$sql = "SELECT t.*, k.nama AS kelas_nama, m.nama AS mapel_nama, j.nama AS jenjang_nama
        FROM topik t
        JOIN kelas k ON k.id = t.kelas_id JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id
        WHERE $whereSql
        ORDER BY t.created_at DESC
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftarTopik = $stmt->fetchAll();

$judul_admin = 'Latihan & Materi';
$menuAktif = 'topik';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Semua Konten (<?= count($daftarTopik) ?>)</h2>
        <div style="display:flex; gap:8px;">
            <a href="impor_massal.php" class="btn btn-outline">📦 Impor Massal</a>
            <a href="topik_form.php" class="btn btn-primary">+ Tambah Baru</a>
        </div>
    </div>

    <form method="get" class="form-2col" style="grid-template-columns: 2fr 1fr 1fr; margin-bottom:18px;">
        <input type="text" name="q" placeholder="Cari judul..." value="<?= h($kataKunci) ?>">
        <select name="tipe" onchange="this.form.submit()">
            <option value="">Semua jenis</option>
            <option value="latihan" <?= $filterTipe === 'latihan' ? 'selected' : '' ?>>Latihan soal</option>
            <option value="materi" <?= $filterTipe === 'materi' ? 'selected' : '' ?>>Materi</option>
        </select>
        <select name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>Terbit</option>
            <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draf</option>
        </select>
    </form>

    <?php if ($daftarTopik): ?>
    <table class="tabel">
        <thead><tr><th>Judul</th><th>Lokasi</th><th>Jenis</th><th>Dilihat</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($daftarTopik as $t): ?>
            <tr>
                <td><strong><?= h(format_judul($t['judul'])) ?></strong></td>
                <td style="color:var(--muted); font-size:12.5px;"><?= h($t['jenjang_nama']) ?> / <?= h($t['mapel_nama']) ?> / <?= h($t['kelas_nama']) ?></td>
                <td><?= $t['tipe'] === 'materi' ? '📘 Materi' : '📝 Latihan' ?></td>
                <td>👁 <?= (int)$t['dilihat'] ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="toggle_status">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="status-pill <?= h($t['status']) ?>" style="border:none; cursor:pointer;">
                            <?= $t['status'] === 'published' ? '● Terbit' : '○ Draf' ?>
                        </button>
                    </form>
                </td>
                <td class="aksi">
                    <a href="topik_form.php?id=<?= (int)$t['id'] ?>" class="btn btn-outline btn-sm">Ubah</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Hapus &quot;<?= h(addslashes($t['judul'])) ?>&quot;? Tindakan ini tidak dapat dibatalkan.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="kosong-admin">Tidak ada konten yang cocok dengan filter ini.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
