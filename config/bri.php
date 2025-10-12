<?php

return [
    'webhook' => [
        // === Header names (ikutin yang dipakai gateway/BRI) ===
        'signature_header' => env('BRI_WEBHOOK_SIG_HEADER', 'X-Signature'),
        'timestamp_header' => env('BRI_WEBHOOK_TS_HEADER', 'X-Timestamp'),

        // === Timestamp skew (detik) ===
        // Catatan: dua key disediakan (alias) biar kompatibel dgn middleware yang beda nama
        'timestamp_skew'   => (int) env('BRI_WEBHOOK_TS_SKEW', 300),
        'ts_skew'          => (int) env('BRI_WEBHOOK_TS_SKEW', 300), // alias, aman buat test

        // === HMAC config ===
        'hmac_secret'      => env('BRI_HMAC_SECRET', 'dev-secret-change-me'),
        'hash_algo'        => env('BRI_HMAC_ALGO', 'sha256'),     // umumnya sha256
        'encoding'         => env('BRI_HMAC_ENCODING', 'base64'), // base64 | hex

        // === Double-lock (PROD aktifkan) ===
        'require_both'     => filter_var(env('BRI_WEBHOOK_REQUIRE_BOTH', false), FILTER_VALIDATE_BOOLEAN),
        'bearer_token'     => env('BRI_WEBHOOK_TOKEN', 'dev-bearer-change-me'),

        // === Test-friendly toggles (DEV only) ===
        // NB: middleware kamu boleh abaikan ini di PROD; pake buat santaiin test lokal aja.
        'dev_mode'                 => filter_var(env('BRI_DEV_MODE', true),  FILTER_VALIDATE_BOOLEAN),
        'accept_unsigned_in_dev'   => filter_var(env('BRI_ACCEPT_UNSIGNED_IN_DEV', true), FILTER_VALIDATE_BOOLEAN),
        'log_raw_body'             => filter_var(env('BRI_LOG_RAW', true),   FILTER_VALIDATE_BOOLEAN),
    ],
];
