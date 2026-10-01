<?php
/**
 * Fungsi bantu untuk sistem pembelian satu-kali topik berbayar (khusus
 * aplikasi mobile — situs web selalu gratis). Dipakai dari API mobile
 * maupun panel admin.
 */

require_once __DIR__ . '/functions.php';

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
