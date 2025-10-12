<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;

use App\Helpers\SemesterHelper;
use App\Models\KalenderEvent;
use App\Http\Middleware\VerifyBrivaWebhook;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // kosong (default)
    }

    public function boot(Router $router): void
    {
        // Pakai Bootstrap 5 untuk pagination
        Paginator::useBootstrapFive();

        /**
         * Alias middleware untuk webhook BRI.
         * Dengan ini, 'briva.webhook' di routes akan ter-resolve ke VerifyBrivaWebhook.
         * Aman dipanggil berulang—Laravel akan menimpa alias yang sama tanpa masalah.
         */
        if (class_exists(VerifyBrivaWebhook::class)) {
            $router->aliasMiddleware('briva.webhook', VerifyBrivaWebhook::class);
        }

        /**
         * View composer untuk halaman admin & mahasiswa.
         * - Inject semester aktif (jika tersedia dari helper)
         * - Inject kalender event mulai bulan ini (ascending)
         */
        View::composer(
            [
                'admin.*',
                'mahasiswa.*',
                'mahasiswa_reguler.*',
                'layouts.admin',
                'layouts.mahasiswa',
            ],
            function ($view) {
                // 1) Semester aktif
                $active = SemesterHelper::getActiveSemester();
                if (is_array($active) && isset($active['name'], $active['start'], $active['end'])) {
                    $view->with('semesterAktif', $active);
                }

                // 2) Kalender events — ambil event bulan ini ke depan
                $events = KalenderEvent::whereDate('tanggal', '>=', now()->startOfMonth())
                    ->orderBy('tanggal')
                    ->get();

                $view->with('kalenderEvents', $events);
            }
        );
    }
}
