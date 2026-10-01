<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();

// ---------- Aksi: setujui transaksi menunggu ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'setujui') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE pembelian SET status='berhasil', diproses_oleh=:admin_id, diproses_pada=NOW() WHERE id=:id AND status='menunggu'");
    $stmt->execute(['admin_id' => $admin['id'], 'id' => $id]);
    if ($stmt->rowCount()) {
        catat_log($admin['id'], 'setujui_pembelian', (string)$id);
        set_flash('sukses', 'Pembelian disetujui — akses topik langsung aktif untuk pengguna.');
    }
    redirect('pembelian.php');
}

// ---------- Aksi: tolak transaksi menunggu ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tolak') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '') ?: 'Ditolak oleh admin.';
    $stmt = $pdo->prepare("UPDATE pembelian SET status='ditolak', catatan=:catatan, diproses_oleh=:admin_id, diproses_pada=NOW() WHERE id=:id AND status='menunggu'");
    $stmt->execute(['catatan' => $catatan, 'admin_id' => $admin['id'], 'id' => $id]);
    if ($stmt->rowCount()) {
        catat_log($admin['id'], 'tolak_pembelian', (string)$id . ' — ' . $catatan);
        set_flash('sukses', 'Pembelian ditolak.');
    }
    redirect('pembelian.php');
}

// ---------- Aksi: refund transaksi yang sudah berhasil ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'refund') {
    wajib_csrf_valid();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE pembelian SET status='refund', diproses_oleh=:admin_id, diproses_pada=NOW() WHERE id=:id AND status='berhasil'");
    $stmt->execute(['admin_id' => $admin['id'], 'id' => $id]);
    if ($stmt->rowCount()) {
        catat_log($admin['id'], 'refund_pembelian', (string)$id);
        set_flash('sukses', 'Pembelian ditandai sebagai refund — akses topik dicabut.');
    }
    redirect('pembelian.php');
}

// ---------- Aksi: beri akses manual (komplimen, tanpa alur pembelian mobile) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'beri_akses') {
    wajib_csrf_valid();
    $userId = (int)($_POST['user_id'] ?? 0);
    $topikId = (int)($_POST['topik_id'] ?? 0);

    $stmtTopik = $pdo->prepare('SELECT judul, harga, berbayar_mobile FROM topik WHERE id = :id');
    $stmtTopik->execute(['id' => $topikId]);
    $topik = $stmtTopik->fetch();

    if (!$userId || !$topik || !$topik['berbayar_mobile']) {
        set_flash('error', 'Pengguna dan topik berbayar wajib dipilih.');
        redirect('pembelian.php');
    }
    if (user_sudah_beli_topik($pdo, $userId, $topikId)) {
        set_flash('error', 'Pengguna ini sudah memiliki akses ke topik tersebut.');
        redirect('pembelian.php');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO pembelian (user_id, topik_id, harga_dibayar, metode, status, catatan, diproses_oleh, diproses_pada)
         VALUES (:u, :t, :h, 'manual', 'berhasil', 'Diberikan manual oleh admin', :admin_id, NOW())"
    );
    $stmt->execute(['u' => $userId, 't' => $topikId, 'h' => (int)$topik['harga'], 'admin_id' => $admin['id']]);
    catat_log($admin['id'], 'beri_akses_manual', $topik['judul'] . ' → user #' . $userId);
    set_flash('sukses', 'Akses "' . $topik['judul'] . '" berhasil diberikan.');
    redirect('pembelian.php');
}

// ---------- Ringkasan ----------
$ringkasan = $pdo->query(
    "SELECT COUNT(*) AS jumlah, COALESCE(SUM(harga_dibayar), 0) AS total
     FROM pembelian WHERE status = 'berhasil'"
)->fetch();
$jumlahMenunggu = (int)$pdo->query("SELECT COUNT(*) FROM pembelian WHERE status = 'menunggu'")->fetchColumn();

// ---------- Filter & daftar ----------
$filterStatus = in_array($_GET['status'] ?? '', ['menunggu', 'berhasil', 'ditolak', 'refund'], true) ? $_GET['status'] : '';
$cariKata = trim($_GET['cari'] ?? '');
$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$perHalaman = 20;
$offset = ($halaman - 1) * $perHalaman;

$where = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'p.status = :status'; $params['status'] = $filterStatus; }
if ($cariKata !== '') {
    $where[] = '(u.nama LIKE :cari OR u.email LIKE :cari OR t.judul LIKE :cari)';
    $params['cari'] = '%' . $cariKata . '%';
}
$whereSql = implode(' AND ', $where);

$totalStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM pembelian p
     JOIN app_users u ON u.id = p.user_id JOIN topik t ON t.id = p.topik_id
     WHERE $whereSql"
);
$totalStmt->execute($params);
$totalBaris = (int)$totalStmt->fetchColumn();
$totalHalaman = max(1, (int)ceil($totalBaris / $perHalaman));

$sql = "SELECT p.*, u.nama AS user_nama, u.email AS user_email, t.judul AS topik_judul
        FROM pembelian p
        JOIN app_users u ON u.id = p.user_id JOIN topik t ON t.id = p.topik_id
        WHERE $whereSql
        ORDER BY p.created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perHalaman, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$daftarPembelian = $stmt->fetchAll();

// ---------- Data untuk form "Beri Akses Manual" ----------
$daftarUserAktif = $pdo->query("SELECT id, nama, email FROM app_users WHERE status = 'aktif' ORDER BY nama")->fetchAll();
$daftarTopikBerbayar = $pdo->query("SELECT id, judul, harga FROM topik WHERE berbayar_mobile = 1 ORDER BY judul")->fetchAll();

$labelStatus = [
    'menunggu' => '⏳ Menunggu',
    'berhasil' => '✅ Berhasil',
    'ditolak'  => '❌ Ditolak',
    'refund'   => '↩️ Refund',
];

$judul_admin = 'Pembelian';
$menuAktif = 'pembelian';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="form-2col" style="grid-template-columns: 1.3fr 1fr; margin-bottom:18px; gap:14px;">
    <div class="panel" style="margin-bottom:0;">
        <div class="panel-head"><h2>Ringkasan Pendapatan</h2></div>
        <p style="font-size:28px; font-weight:700; margin:4px 0;"><?= h(format_rupiah((int)$ringkasan['total'])) ?></p>
        <p style="color:var(--muted);"><?= (int)$ringkasan['jumlah'] ?> transaksi berhasil</p>
    </div>
    <div class="panel" style="margin-bottom:0;">
        <div class="panel-head"><h2>Perlu Ditinjau</h2></div>
        <p style="font-size:28px; font-weight:700; margin:4px 0;"><?= $jumlahMenunggu ?></p>
        <p style="color:var(--muted);">transaksi menunggu konfirmasi</p>
    </div>
</div>

<div class="form-2col">
    <div class="panel">
        <div class="panel-head">
            <h2>Daftar Transaksi</h2>
        </div>
        <form method="get" class="filter-bar" style="margin-bottom:14px; display:flex; gap:8px;">
            <input type="text" name="cari" placeholder="Cari nama, email, atau judul topik…" value="<?= h($cariKata) ?>">
            <select name="status" onchange="this.form.submit()">
                <option value="">Semua status</option>
                <?php foreach ($labelStatus as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $filterStatus === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">Cari</button>
        </form>
        <table class="tabel">
            <thead><tr><th>Pengguna</th><th>Topik</th><th>Harga</th><th>Status</th><th>Tanggal</th><th></th></tr></thead>
            <tbody>
            <?php if (!$daftarPembelian): ?>
                <tr><td colspan="6">Belum ada transaksi.</td></tr>
            <?php endif; ?>
            <?php foreach ($daftarPembelian as $p): ?>
                <tr>
                    <td><?= h($p['user_nama']) ?><br><span style="color:var(--muted); font-size:12px;"><?= h($p['user_email']) ?></span></td>
                    <td><?= h(format_judul($p['topik_judul'])) ?></td>
                    <td><?= h(format_rupiah((int)$p['harga_dibayar'])) ?></td>
                    <td><?= h($labelStatus[$p['status']] ?? $p['status']) ?>
                        <?php if ($p['status'] === 'ditolak' && $p['catatan']): ?>
                            <br><span style="color:var(--muted); font-size:12px;"><?= h($p['catatan']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= h(waktu_relatif($p['created_at'])) ?></td>
                    <td class="aksi">
                        <?php if ($p['status'] === 'menunggu'): ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="setujui">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Setujui</button>
                            </form>
                            <form method="post" style="display:inline;" onsubmit="var c=prompt('Alasan penolakan (opsional):'); if(c===null) return false; this.catatan.value=c;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="tolak">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="catatan" value="">
                                <button type="submit" class="btn btn-danger btn-sm">Tolak</button>
                            </form>
                        <?php elseif ($p['status'] === 'berhasil'): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Refund transaksi ini? Akses topik untuk pengguna akan dicabut.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="refund">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">Refund</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalHalaman > 1): ?>
        <div class="paginasi" style="margin-top:14px; display:flex; gap:8px;">
            <?php for ($p = 1; $p <= $totalHalaman; $p++): ?>
                <a href="?halaman=<?= $p ?>&status=<?= h($filterStatus) ?>&cari=<?= h(urlencode($cariKata)) ?>" class="btn btn-outline btn-sm <?= $p === $halaman ? 'aktif' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Beri Akses Manual</h2></div>
        <p class="bantuan">Berikan akses topik berbayar ke pengguna tertentu tanpa lewat alur pembelian di aplikasi — misalnya untuk komplimen atau pembayaran yang diverifikasi di luar sistem.</p>
        <?php if (!$daftarTopikBerbayar): ?>
            <p class="kosong-admin">Belum ada topik yang ditandai berbayar. Atur dulu di menu <a href="topik.php">Latihan &amp; Materi</a>.</p>
        <?php else: ?>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="beri_akses">
            <div class="form-row">
                <label for="user_id">Pengguna</label>
                <select id="user_id" name="user_id" required>
                    <option value="">— pilih pengguna —</option>
                    <?php foreach ($daftarUserAktif as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= h($u['nama']) ?> (<?= h($u['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="topik_id">Topik berbayar</label>
                <select id="topik_id" name="topik_id" required>
                    <option value="">— pilih topik —</option>
                    <?php foreach ($daftarTopikBerbayar as $t): ?>
                        <option value="<?= (int)$t['id'] ?>"><?= h(format_judul($t['judul'])) ?> — <?= h(format_rupiah((int)$t['harga'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Beri Akses</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
