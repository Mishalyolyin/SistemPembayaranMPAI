<?php

namespace App\Helpers;

use Carbon\Carbon;

class SemesterHelper
{
    /**
     * Hitung semester aktif berdasarkan tanggal hari ini.
     *
     * @return array { 'kode' => 'genap'|'ganjil'|'libur', 'periode' => 'YYYY/YYYY' }
     */
    public static function getActiveSemester(): array
    {
        $today = Carbon::today();
        $year  = $today->year;

        // Definisi periode semester
        $startGenap  = Carbon::create($year, 2, 20);
        $endGenap    = Carbon::create($year, 7, 31);
        $startGanjil = Carbon::create($year, 9, 20);
        $endGanjil   = Carbon::create($year + 1, 1, 31);

        // Bulan Agustus selalu libur
        if ($today->month === 8) {
            $kode = 'libur';
        } elseif ($today->between($startGenap, $endGenap)) {
            $kode = 'genap';
        } elseif ($today->between($startGanjil, $endGanjil)) {
            $kode = 'ganjil';
        } else {
            // Februari sebelum 20 Feb dianggap genap
            if ($today->month === 2 && $today->lt($startGenap)) {
                $kode = 'genap';
            } else {
                $kode = 'libur';
            }
        }

        return [
            'kode'    => $kode,
            'periode' => $year . '/' . ($year + 1),
        ];
    }

    /**
     * Ambil daftar bulan tagihan RPL untuk skema angsuran tertentu.
     *
     * @param int $skema 4|6|10
     * @param string $semester 'ganjil'|'genap'
     * @return array Bulan dalam format full name
     */
    public static function getRplBillingMonths(int $skema, string $semester): array
    {
        $mapping = [
            'ganjil' => [
                4  => ['September', 'Desember', 'Maret', 'Juni'],
                6  => ['September', 'November', 'Januari', 'Maret', 'Mei', 'Juni'],
                10 => ['September','Oktober','November','Desember','Januari','Februari','Maret','April','Mei','Juni'],
            ],
            'genap' => [
                4  => ['Februari', 'Mei', 'Agustus', 'November'],
                6  => ['Februari', 'April', 'Juni', 'Agustus', 'Oktober', 'Desember'],
                10 => ['Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November'],
            ],
        ];

        return $mapping[$semester][$skema] ?? [];
    }

    /**
     * Ambil daftar bulan tagihan Reguler untuk skema 8x atau 20x.
     *
     * @param int $skema 8|20
     * @param string $startSemester 'ganjil'|'genap'
     * @param int $tahunAwal
     * @return array Bulan tagihan dengan format 'YYYY-MM'
     */
    public static function getRegulerBillingMonths(int $skema, string $startSemester, int $tahunAwal): array
    {
        $months = [];

        if ($skema === 8) {
            // 4 invoice per semester, total 8
            $pattern = [
                'ganjil' => ['09', '01', '03', '07'],
                'genap'  => ['02', '07', '09', '11'],
            ];
            foreach ($pattern[$startSemester] as $idx => $m) {
                // Tahun bergulir: Januari masuk tahun berikutnya
                $year = ($m === '01') ? $tahunAwal + 1 : $tahunAwal;
                $months[] = $year . '-' . $m;
            }
        } elseif ($skema === 20) {
            // Generate 20 invoice continuously
            $pointer = Carbon::create($tahunAwal, $startSemester === 'ganjil' ? 9 : 2, 1);
            while (count($months) < 20) {
                $m = $pointer->format('m');
                $y = $pointer->year;
                // Skip Agustus event month
                if ($m !== '08') {
                    $months[] = $y . '-' . $m;
                }
                $pointer->addMonth();
            }
        }

        return $months;
    }
}
