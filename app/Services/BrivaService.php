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
    protected int    $timeout;

    public function __construct(BriApi $api)
    {
        $this->api             = $api;

        // --- Config fallback (dukung kunci lama & baru) ---
        $this->institutionCode = (string) (config('bri.institution_code', config('bri.custcode.institution_code', '')));
        $this->brivaNo         = (string) config('bri.briva_no', '');
        $this->clientSecret    = (string) (config('bri.client_secret', config('bri.api.client_secret', '')));
        $this->baseUrl         = rtrim((string) (config('bri.base_url', config('bri.api.base_url', ''))), '/');
        $this->timeout         = (int) (config('bri.timeout', config('bri.api.timeout', 15)));
        $this->logging         = (bool) config('bri.log', false);
    }

    /* =========================
     * ===  Core Utilities   ===
     * ========================= */

    protected function utcTimestamp(): string
    {
        // ISO8601 / RFC3339 with timezone
        return Carbon::now('UTC')->toRfc3339String();
    }

    protected function normalizePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    protected function buildStringToSign(string $method, string $path, string $accessToken, string $timestamp, string $body): string
    {
        // Sesuaikan kalau spek bank-mu beda (delimiter, urutan, dll)
        return implode('|', [$path, strtoupper($method), $accessToken, $timestamp, $body]);
    }

    protected function signature(string $stringToSign): string
    {
        // HMAC-SHA256 (base64)
        $raw = hash_hmac('sha256', $stringToSign, $this->clientSecret, true);
        return base64_encode($raw);
    }

    protected function request(string $method, string $path, array $body = []): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('[BRI] base_url kosong. Set bri.api.base_url / bri.base_url di config/.env.');
        }

        $method    = strtoupper($method);
        $path      = $this->normalizePath($path);
        $token     = $this->api->getAccessToken();
        $timestamp = $this->utcTimestamp();

        $jsonBody  = empty($body) ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $string    = $this->buildStringToSign($method, $path, $token, $timestamp, $jsonBody);
        $sign      = $this->signature($string);

        $url = $this->baseUrl . $path;

        // Header names (fallback-aware)
        $tsHeader  = (string) (config('bri.headers.timestamp', config('bri.api.headers.timestamp', 'BRI-Timestamp')));
        $sigHeader = (string) (config('bri.headers.signature', config('bri.api.headers.signature', 'BRI-Signature')));

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            $tsHeader       => $timestamp,
            $sigHeader      => $sign,
            'Content-Type'  => 'application/json',
        ];

        $http = $this->api->http()
            ->timeout($this->timeout)
            ->withHeaders($headers);

        if ($this->logging) {
            $maskedToken = strlen($token) > 12 ? substr($token, 0, 8) . '...' . substr($token, -4) : $token;
            $maskedSign  = strlen($sign) > 9 ? substr($sign, 0, 6) . '...' : $sign;
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
            : $http->send($method, $url, ['body' => $jsonBody]);

        if ($this->logging) {
            logger()->info('[BRI][RESP]', [
                'status' => $resp->status(),
                'body'   => $resp->json() ?? $resp->body(),
            ]);
        }

        // One-time retry kalau expired token
        if (!$resp->successful() && $resp->status() === 401) {
            $token     = $this->api->getAccessToken(true);
            $timestamp = $this->utcTimestamp();
            $string    = $this->buildStringToSign($method, $path, $token, $timestamp, $jsonBody);
            $sign      = $this->signature($string);

            $headers['Authorization'] = 'Bearer ' . $token;
            $headers[$tsHeader]       = $timestamp;
            $headers[$sigHeader]      = $sign;

            $http2 = $this->api->http()
                ->timeout($this->timeout)
                ->withHeaders($headers);

            $resp  = ($method === 'GET')
                ? $http2->get($url)
                : $http2->send($method, $url, ['body' => $jsonBody]);

            if ($this->logging) {
                logger()->info('[BRI][RESP-RETRY]', [
                    'status' => $resp->status(),
                    'body'   => $resp->json() ?? $resp->body(),
                ]);
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
     * Buat VA (opsional, hanya jika kamu memang memanggil API BRI untuk create).
     * Plan A tetap mengandalkan webhook 'va-assigned'.
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

        // Fallback-aware endpoint
        $base = (string) (config('bri.endpoints.briva_base', config('bri.api.endpoints.briva_base', '/v1/briva')));
        $path = $base . '/create';

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
        $base = (string) (config('bri.endpoints.briva_base', config('bri.api.endpoints.briva_base', '/v1/briva')));

        $path = $base
              . '/get/' . rawurlencode($this->institutionCode)
              . '/'     . rawurlencode($this->brivaNo)
              . '/'     . rawurlencode($custCode);

        return $this->request('GET', $path);
    }

    public function updateVa(array $params): array
    {
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

        $base = (string) (config('bri.endpoints.briva_base', config('bri.api.endpoints.briva_base', '/v1/briva')));
        $path = $base . '/update';

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
        $base = (string) (config('bri.endpoints.briva_base', config('bri.api.endpoints.briva_base', '/v1/briva')));
        $path = $base . '/delete';

        $body = [
            'institutionCode' => $this->institutionCode,
            'brivaNo'         => $this->brivaNo,
            'custCode'        => $custCode,
        ];

        return $this->request('DELETE', $path, $body);
    }

    /* =========================
     * ===   Report API     ===
     * ========================= */

    public function getReportByDate(string $fromDate, ?string $toDate = null): array
    {
        $path = (string) (config('bri.endpoints.report_date', config('bri.api.endpoints.report_date', '/v1/briva/report')));

        $body = [
            'institutionCode' => $this->institutionCode,
            'brivaNo'         => $this->brivaNo,
            'fromDate'        => $fromDate,
            'toDate'          => $toDate ?: $fromDate,
        ];

        return $this->request('POST', $path, $body);
    }

    public function getReportByTime(string $fromDateTime, string $toDateTime): array
    {
        $path = (string) (config('bri.endpoints.report_time', config('bri.api.endpoints.report_time', '/v1/briva/report/time')));

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
     * custCode KONSTAN = NIM (last-N). Param $angsuranKe dipertahankan demi kompatibilitas, tapi DIABAIKAN.
     */
    public static function makeCustCode(string $nim, int $angsuranKe = 0): string
    {
        $lastN   = (int) config('bri.custcode.last_n_digits', 10);
        $nimCore = preg_replace('/\D+/', '', $nim) ?: $nim;
        return $lastN > 0 ? substr($nimCore, -$lastN) : $nimCore;
    }

    /**
     * DILARANG: VA harus datang dari BRI via webhook 'va-assigned'.
     * Prod: exception. Dev: warning (untuk deteksi pemakaian lama).
     */
    public function makeFullVa(string $custCode): string
    {
        if (config('bri.forbid_local_va', true)) {
            if (app()->environment('production')) {
                throw new RuntimeException('Forbidden: local VA generation is disabled. VA must come from BRI webhook.');
            }
            trigger_error('Deprecated: Do NOT generate VA locally. Wait for BRI webhook.', E_USER_WARNING);
        }
        return (string) ($this->brivaNo . $custCode);
    }
}
