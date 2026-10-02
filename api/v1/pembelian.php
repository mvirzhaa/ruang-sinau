<?php
require_once __DIR__ . '/_bootstrap.php';

wajib_metode('GET', 'POST');
$pdo = db();
$user = wajib_auth_app();

function serialisasi_pembelian(array $p): array
{
    return [
        'id' => (int)$p['id'],
        'topik_id' => (int)$p['topik_id'],
        'topik_judul' => format_judul($p['topik_judul']),
        'harga_dibayar' => (int)$p['harga_dibayar'],
        'metode' => $p['metode'],
        'status' => $p['status'],
        'catatan' => $p['catatan'],
        'payment_url' => $p['status'] === 'menunggu' ? $p['payment_url'] : null,
        'snap_token' => $p['status'] === 'menunggu' ? $p['snap_token'] : null,
        'dibuat_pada' => $p['created_at'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT p.*, t.judul AS topik_judul
         FROM pembelian p JOIN topik t ON t.id = p.topik_id
         WHERE p.user_id = :u ORDER BY p.created_at DESC'
    );
    $stmt->execute(['u' => $user['id']]);
    json_ok(['pembelian' => array_map('serialisasi_pembelian', $stmt->fetchAll())]);
}

$body = body_json();
$topikId = (int)($body['topik_id'] ?? 0);
if ($topikId <= 0) {
    json_error(422, 'VALIDASI', 'Parameter topik_id wajib diisi.');
}

$stmtTopik = $pdo->prepare("SELECT * FROM topik WHERE id = :id AND status = 'published'");
$stmtTopik->execute(['id' => $topikId]);
$topik = $stmtTopik->fetch();

if (!$topik) {
    json_error(404, 'TIDAK_DITEMUKAN', 'Topik tidak ditemukan.');
}
if (!$topik['berbayar_mobile']) {
    json_error(422, 'VALIDASI', 'Topik ini gratis, tidak perlu dibeli.');
}

$stmtCek = $pdo->prepare(
    "SELECT * FROM pembelian WHERE user_id = :u AND topik_id = :t AND status IN ('berhasil','menunggu')
     ORDER BY created_at DESC LIMIT 1"
);
$stmtCek->execute(['u' => $user['id'], 't' => $topikId]);
$existing = $stmtCek->fetch();

if ($existing && $existing['status'] === 'berhasil') {
    json_error(409, 'SUDAH_DIBELI', 'Anda sudah memiliki akses ke topik ini.');
}
if ($existing && $existing['status'] === 'menunggu') {
    // Transaksi Midtrans sebelumnya belum tuntas — kembalikan link pembayaran yang sama
    // supaya app tidak membuat transaksi baru (dan uang) untuk pembelian yang sama.
    json_ok([
        'pembelian' => [
            'id' => (int)$existing['id'],
            'topik_id' => $topikId,
            'harga_dibayar' => (int)$existing['harga_dibayar'],
            'status' => 'menunggu',
            'payment_url' => $existing['payment_url'],
            'snap_token' => $existing['snap_token'],
        ],
        'pesan' => 'Pembelian sebelumnya untuk topik ini masih menunggu pembayaran.',
    ]);
}

$stmt = $pdo->prepare(
    "INSERT INTO pembelian (user_id, topik_id, harga_dibayar, metode, status)
     VALUES (:u, :t, :h, 'midtrans', 'menunggu')"
);
$stmt->execute(['u' => $user['id'], 't' => $topikId, 'h' => (int)$topik['harga']]);
$idBaru = (int)$pdo->lastInsertId();
$orderId = 'PMB-' . $idBaru;

try {
    $transaksi = midtrans()->buatTransaksi($orderId, (int)$topik['harga'], [
        'nama' => $user['nama'],
        'email' => $user['email'],
    ]);
} catch (MidtransException $e) {
    $pdo->prepare("UPDATE pembelian SET status = 'ditolak', catatan = :c WHERE id = :id")
        ->execute(['c' => 'Gagal membuat transaksi pembayaran di Midtrans.', 'id' => $idBaru]);
    json_error(502, 'GAGAL_GATEWAY', 'Gagal membuat transaksi pembayaran. Coba lagi beberapa saat.');
}

$pdo->prepare("UPDATE pembelian SET order_id = :o, snap_token = :s, payment_url = :p WHERE id = :id")
    ->execute(['o' => $orderId, 's' => $transaksi['token'], 'p' => $transaksi['redirect_url'], 'id' => $idBaru]);

json_ok([
    'pembelian' => [
        'id' => $idBaru,
        'topik_id' => $topikId,
        'harga_dibayar' => (int)$topik['harga'],
        'status' => 'menunggu',
        'payment_url' => $transaksi['redirect_url'],
        'snap_token' => $transaksi['token'],
    ],
    'pesan' => 'Transaksi pembayaran dibuat — arahkan pengguna ke payment_url untuk membayar.',
], 201);
