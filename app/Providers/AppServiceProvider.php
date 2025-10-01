<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Helpers\SemesterHelper;
use App\Models\KalenderEvent;  // pastikan import model
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        // Composer untuk semua view admin.* dan mahasiswa.*
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

    public function register(): void
    {
        //
    }
}
