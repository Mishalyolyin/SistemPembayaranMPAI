<?php

namespace App\Support;

class VaHelper
{
    public static function normalizeNim(string $nim, int $lastN = 10): string
    {
        $digits = preg_replace('/\D+/', '', $nim) ?: $nim;
        return strlen($digits) > $lastN ? substr($digits, -$lastN) : $digits;
    }
}
