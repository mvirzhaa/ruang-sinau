<?php
/**
 * Fungsi bantu yang dipakai di seluruh aplikasi.
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------
// Escaping / keamanan output
// ---------------------------------------------------------

/** Escape aman untuk output HTML (cegah XSS). Selalu bungkus data dinamis dengan ini. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------
// Slug
// ---------------------------------------------------------

function buat_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-') ?: 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

/** Pastikan slug unik dalam sebuah tabel (opsional exclude id saat edit). */
function slug_unik(string $table, string $slugDasar, ?string $kolomScope = null, $scopeValue = null, ?int $excludeId = null): string
{
    $pdo = db();
    $slug = $slugDasar;
    $i = 2;

    while (true) {
        $sql = "SELECT id FROM `$table` WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($kolomScope !== null) {
            $sql .= " AND `$kolomScope` = :scope";
            $params['scope'] = $scopeValue;
        }
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $slugDasar . '-' . $i;
        $i++;
    }
}

// ---------------------------------------------------------
// Sesi & flash message
// ---------------------------------------------------------

function mulai_sesi(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
    }
}

function set_flash(string $tipe, string $pesan): void
{
    mulai_sesi();
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function ambil_flash(): array
{
    mulai_sesi();
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

// ---------------------------------------------------------
// CSRF
// ---------------------------------------------------------

function csrf_token(): string
{
    mulai_sesi();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    mulai_sesi();
    $dikirim = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($dikirim) && hash_equals($_SESSION['csrf_token'], $dikirim);
}

function wajib_csrf_valid(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
        http_response_code(403);
        die('Sesi tidak valid atau kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
    }
}

// ---------------------------------------------------------
// Redirect
// ---------------------------------------------------------

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

// ---------------------------------------------------------
// Sanitasi HTML untuk konten "materi" (whitelist tag)
// Mencegah stored-XSS dari editor teks kaya di panel admin.
// ---------------------------------------------------------

function sanitasi_html(string $html): string
{
    $tagDiizinkan = '<p><br><b><strong><i><em><u><s><h2><h3><h4><ul><ol><li>' .
                    '<blockquote><a><img><table><thead><tbody><tr><td><th><hr><span><div><code><pre>';

    $bersih = strip_tags($html, $tagDiizinkan);

    // Buang atribut event handler (onclick, onerror, dst) dan skema javascript:/data:
    $bersih = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $bersih);
    $bersih = preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>]*\2/i', '$1="#"', $bersih);
    $bersih = preg_replace('/(href|src)\s*=\s*(["\']?)\s*data:(?!image\/(png|jpe?g|gif|webp))[^"\'>]*\2/i', '$1="#"', $bersih);

    return $bersih;
}

// ---------------------------------------------------------
// Tanggal & waktu (format Indonesia)
// ---------------------------------------------------------

function format_tanggal(string $datetime): string
{
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($datetime);
    return (int)date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function waktu_relatif(string $datetime): string
{
    $selisih = time() - strtotime($datetime);
    if ($selisih < 60) return 'baru saja';
    if ($selisih < 3600) return floor($selisih / 60) . ' menit lalu';
    if ($selisih < 86400) return floor($selisih / 3600) . ' jam lalu';
    if ($selisih < 2592000) return floor($selisih / 86400) . ' hari lalu';
    return format_tanggal($datetime);
}

// ---------------------------------------------------------
// Lain-lain
// ---------------------------------------------------------

function url_publik(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function nama_file_aman(string $namaAsli): string
{
    $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
    $base = buat_slug(pathinfo($namaAsli, PATHINFO_FILENAME));
    return $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
}

function catat_log(?int $adminId, string $aksi, string $detail = ''): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO log_aktivitas (admin_id, aksi, detail, ip_address) VALUES (:admin_id, :aksi, :detail, :ip)'
        );
        $stmt->execute([
            'admin_id' => $adminId,
            'aksi'     => $aksi,
            'detail'   => $detail,
            'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('Gagal mencatat log aktivitas: ' . $e->getMessage());
    }
}
