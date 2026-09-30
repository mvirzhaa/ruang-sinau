<?php
/**
 * Pengiriman email (verifikasi & reset password akun mobile).
 *
 * Jika SMTP_HOST belum diisi di config/config.php (mode pengembangan),
 * email tidak benar-benar dikirim — isinya ditulis ke storage/mail_log/
 * supaya alur registrasi/lupa-password tetap bisa ditest tanpa SMTP asli.
 */

require_once __DIR__ . '/libs/SimpleSmtp.php';

/**
 * @return bool true jika terkirim (atau berhasil ditulis ke log dev)
 */
function kirim_email(string $keEmail, string $keNama, string $subjek, string $htmlBody): bool
{
    if (SMTP_HOST === '') {
        return kirim_email_ke_log_dev($keEmail, $subjek, $htmlBody);
    }

    try {
        $smtp = new SimpleSmtp(SMTP_HOST, (int)SMTP_PORT, SMTP_SECURE, SMTP_USER, SMTP_PASS);
        $smtp->kirim(MAIL_FROM_EMAIL, MAIL_FROM_NAME, $keEmail, $keNama, $subjek, $htmlBody);
        return true;
    } catch (Throwable $e) {
        error_log('Gagal mengirim email ke ' . $keEmail . ': ' . $e->getMessage());
        return false;
    }
}

function kirim_email_ke_log_dev(string $keEmail, string $subjek, string $htmlBody): bool
{
    try {
        $dir = __DIR__ . '/../storage/mail_log/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $namaFile = date('Y-m-d_His') . '_' . preg_replace('/[^a-z0-9]+/i', '-', $keEmail) . '.html';
        $isi = '<p><strong>Ke:</strong> ' . h($keEmail) . '</p>' .
               '<p><strong>Subjek:</strong> ' . h($subjek) . '</p><hr>' . $htmlBody;
        file_put_contents($dir . $namaFile, $isi);
        error_log("[DEV] Email ke $keEmail dicatat di storage/mail_log/$namaFile (SMTP_HOST belum diisi)");
        return true;
    } catch (Throwable $e) {
        error_log('Gagal menulis log email dev: ' . $e->getMessage());
        return false;
    }
}

function template_email_otp(string $nama, string $kode, string $tujuan): string
{
    $judul = $tujuan === 'verify_email' ? 'Verifikasi Email Anda' : 'Reset Password Anda';
    $instruksi = $tujuan === 'verify_email'
        ? 'Gunakan kode berikut untuk memverifikasi email Anda di aplikasi ' . h(APP_NAME) . ':'
        : 'Gunakan kode berikut untuk mengatur ulang password akun Anda di aplikasi ' . h(APP_NAME) . ':';

    return '<div style="font-family: sans-serif; max-width:480px; margin:0 auto;">' .
        '<h2>' . h($judul) . '</h2>' .
        '<p>Halo ' . h($nama) . ',</p>' .
        '<p>' . $instruksi . '</p>' .
        '<p style="font-size:32px; font-weight:700; letter-spacing:6px; text-align:center; ' .
        'background:#F4F6FB; padding:16px; border-radius:14px; color:#2952E3;">' . h($kode) . '</p>' .
        '<p>Kode ini berlaku selama ' . (int)OTP_TTL_MENIT . ' menit. Jangan bagikan kode ini kepada siapa pun.</p>' .
        '<p>Jika Anda tidak meminta ini, abaikan saja email ini.</p>' .
        '</div>';
}
