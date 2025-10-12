<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook Security (tetap sesuai logic lama)
    |--------------------------------------------------------------------------
    | Dipakai middleware briva.webhook untuk verifikasi HMAC + timestamp skew
    | + (opsional) Bearer. Biarkan apa adanya.
    */
    'webhook' => [
        // Header names (ikut gateway/BRI)
        'signature_header' => env('BRI_WEBHOOK_SIG_HEADER', 'X-Signature'),
        'timestamp_header' => env('BRI_WEBHOOK_TS_HEADER', 'X-Timestamp'),

        // Timestamp skew (detik) + alias kompat
        'timestamp_skew'   => (int) env('BRI_WEBHOOK_TS_SKEW', 300),
        'ts_skew'          => (int) env('BRI_WEBHOOK_TS_SKEW', 300),

        // HMAC config
        'hmac_secret'      => env('BRI_HMAC_SECRET', 'dev-secret-change-me'),
        'hash_algo'        => env('BRI_HMAC_ALGO', 'sha256'),
        'encoding'         => env('BRI_HMAC_ENCODING', 'base64'),

        // Double-lock (PROD aktifkan)
        'require_both'     => filter_var(env('BRI_WEBHOOK_REQUIRE_BOTH', false), FILTER_VALIDATE_BOOLEAN),
        'bearer_token'     => env('BRI_WEBHOOK_TOKEN', 'dev-bearer-change-me'),

        // Test-friendly toggles (DEV only)
        'dev_mode'               => filter_var(env('BRI_DEV_MODE', true),  FILTER_VALIDATE_BOOLEAN),
        'accept_unsigned_in_dev' => filter_var(env('BRI_ACCEPT_UNSIGNED_IN_DEV', true), FILTER_VALIDATE_BOOLEAN),
        'log_raw_body'           => filter_var(env('BRI_LOG_RAW', true),   FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | CustCode Policy — CAP LOGIC
    |--------------------------------------------------------------------------
    | KONSTAN = NIM (ambil N digit terakhir). Tidak ada nim_plus_step.
    | Tambahkan 'mode'='nim' sebagai ALIAS LEGACY agar kode lama yang masih
    | memanggil config('bri.custcode.mode') tetap menghasilkan 'nim'.
    */
    'custcode' => [
        'last_n_digits'    => (int) env('BRI_CUSTCODE_LAST_N', 10),
        'institution_code' => env('BRI_INSTITUTION_CODE', null),

        // === Legacy alias (JANGAN diganti): pastikan selalu 'nim'
        'mode'             => 'nim',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hard Stop: Larang generate VA lokal
    |--------------------------------------------------------------------------
    | Jika true: abaikan/stop pemakaian VA lokal (contoh: briva_no+custCode).
    | VA wajib datang dari bank (webhook 'va-assigned') / sistem BRI.
    */
    'forbid_local_va' => filter_var(env('BRI_FORBID_LOCAL_VA', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | BRIVA / Bank-side Numbers (opsional)
    |--------------------------------------------------------------------------
    | Isi jika bank pakai format VA = briva_no + custCode. Kalau tidak, kosongkan.
    */
    'briva_no' => env('BRI_BRIVA_NO', ''),

    /*
    |--------------------------------------------------------------------------
    | BRI API Client (opsional)
    |--------------------------------------------------------------------------
    | Tidak mengubah flow utama. Aman dibiarkan kosong jika belum dipakai.
    */
    'api' => [
        'base_url'      => env('BRI_BASE_URL', ''),
        'client_id'     => env('BRI_CLIENT_ID', ''),
        'client_secret' => env('BRI_CLIENT_SECRET', ''),
        'timeout'       => (int) env('BRI_TIMEOUT', 15),

        'endpoints' => [
            'oauth_token_path' => env('BRI_OAUTH_TOKEN_PATH', '/oauth/token'),
            'briva_base'       => env('BRI_BRIVA_BASE', '/v1/briva'),
            // opsional (rekonsiliasi)
            'report_date'      => env('BRI_BRIVA_REPORT_DATE', '/v1/briva/report'),
            'report_time'      => env('BRI_BRIVA_REPORT_TIME', '/v1/briva/report/time'),
        ],

        // Header kustom versi API (dipakai BrivaService::request)
        'headers' => [
            'timestamp' => env('BRI_API_TS_HEADER', 'BRI-Timestamp'),
            'signature' => env('BRI_API_SIG_HEADER', 'BRI-Signature'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Header root (fallback) — untuk kompat BrivaService yang cek 'bri.headers'
    |--------------------------------------------------------------------------
    */
    'headers' => [
        // fallback ke api.headers.* kalau tidak dioverride
        'timestamp' => env('BRI_API_TS_HEADER', 'BRI-Timestamp'),
        'signature' => env('BRI_API_SIG_HEADER', 'BRI-Signature'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Eligibility Pull API (opsional untuk Spectate)
    |--------------------------------------------------------------------------
    */
    'eligibility' => [
        'bearer_token'       => env('BRI_ELIGIBILITY_TOKEN', null),
        'allowed_ips'        => env('BRI_ELIGIBILITY_ALLOWED_IPS', ''), // "1.2.3.4,5.6.7.8"
        'hash_secret'        => env('BRI_ELIGIBILITY_HASH_SECRET', null),
        'rate_limit_per_min' => (int) env('BRI_ELIGIBILITY_RATELIMIT', 60),
    ],
];
