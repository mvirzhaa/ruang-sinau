<?php
/**
 * Webhook server-to-server dari Midtrans (bukan dipanggil oleh app mobile).
 * Daftarkan URL endpoint ini sebagai "Payment Notification URL" di dashboard
 * Midtrans (Settings → Configuration), mis. https://domain-anda.com/api/v1/pembelian/notifikasi.php
 *
 * Tidak memakai Bearer token — keabsahan request diverifikasi lewat signature_key
 * (sha512 dari order_id+status_code+gross_amount+ServerKey), bukan dari auth pengguna.
 */
require_once __DIR__ . '/../_bootstrap.php';

wajib_metode('POST');
$pdo = db();

$body = body_json();
$orderId = (string)($body['order_id'] ?? '');
$statusCode = (string)($body['status_code'] ?? '');
$grossAmount = (string)($body['gross_amount'] ?? '');
$signatureKey = (string)($body['signature_key'] ?? '');
$transactionStatus = (string)($body['transaction_status'] ?? '');
$fraudStatus = (string)($body['fraud_status'] ?? 'accept');

if ($orderId === '' || $signatureKey === '') {
    json_error(400, 'VALIDASI', 'Payload notifikasi tidak lengkap.');
}

if (!midtrans()->verifikasiSignature($orderId, $statusCode, $grossAmount, $signatureKey)) {
    json_error(403, 'SIGNATURE_TIDAK_VALID', 'Signature notifikasi tidak valid.');
}

$stmt = $pdo->prepare('SELECT * FROM pembelian WHERE order_id = :o LIMIT 1');
$stmt->execute(['o' => $orderId]);
$pembelian = $stmt->fetch();

if (!$pembelian) {
    json_error(404, 'TIDAK_DITEMUKAN', 'Transaksi dengan order_id tersebut tidak ditemukan.');
}

// Midtrans bisa mengirim notifikasi berkali-kali untuk status yang sama — abaikan jika
// transaksi sudah final (mencegah 'berhasil' yang sudah di-refund admin tertimpa lagi).
if ($pembelian['status'] !== 'menunggu') {
    json_ok(['diterima' => true]);
}

$statusBaru = null;
if (in_array($transactionStatus, ['capture', 'settlement'], true) && $fraudStatus === 'accept') {
    $statusBaru = 'berhasil';
} elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'], true)) {
    $statusBaru = 'ditolak';
}
// transaction_status 'pending' -> tetap 'menunggu', tunggu notifikasi berikutnya

if ($statusBaru !== null) {
    $pdo->prepare("UPDATE pembelian SET status = :s, catatan = :c, diproses_pada = NOW() WHERE id = :id")
        ->execute([
            's' => $statusBaru,
            'c' => 'Status dari Midtrans: ' . $transactionStatus,
            'id' => $pembelian['id'],
        ]);
    catat_log(null, 'notifikasi_midtrans', "pembelian #{$pembelian['id']} -> $statusBaru ($transactionStatus)");
}

json_ok(['diterima' => true]);
