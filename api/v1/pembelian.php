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
    "SELECT status FROM pembelian WHERE user_id = :u AND topik_id = :t AND status IN ('berhasil','menunggu')
     ORDER BY created_at DESC LIMIT 1"
);
$stmtCek->execute(['u' => $user['id'], 't' => $topikId]);
$existing = $stmtCek->fetchColumn();
if ($existing === 'berhasil') {
    json_error(409, 'SUDAH_DIBELI', 'Anda sudah memiliki akses ke topik ini.');
}
if ($existing === 'menunggu') {
    json_error(409, 'MENUNGGU_KONFIRMASI', 'Pembelian sebelumnya untuk topik ini masih menunggu konfirmasi admin.');
}

$stmt = $pdo->prepare(
    "INSERT INTO pembelian (user_id, topik_id, harga_dibayar, metode, status)
     VALUES (:u, :t, :h, 'manual', 'menunggu')"
);
$stmt->execute(['u' => $user['id'], 't' => $topikId, 'h' => (int)$topik['harga']]);
$idBaru = (int)$pdo->lastInsertId();

json_ok([
    'pembelian' => [
        'id' => $idBaru,
        'topik_id' => $topikId,
        'harga_dibayar' => (int)$topik['harga'],
        'status' => 'menunggu',
    ],
    'pesan' => 'Pembelian tercatat dan menunggu konfirmasi admin.',
], 201);
