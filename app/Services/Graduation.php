<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Mahasiswa;
use App\Models\MahasiswaReguler;
use App\Models\Invoice;
use App\Models\InvoiceReguler;

class Graduation
{
    /**
     * Hitung jumlah semester yang SUDAH TERLEWATI sejak anchor (semester_awal + tahun_akademik).
     * Anchor: 20 Sep (mulai Ganjil) & 20 Feb (mulai Genap), sesuai rekap final.
     */
    public static function countPassedSemesters(string $semesterAwal, string|int $tahunAkademik, ?Carbon $now = null): int
    {
        $now = $now ?: now('Asia/Jakarta');

        // dukung format "2025/2026"
        if (is_string($tahunAkademik) && preg_match('/(\d{4})/', $tahunAkademik, $m)) {
            $Y = (int)$m[1];
        } else {
            $Y = (int)$tahunAkademik;
        }

        $sem = strtolower(trim($semesterAwal));
        if (!in_array($sem, ['ganjil','genap'], true)) $sem = 'ganjil';

        $cursor = ($sem === 'ganjil')
            ? Carbon::create($Y, 9, 20, 0, 0, 0, 'Asia/Jakarta')   // 20 Sep Y
            : Carbon::create($Y, 2, 20, 0, 0, 0, 'Asia/Jakarta');  // 20 Feb Y

        $passed = 0;
        $current = $sem;

        while (true) {
            $next = ($current === 'ganjil')
                ? Carbon::create($cursor->year + 1, 2, 20, 0, 0, 0, 'Asia/Jakarta') // ke Genap
                : Carbon::create($cursor->year, 9, 20, 0, 0, 0, 'Asia/Jakarta');     // ke Ganjil

            if ($now->lt($next)) break;

            $passed++;
            $cursor = $next->copy();
            $current = ($current === 'ganjil') ? 'genap' : 'ganjil';
        }

        return $passed;
    }

    /** Semua invoice RPL lunas? */
    public static function allInvoicesPaidRPL(Mahasiswa $m): bool
    {
        $total = Invoice::where('mahasiswa_id', $m->id)->count();
        if ($total === 0) return false;
        $lunas = Invoice::where('mahasiswa_id', $m->id)
            ->whereIn('status', ['Lunas','Lunas (Otomatis)'])->count();
        return $lunas === $total;
    }

    /** Semua invoice Reguler lunas? */
    public static function allInvoicesPaidReg(MahasiswaReguler $m): bool
    {
        $total = InvoiceReguler::where('mahasiswa_reguler_id', $m->id)->count();
        if ($total === 0) return false;
        $lunas = InvoiceReguler::where('mahasiswa_reguler_id', $m->id)
            ->whereIn('status', ['Lunas','Lunas (Otomatis)'])->count();
        return $lunas === $total;
    }

    /** RPL: eligible kalau semua lunas + ≥ 2 semester terlewati */
    public static function eligibleRPL(Mahasiswa $m, ?Carbon $now = null): bool
    {
        $passed = self::countPassedSemesters((string)$m->semester_awal, (string)($m->tahun_akademik ?? ''), $now);
        return $passed >= 2 && self::allInvoicesPaidRPL($m);
    }

    /** Reguler: eligible kalau semua lunas + ≥ 4 semester terlewati */
    public static function eligibleReg(MahasiswaReguler $m, ?Carbon $now = null): bool
    {
        $passed = self::countPassedSemesters((string)$m->semester_awal, (string)($m->tahun_akademik ?? ''), $now);
        return $passed >= 4 && self::allInvoicesPaidReg($m);
    }
}
