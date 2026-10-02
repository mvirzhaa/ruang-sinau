<?php
/**
 * Klien minimal untuk Midtrans Snap API (native cURL, tanpa SDK/Composer)
 * agar konsisten dengan prinsip proyek: PHP native tanpa dependency pihak ketiga.
 * Dokumentasi: https://docs.midtrans.com/reference/snap-create-transaction
 */

class MidtransException extends RuntimeException {}

class MidtransClient
{
    public function __construct(
        private string $serverKey,
        private bool $isProduction = false
    ) {
    }

    /**
     * Buat transaksi Snap, kembalikan ['token' => ..., 'redirect_url' => ...].
     * @param array{nama:string,email:string} $pembeli
     */
    public function buatTransaksi(string $orderId, int $grossAmount, array $pembeli): array
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $pembeli['nama'],
                'email' => $pembeli['email'],
            ],
        ];

        $respons = $this->kirimPermintaan('/snap/v1/transactions', $payload);
        if (!isset($respons['token'])) {
            throw new MidtransException('Respons Midtrans tidak berisi token: ' . json_encode($respons));
        }
        return ['token' => $respons['token'], 'redirect_url' => $respons['redirect_url'] ?? null];
    }

    /** true jika signature_key dari notifikasi webhook valid (bukan pemalsuan). */
    public function verifikasiSignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $diharapkan = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($diharapkan, $signatureKey);
    }

    private function kirimPermintaan(string $path, array $payload): array
    {
        $base = $this->isProduction ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($this->serverKey . ':'),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new MidtransException('Gagal menghubungi Midtrans: ' . $error);
        }
        $statusHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body, true);
        if (!is_array($data) || $statusHttp >= 300) {
            throw new MidtransException('Midtrans membalas status ' . $statusHttp . ': ' . $body);
        }
        return $data;
    }
}
