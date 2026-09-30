<?php
/**
 * Klien SMTP minimal (native, tanpa Composer/dependency) untuk mengirim
 * email verifikasi/reset password. Mendukung koneksi polos+STARTTLS (587)
 * atau SSL langsung (465), dan AUTH LOGIN.
 *
 * Sengaja ditulis sendiri (bukan vendor PHPMailer) supaya tetap konsisten
 * dengan prinsip proyek ini: PHP native tanpa dependency pihak ketiga.
 */

class SimpleSmtpException extends RuntimeException {}

class SimpleSmtp
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $secure, // 'tls' | 'ssl' | ''
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeoutDetik = 15
    ) {
    }

    public function kirim(string $dariEmail, string $dariNama, string $keEmail, string $keNama, string $subjek, string $htmlBody): void
    {
        $this->buka();
        try {
            $this->baca(220);
            $this->tulisPerintah('EHLO ' . $this->hostLokal(), 250);

            if ($this->secure === 'tls') {
                $this->tulisPerintah('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new SimpleSmtpException('Gagal memulai TLS ke server SMTP.');
                }
                $this->tulisPerintah('EHLO ' . $this->hostLokal(), 250);
            }

            if ($this->username !== '') {
                $this->tulisPerintah('AUTH LOGIN', 334);
                $this->tulisPerintah(base64_encode($this->username), 334);
                $this->tulisPerintah(base64_encode($this->password), 235);
            }

            $this->tulisPerintah('MAIL FROM:<' . $this->bersihkanAlamat($dariEmail) . '>', 250);
            $this->tulisPerintah('RCPT TO:<' . $this->bersihkanAlamat($keEmail) . '>', 250);
            $this->tulisPerintah('DATA', 354);

            $pesan = $this->rakitPesan($dariEmail, $dariNama, $keEmail, $keNama, $subjek, $htmlBody);
            $this->tulis($pesan . "\r\n.");
            $this->baca(250);

            $this->tulisPerintah('QUIT', 221);
        } finally {
            $this->tutup();
        }
    }

    private function rakitPesan(string $dariEmail, string $dariNama, string $keEmail, string $keNama, string $subjek, string $htmlBody): string
    {
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . $this->hostLokal() . '>';
        $header = [
            'Date: ' . date('r'),
            'Message-ID: ' . $messageId,
            'From: ' . $this->formatAlamat($dariEmail, $dariNama),
            'To: ' . $this->formatAlamat($keEmail, $keNama),
            'Subject: ' . $this->encodeSubjek($subjek),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        // Baris tunggal "." di awal harus di-escape (dot-stuffing) sesuai protokol SMTP
        $body = preg_replace('/^\./m', '..', $htmlBody);

        return implode("\r\n", $header) . "\r\n\r\n" . $body;
    }

    private function formatAlamat(string $email, string $nama): string
    {
        $email = $this->bersihkanAlamat($email);
        $nama = $this->bersihkanHeaderValue($nama);
        return $nama !== '' ? sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($nama), $email) : $email;
    }

    private function encodeSubjek(string $subjek): string
    {
        return '=?UTF-8?B?' . base64_encode($this->bersihkanHeaderValue($subjek)) . '?=';
    }

    private function bersihkanHeaderValue(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value));
    }

    private function bersihkanAlamat(string $email): string
    {
        $email = $this->bersihkanHeaderValue($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SimpleSmtpException('Alamat email tidak valid: ' . $email);
        }
        return $email;
    }

    private function hostLokal(): string
    {
        return gethostname() ?: 'localhost';
    }

    private function buka(): void
    {
        $skema = $this->secure === 'ssl' ? 'ssl://' : 'tcp://';
        $this->socket = @stream_socket_client(
            $skema . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeoutDetik
        );
        if (!$this->socket) {
            throw new SimpleSmtpException("Gagal konek ke SMTP {$this->host}:{$this->port} — $errstr");
        }
        stream_set_timeout($this->socket, $this->timeoutDetik);
    }

    private function tutup(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function tulis(string $data): void
    {
        fwrite($this->socket, $data . "\r\n");
    }

    private function baca(int $kodeDiharapkan): string
    {
        $respons = '';
        while (($baris = fgets($this->socket, 515)) !== false) {
            $respons .= $baris;
            // Baris terakhir dari respons multi-baris ditandai spasi setelah kode (bukan '-')
            if (preg_match('/^\d{3} /', $baris)) {
                break;
            }
        }
        $kode = (int)substr($respons, 0, 3);
        if ($kode !== $kodeDiharapkan) {
            throw new SimpleSmtpException("Server SMTP membalas tidak sesuai (diharap $kodeDiharapkan): " . trim($respons));
        }
        return $respons;
    }

    private function tulisPerintah(string $perintah, int $kodeDiharapkan): string
    {
        $this->tulis($perintah);
        return $this->baca($kodeDiharapkan);
    }
}
