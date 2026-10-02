<?php
/**
 * Fungsi bantu untuk sistem pembelian satu-kali topik berbayar (khusus
 * aplikasi mobile — situs web selalu gratis). Dipakai dari API mobile
 * maupun panel admin.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/libs/MidtransClient.php';

/** Klien Midtrans Snap siap pakai, dikonfigurasi dari config/config.php. */
function midtrans(): MidtransClient
{
    return new MidtransClient(MIDTRANS_SERVER_KEY, MIDTRANS_IS_PRODUCTION);
}

/** Harga default (Rp) untuk topik baru di kelas ini berdasarkan jenjangnya; null jika jenjang tidak terdaftar di HARGA_DEFAULT_PER_JENJANG. */
function harga_default_untuk_kelas(PDO $pdo, int $kelasId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT j.nama FROM kelas k JOIN mapel m ON m.id = k.mapel_id JOIN jenjang j ON j.id = m.jenjang_id WHERE k.id = :id'
    );
    $stmt->execute(['id' => $kelasId]);
    $jenjang = $stmt->fetchColumn();
    return $jenjang !== false ? (HARGA_DEFAULT_PER_JENJANG[$jenjang] ?? null) : null;
}

/** true jika user sudah membeli topik ini (transaksi berstatus 'berhasil'). */
function user_sudah_beli_topik(PDO $pdo, int $userId, int $topikId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM pembelian WHERE user_id = :u AND topik_id = :t AND status = 'berhasil' LIMIT 1"
    );
    $stmt->execute(['u' => $userId, 't' => $topikId]);
    return (bool) $stmt->fetchColumn();
}

/** Format angka rupiah untuk tampilan, mis. 25000 -> "Rp 25.000". */
function format_rupiah(int $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
