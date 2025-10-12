<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use RuntimeException;

class BrivaService
{
    protected BriApi $api;

    protected string $institutionCode;
    protected string $brivaNo;
    protected string $clientSecret;
    protected string $baseUrl;
    protected bool   $logging;

    public function __construct(BriApi $api)
    {
        $this->api             = $api;
        $this->institutionCode = (string) config('bri.institution_code', '');
        $this->brivaNo         = (string) config('bri.briva_no', '');
        $this->clientSecret    = (string) config('bri.client_secret', '');
        $this->baseUrl         = rtrim((string) config('bri.base_url', ''), '/');
        $this->logging         = (bool) config('bri.log', false);
    }

    /* =========================
     * ===  Core Utilities   ===
     * ========================= */

    protected function utcTimestamp(): string
    {
        // BRI biasanya minta format ISO8601 / RFC3339 (Z)
        return Carbon::now('UTC')->toRfc3339String(); // ex: 2025-10-09T02:35:10+00:00
    }

    protected function normalizePath(string $path): string
    {
        // pastikan selalu diawali '/'
        return '/' . ltrim($path, '/');
    }

    protected function buildStringToSign(string $method, string $path, string $accessToken, string $timestamp, string $body): string
    {
        // NOTE: Sesuaikan dengan spesifikasi BRI kamu jika berbeda
        // Beberapa varian pakai '&' / '\n' sebagai delimiter — tinggal ubah di sini.
        return implode('|', [$path, strtoupper($method), $accessToken, $timestamp, $body]);
    }

    protected function signature(string $stringToSign): string
    {
        // HMAC-SHA256 base64
        $raw = hash_hmac('sha256', $stringToSign, $this->clientSecret, true);
        return base64_encode($raw);
    }

    protected function request(string $method, string $path, array $body = []): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('[BRI] base_url kosong. Set bri.base_url di config/.env.');
        }

        $method    = strtoupper($method);
        $path      = $this->normalizePath($path);
        $token     = $this->api->getAccessToken();
        $timestamp = $this->utcTimestamp();

        $jsonBody  = empty($body) ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $string    = $this->buildStringToSign($method, $path, $token, $timestamp, $jsonBody);
        $sign      = $this->signature($string);

        $url = $this->baseUrl . $path;

        // Header names configurable (fallback aman)
        $tsHeader  = (string) config('bri.headers.timestamp', 'BRI-Timestamp');
        $sigHeader = (string) config('bri.headers.signature', 'BRI-Signature');

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            $tsHeader       => $timestamp,
            $sigHeader      => $sign,
            'Content-Type'  => 'application/json',
        ];

        $http = $this->api->http()->withHeaders($headers);

        if ($this->logging) {
            $maskedToken = substr($token, 0, 8) . '...' . substr($token, -4);
            $maskedSign  = substr($sign, 0, 6) . '...';
            logger()->info('[BRI][REQ]', [
                'method'  => $method,
                'url'     => $url,
                'body'    => $body,
                'headers' => [
                    'Authorization' => 'Bearer ' . $maskedToken,
                    $tsHeader       => $timestamp,
                    $sigHeader      => $maskedSign,
                    'Content-Type'  => 'application/json',
                ],
            ]);
        }

        /** @var \Illuminate\Http\Client\Response $resp */
        $resp = ($method === 'GET')
            ? $http->get($url)
            // untuk POST/PUT/PATCH/DELETE, pakai send agar body terkirim
            : $http->send($method, $url, ['body' => $jsonBody]);

        if ($this->logging) {
            logger()->info('[BRI][RESP]', [
                'status' => $resp->status(),
                'body'   => $resp->json() ?? $resp->body(),
            ]);
        }

        if (!$resp->successful()) {
            // Coba refresh token 1x kalau 401
            if ($resp->status() === 401) {
                $token     = $this->api->getAccessToken(true);
                $timestamp = $this->utcTimestamp();
                $string    = $this->buildStringToSign($method, $path, $token, $timestamp, $jsonBody);
                $sign      = $this->signature($string);

                $headers['Authorization'] = 'Bearer ' . $token;
                $headers[$tsHeader]       = $timestamp;
                $headers[$sigHeader]      = $sign;

                $http2 = $this->api->http()->withHeaders($headers);
                $resp  = ($method === 'GET')
                    ? $http2->get($url)
                    : $http2->send($method, $url, ['body' => $jsonBody]);
            }
        }

        if (!$resp->successful()) {
            throw new RuntimeException('[BRI] HTTP ' . $resp->status() . ': ' . $resp->body());
        }

        return $resp->json() ?? [];
    }

    /* =========================
     * ===    VA Methods     ===
     * ========================= */

    /**
     * Buat VA untuk satu invoice.
     * $params minimal: custCode, amount (int), nama (custName), expiredAt (Carbon|string Y-m-d H:i:s)
     * Opsi: keterangan / description.
     */
    public function createVa(array $params): array
    {
        $custCode  = (string) data_get($params, 'custCode');
        $amount    = (int) data_get($params, 'amount', 0);
        $nama      = (string) data_get($params, 'nama', data_get($params, 'custName', 'Mahasiswa'));
        $expiredAt = data_get($params, 'expiredAt'); // Carbon|string
        $ket       = (string) data_get($params, 'keterangan', data_get($params, 'description', 'Pembayaran SKS'));

        if (!$custCode || $amount <= 0) {
            throw new RuntimeException('createVa: custCode/amount wajib diisi.');
        }

        $expired = $expiredAt instanceof \DateTimeInterface
            ? $expiredAt->format('Y-m-d H:i:s')
            : (string) $expiredAt;

        $path = (string) (config('bri.endpoints.briva_base') ?? '/v1/briva');
        $path .= '/create';

        $body = [
            'institutionCode' => $this->institutionCode,
            'brivaNo'         => $this->brivaNo,
            'custCode'        => $custCode,
            'nama'            => $nama,
            'amount'          => (string) $amount,
            'keterangan'      => $ket,
            'expiredDate'     => $expired, // 'YYYY-MM-DD HH:mm:ss'
        ];

        return $this->request('POST', $path, $body);
    }

    public function getVa(string $custCode): array
    {
        $base = (string) (config('bri.endpoints.briva_base') ?? '/v1/briva');

        $path = $base
              . '/get/' . rawurlencode($this->institutionCode)
              . '/'     . rawurlencode($this->brivaNo)
              . '/'     . rawurlencode($custCode);

        return $this->request('GET', $path);
    }

    public function updateVa(array $params): array
    {
        // BRI biasanya butuh amount/nama/expiredDate utk update
        $custCode  = (string) data_get($params, 'custCode');
        if (!$custCode) {
            throw new RuntimeException('updateVa: custCode wajib diisi.');
        }

        $amount    = (int) data_get($params, 'amount', 0);
        $nama      = (string) data_get($params, 'nama', data_get($params, 'custName'));
        $expiredAt = data_get($params, 'expiredAt');

        $expired = $expiredAt instanceof \DateTimeInterface
            ? $expiredAt->format('Y-m-d H:i:s')
            : (string) $expiredAt;

        $path = (string) (config('bri.endpoints.briva_base') ?? '/v1/briva');
        $path .= '/update';

        $body = array_filter([
            'institutionCode' => $this->institutionCode,
            'brivaNo'         => $this->brivaNo,
            'custCode'        => $custCode,
            'nama'            => $nama,
            'amount'          => $amount ? (string) $amount : null,
            'expiredDate'     => $expired ?: null,
        ], fn($v) => !is_null($v) && $v !== '');

        return $this->request('PUT', $path, $body);
    }

    public function deleteVa(string $custCode): array
    {
        $path = (string) (config('bri.endpoints.briva_base') ?? '/v1/briva');
        $path .= '/delete';

        $body = [
            'institutionCode' => $this->institutionCode,
            'brivaNo'         => $this->brivaNo,
            'custCode'        => $custCode,
        ];

        // pakai send() supaya body terkirim untuk DELETE
        return $this->request('DELETE', $path, $body);
    }

    /* =========================
     * ===   Report API     ===
     * ========================= */

    /**
     * Rekonsiliasi berdasarkan tanggal (harian).
     * Format tanggal: 'YYYY-MM-DD'
     */
    public function getReportByDate(string $fromDate, ?string $toDate = null): array
    {
        $path = (string) (config('bri.endpoints.report_date') ?? '/v1/briva/report');

        $body = [
            'institutionCode' => $this->institutionCode,
            'brivaNo'         => $this->brivaNo,
            'fromDate'        => $fromDate,
            'toDate'          => $toDate ?: $fromDate,
        ];

        return $this->request('POST', $path, $body);
    }

    /**
     * Rekonsiliasi berdasarkan waktu (jam-menit).
     * Format datetime: 'YYYY-MM-DD HH:mm:ss'
     */
    public function getReportByTime(string $fromDateTime, string $toDateTime): array
    {
        $path = (string) (config('bri.endpoints.report_time') ?? '/v1/briva/report/time');

        $body = [
            'institutionCode' => $this->institutionCode,
            'brivaNo'         => $this->brivaNo,
            'fromDateTime'    => $fromDateTime,
            'toDateTime'      => $toDateTime,
        ];

        return $this->request('POST', $path, $body);
    }

    /* =========================
     * ===   Helpers (VA)   ===
     * ========================= */

    /**
     * Generator custCode contoh (stabil & ringkas): NIM (max 10) + angsuran_ke (2 digit, pad-left).
     * Pastikan total panjang custCode sesuai aturan korporatmu (umumnya 10).
     */
    public static function makeCustCode(string $nim, int $angsuranKe): string
    {
        $nimCore = preg_replace('/\D+/', '', $nim) ?: $nim;
        $nimCore = substr($nimCore, -10); // ambil 10 digit terakhir kalau kepanjangan
        return $nimCore . str_pad((string) $angsuranKe, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Gabung brivaNo + custCode (buat ditaruh di kolom va_full).
     */
    public function makeFullVa(string $custCode): string
    {
        return (string) ($this->brivaNo . $custCode);
    }
}
