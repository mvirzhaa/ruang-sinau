<?php
require_once __DIR__ . '/includes/bootstrap.php';
wajib_super_admin();
$pdo = db();

$halaman = max(1, (int)($_GET['halaman'] ?? 1));
$perHalaman = 40;
$offset = ($halaman - 1) * $perHalaman;

$total = (int)$pdo->query('SELECT COUNT(*) AS n FROM log_aktivitas')->fetch()['n'];
$totalHalaman = max(1, (int)ceil($total / $perHalaman));

$stmt = $pdo->prepare(
    "SELECT l.*, u.nama AS admin_nama, u.username
     FROM log_aktivitas l LEFT JOIN admin_users u ON u.id = l.admin_id
     ORDER BY l.waktu DESC LIMIT :limit OFFSET :offset"
);
$stmt->bindValue(':limit', $perHalaman, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$judul_admin = 'Log Aktivitas';
$menuAktif = 'log';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Jejak Audit (<?= (int)$total ?> catatan)</h2>
    </div>
    <?php if ($logs): ?>
    <table class="tabel">
        <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Detail</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td style="white-space:nowrap;"><?= h(format_tanggal($l['waktu'])) ?> <span style="color:var(--muted);"><?= h(date('H:i', strtotime($l['waktu']))) ?></span></td>
                <td><?= h($l['admin_nama'] ?? '(pengguna dihapus)') ?></td>
                <td><code><?= h($l['aksi']) ?></code></td>
                <td style="color:var(--muted); font-size:13px;"><?= h($l['detail']) ?></td>
                <td style="color:var(--muted); font-size:12.5px;"><?= h($l['ip_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalHalaman > 1): ?>
    <div style="display:flex; gap:8px; justify-content:center; margin-top:20px;">
        <?php for ($p = 1; $p <= $totalHalaman; $p++): ?>
            <a href="?halaman=<?= $p ?>" class="btn <?= $p === $halaman ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <p class="kosong-admin">Belum ada aktivitas tercatat.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
