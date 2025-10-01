<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        'admin' => [
            'driver'   => 'session',
            'provider' => 'admins',
        ],

        // RPL
        'mahasiswa' => [
            'driver'   => 'session',
            'provider' => 'mahasiswa',        // <- singular
        ],

        // Reguler
        'mahasiswa_reguler' => [
            'driver'   => 'session',
            'provider' => 'mahasiswa_reguler', // <- singular
        ],

        // (opsional) API token legacy
        'api' => [
            'driver'   => 'token',
            'provider' => 'users',
            'hash'     => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Admin::class,
        ],

        // RPL
        'mahasiswa' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Mahasiswa::class,
        ],

        // Reguler
        'mahasiswa_reguler' => [
            'driver' => 'eloquent',
            'model'  => App\Models\MahasiswaReguler::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords (Password Brokers)
    |--------------------------------------------------------------------------
    | Nama broker HARUS sama dengan yang dipanggil di Password::broker('...')
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],

        'admins' => [
            'provider' => 'admins',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],

        // Broker untuk RPL
        'mahasiswa' => [
            'provider' => 'mahasiswa',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],

        // Broker untuk Reguler
        'mahasiswa_reguler' => [
            'provider' => 'mahasiswa_reguler',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
