<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Artisan commands custom
     */
    protected $commands = [
        \App\Console\Commands\ImportMahasiswaCSV::class,
        \App\Console\Commands\ImportMahasiswaRegulerCSV::class, // ✅ tambahkan command baru di sini
        \App\Console\Commands\BrivaBackfillCust::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Jadwal otomatis (kalau diperlukan)
    }

    protected function commands(): void
    {
        
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
