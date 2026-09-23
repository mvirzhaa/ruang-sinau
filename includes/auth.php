<?php
/**
 * Autentikasi & otorisasi admin.
 */

require_once __DIR__ . '/functions.php';

function admin_login_sedang(): bool
{
    mulai_sesi();
    return !empty($_SESSION['admin_id']);
}

function admin_saat_ini(): ?array
{
    mulai_sesi();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache === null) {
        $stmt = db()->prepare('SELECT id, username, nama, peran, aktif FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['admin_id']]);
        $cache = $stmt->fetch() ?: null;
        if ($cache === null || !$cache['aktif']) {
            admin_logout();
        }
    }
    return $cache;
}

function wajib_login(): void
{
    if (!admin_login_sedang()) {
        redirect('login.php');
    }
}

function wajib_super_admin(): void
{
    wajib_login();
    $admin = admin_saat_ini();
    if (!$admin || $admin['peran'] !== 'super_admin') {
        http_response_code(403);
        die('Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
}

// ---------------------------------------------------------
// Proteksi brute-force login
// ---------------------------------------------------------

function login_terkunci(string $username): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS jumlah FROM login_attempts
         WHERE username = :u AND ip_address = :ip AND waktu > (NOW() - INTERVAL :menit MINUTE)'
    );
    $stmt->bindValue(':u', $username);
    $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
    $stmt->bindValue(':menit', LOGIN_LOCKOUT_MINUTES, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return ((int)$row['jumlah']) >= LOGIN_MAX_ATTEMPTS;
}

function catat_percobaan_gagal(string $username): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (:u, :ip)');
    $stmt->execute(['u' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
}

function bersihkan_percobaan_gagal(string $username): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE username = :u AND ip_address = :ip');
    $stmt->execute(['u' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
}

function coba_login(string $username, string $password): array
{
    if (login_terkunci($username)) {
        return ['sukses' => false, 'pesan' => 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . LOGIN_LOCKOUT_MINUTES . ' menit.'];
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = :u');
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !$user['aktif'] || !password_verify($password, $user['password_hash'])) {
        catat_percobaan_gagal($username);
        return ['sukses' => false, 'pesan' => 'Username atau password salah.'];
    }

    bersihkan_percobaan_gagal($username);

    mulai_sesi();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $user['id'];

    $upd = db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $upd->execute(['id' => $user['id']]);

    catat_log((int)$user['id'], 'login', 'Login berhasil');

    // Rehash otomatis jika algoritma password sudah usang
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id');
        $rehash->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
    }

    return ['sukses' => true, 'pesan' => ''];
}

function admin_logout(): void
{
    mulai_sesi();
    $adminId = $_SESSION['admin_id'] ?? null;
    if ($adminId) {
        catat_log((int)$adminId, 'logout', '');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
