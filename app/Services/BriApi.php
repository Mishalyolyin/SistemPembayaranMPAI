<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class BriApi
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl      = rtrim(config('bri.base_url'), '/');
        $this->clientId     = (string) config('bri.client_id');
        $this->clientSecret = (string) config('bri.client_secret');
        $this->timeout      = (int) config('bri.timeout', 15);
    }

    /**
     * Ambil OAuth access_token via client_credentials, cache otomatis sebelum expire.
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $cacheKey = 'bri:oauth:client_credentials:' . md5($this->clientId);
        if (!$forceRefresh && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $url = $this->baseUrl . (config('bri.endpoints.token') ?? '/oauth/client_credential/accesstoken');

        $resp = Http::timeout($this->timeout)
            ->asForm()
            ->acceptJson()
            ->post($url, [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        if (!$resp->ok()) {
            throw new RuntimeException('BRI OAuth gagal: ' . $resp->status() . ' ' . $resp->body());
        }

        $json  = $resp->json();
        $token = (string) data_get($json, 'access_token', '');

        if ($token === '') {
            throw new RuntimeException('BRI OAuth: access_token kosong.');
        }

        // Simpan ke cache sedikit lebih pendek dari expires_in (buffer 60 detik)
        $ttl = max(60, (int) data_get($json, 'expires_in', 3600) - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        if (config('bri.log')) {
            logger()->info('[BRI][OAuth] token updated', ['ttl' => $ttl]);
        }

        return $token;
    }

    /**
     * Helper HTTP client dengan timeout & default header JSON.
     */
    public function http()
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withUserAgent('CampusPay/1.0 (+Laravel)');
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }
}
