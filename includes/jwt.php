<?php
/**
 * JWT (JSON Web Token) HS256 minimal, tanpa dependency eksternal.
 * Dipakai untuk access token API mobile (api/v1/). Refresh token TIDAK
 * pakai format ini — refresh token adalah string acak buram yang disimpan
 * (di-hash) di tabel app_user_tokens, supaya bisa dicabut sewaktu-waktu.
 */

function jwt_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_base64url_decode(string $data): string|false
{
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'), true);
}

/**
 * Buat access token JWT berisi $claims, kedaluwarsa setelah $ttlDetik.
 * Selalu menyisipkan 'iss', 'iat', 'exp' — claims pemanggil tidak boleh
 * menimpa ketiga field ini.
 */
function jwt_buat(array $claims, int $ttlDetik): string
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $now = time();
    $payload = array_merge($claims, [
        'iss' => JWT_ISSUER,
        'iat' => $now,
        'exp' => $now + $ttlDetik,
    ]);

    $segmenHeader = jwt_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $segmenPayload = jwt_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signature = hash_hmac('sha256', $segmenHeader . '.' . $segmenPayload, JWT_SECRET, true);
    $segmenSignature = jwt_base64url_encode($signature);

    return $segmenHeader . '.' . $segmenPayload . '.' . $segmenSignature;
}

/**
 * Verifikasi & decode token. Mengembalikan array claims jika valid
 * (signature cocok, belum kedaluwarsa), atau null jika tidak valid.
 */
function jwt_verifikasi(string $token): ?array
{
    $bagian = explode('.', $token);
    if (count($bagian) !== 3) {
        return null;
    }
    [$segmenHeader, $segmenPayload, $segmenSignature] = $bagian;

    $signatureDiharapkan = jwt_base64url_encode(
        hash_hmac('sha256', $segmenHeader . '.' . $segmenPayload, JWT_SECRET, true)
    );
    if (!hash_equals($signatureDiharapkan, $segmenSignature)) {
        return null;
    }

    $payloadJson = jwt_base64url_decode($segmenPayload);
    if ($payloadJson === false) {
        return null;
    }
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || !isset($payload['exp']) || !is_numeric($payload['exp'])) {
        return null;
    }
    if ((int)$payload['exp'] < time()) {
        return null;
    }

    return $payload;
}
