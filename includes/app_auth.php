<?php
/**
 * Autentikasi & sesi untuk akun pengguna aplikasi mobile (tabel app_users).
 * Terpisah sepenuhnya dari includes/auth.php (yang khusus admin_users).
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/jwt.php';

// ---------------------------------------------------------
// Validasi input
// ---------------------------------------------------------

function app_normalisasi_email(string $email): string
{
    return strtolower(trim($email));
}

function app_email_valid(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function app_password_valid(string $password): bool
{
    return strlen($password) >= 8;
}

// ---------------------------------------------------------
// Proteksi brute-force login (pola sama seperti includes/auth.php,
// tapi scope-nya email pengguna aplikasi, tabel terpisah)
// ---------------------------------------------------------

function app_login_terkunci(string $email): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS jumlah FROM app_login_attempts
         WHERE email = :e AND ip_address = :ip AND waktu > (NOW() - INTERVAL :menit MINUTE)'
    );
    $stmt->bindValue(':e', $email);
    $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
    $stmt->bindValue(':menit', APP_LOGIN_LOCKOUT_MINUTES, PDO::PARAM_INT);
    $stmt->execute();
    return ((int)$stmt->fetch()['jumlah']) >= APP_LOGIN_MAX_ATTEMPTS;
}

function app_catat_percobaan_gagal(string $email): void
{
    $stmt = db()->prepare('INSERT INTO app_login_attempts (email, ip_address) VALUES (:e, :ip)');
    $stmt->execute(['e' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
}

function app_bersihkan_percobaan_gagal(string $email): void
{
    $stmt = db()->prepare('DELETE FROM app_login_attempts WHERE email = :e AND ip_address = :ip');
    $stmt->execute(['e' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
}

// ---------------------------------------------------------
// OTP (verifikasi email & reset password)
// ---------------------------------------------------------

function app_buat_kode_otp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/** true jika OTP baru untuk purpose ini masih dalam masa jeda/kuota — cegah spam kirim ulang. */
function app_otp_dibatasi(int $userId, string $purpose): bool
{
    $stmt = db()->prepare(
        'SELECT created_at FROM app_email_otp WHERE user_id = :u AND purpose = :p ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute(['u' => $userId, 'p' => $purpose]);
    $terakhir = $stmt->fetchColumn();
    if ($terakhir && strtotime($terakhir) > time() - 60) {
        return true; // jeda 60 detik antar permintaan
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM app_email_otp WHERE user_id = :u AND purpose = :p AND created_at > (NOW() - INTERVAL 60 MINUTE)'
    );
    $stmt->execute(['u' => $userId, 'p' => $purpose]);
    return ((int)$stmt->fetchColumn()) >= 5;
}

/** Buat & simpan OTP baru (hash saja yang disimpan), kembalikan kode plaintext untuk dikirim via email. */
function app_terbitkan_otp(int $userId, string $purpose): string
{
    $kode = app_buat_kode_otp();
    $stmt = db()->prepare(
        'INSERT INTO app_email_otp (user_id, purpose, code_hash, expires_at, ip_address)
         VALUES (:u, :p, :h, DATE_ADD(NOW(), INTERVAL :ttl MINUTE), :ip)'
    );
    $stmt->execute([
        'u' => $userId,
        'p' => $purpose,
        'h' => hash('sha256', $kode),
        'ttl' => OTP_TTL_MENIT,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    return $kode;
}

/** @return array{sukses:bool, pesan:string} */
function app_verifikasi_otp(int $userId, string $purpose, string $kode): array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM app_email_otp
         WHERE user_id = :u AND purpose = :p AND consumed_at IS NULL AND expires_at > NOW()
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute(['u' => $userId, 'p' => $purpose]);
    $otp = $stmt->fetch();

    if (!$otp) {
        return ['sukses' => false, 'pesan' => 'Kode tidak ditemukan atau sudah kedaluwarsa. Minta kode baru.'];
    }
    if ((int)$otp['attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['sukses' => false, 'pesan' => 'Terlalu banyak percobaan salah. Minta kode baru.'];
    }
    if (!hash_equals($otp['code_hash'], hash('sha256', $kode))) {
        $pdo->prepare('UPDATE app_email_otp SET attempts = attempts + 1 WHERE id = :id')->execute(['id' => $otp['id']]);
        return ['sukses' => false, 'pesan' => 'Kode salah.'];
    }

    $pdo->prepare('UPDATE app_email_otp SET consumed_at = NOW() WHERE id = :id')->execute(['id' => $otp['id']]);
    return ['sukses' => true, 'pesan' => ''];
}

// ---------------------------------------------------------
// Token akses (JWT) & refresh token (opaque, disimpan ter-hash)
// ---------------------------------------------------------

function app_buat_access_token(array $user): string
{
    return jwt_buat(['sub' => (int)$user['id'], 'email' => $user['email']], JWT_ACCESS_TTL_MENIT * 60);
}

/** Terbitkan refresh token baru untuk user, kembalikan token plaintext (hanya disimpan ter-hash di DB). */
function app_terbitkan_refresh_token(int $userId, ?string $userAgent = null): string
{
    $token = bin2hex(random_bytes(48));
    $stmt = db()->prepare(
        'INSERT INTO app_user_tokens (user_id, token_hash, user_agent, expires_at)
         VALUES (:u, :h, :ua, DATE_ADD(NOW(), INTERVAL :ttl DAY))'
    );
    $stmt->execute([
        'u' => $userId,
        'h' => hash('sha256', $token),
        'ua' => $userAgent !== null ? substr($userAgent, 0, 255) : null,
        'ttl' => JWT_REFRESH_TTL_HARI,
    ]);
    return $token;
}

/** Ambil baris token yang valid (belum dicabut, belum kedaluwarsa) dari refresh token plaintext. */
function app_ambil_refresh_token_valid(string $token): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM app_user_tokens WHERE token_hash = :h AND revoked_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute(['h' => hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function app_cabut_refresh_token_by_id(int $tokenId): void
{
    db()->prepare('UPDATE app_user_tokens SET revoked_at = NOW() WHERE id = :id')->execute(['id' => $tokenId]);
}

function app_cabut_refresh_token(string $token): void
{
    db()->prepare('UPDATE app_user_tokens SET revoked_at = NOW() WHERE token_hash = :h')
        ->execute(['h' => hash('sha256', $token)]);
}

/** Cabut semua sesi (refresh token) milik user — dipakai saat nonaktif/reset password/ubah status. */
function app_cabut_semua_refresh_token(int $userId): void
{
    db()->prepare('UPDATE app_user_tokens SET revoked_at = NOW() WHERE user_id = :u AND revoked_at IS NULL')
        ->execute(['u' => $userId]);
}

// ---------------------------------------------------------
// Middleware API: ambil user aplikasi dari header Authorization
// ---------------------------------------------------------

function app_ambil_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $semua = apache_request_headers();
        $header = $semua['Authorization'] ?? $semua['authorization'] ?? '';
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/** @return array|null Baris app_users jika access token valid & akun aktif, null jika tidak. */
function app_user_dari_access_token(): ?array
{
    $token = app_ambil_bearer_token();
    if (!$token) {
        return null;
    }
    $claims = jwt_verifikasi($token);
    if (!$claims || empty($claims['sub'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM app_users WHERE id = :id');
    $stmt->execute(['id' => (int)$claims['sub']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'aktif') {
        return null;
    }
    return $user;
}
