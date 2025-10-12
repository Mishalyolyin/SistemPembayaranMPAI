<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Inputs yang tidak akan ditampilkan saat validasi gagal.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Daftarkan callbacks untuk menangani exception.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // biarkan kosong (default)
        });
    }
}
