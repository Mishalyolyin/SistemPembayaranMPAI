<?php

namespace App\Support;

use Carbon\Carbon;
use App\Helpers\SemesterHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RplBilling
{
    /**
     * Mapping bulan sesuai REKAP FINAL (RPL).
     * - Ganjil: 20 Sep – 31 Jan
     * - Genap : 20 Feb – 31 Jul
     */
    public static function mapping(string $semester, int $angsuran): array
    {
        $s = strtolower($semester);

        if ($s === 'ganjil') {
            if ($angsuran === 4)  return [9, 12, 3, 6];                // Sep, Des, Mar, Jun
            if ($angsuran === 6)  return [9, 11, 1, 3, 5, 6];           // Sep, Nov, Jan, Mar, Mei, Jun
            if ($angsuran === 10) return [9,10,11,12,1,2,3,4,5,6];      // Sep–Jun (skip Jul, Ags)
        } elseif ($s === 'genap') {
            if ($angsuran === 4)  return [2, 5, 8, 11];                 // Feb, Mei, Ags, Nov
            if ($angsuran === 6)  return [2, 4, 6, 8, 10, 12];          // Feb, Apr, Jun, Ags, Okt, Des
            if ($angsuran === 10) return [2,3,4,5,6,7,8,9,10,11];       // Feb–Nov (tanpa Jan & Des akhir)
        }

        throw new \InvalidArgumentException('Skema/semester RPL tidak dikenal.');
    }

    /** Nama bulan Indonesia */
    public static function bulanNama(int $m): string
    {
        $nm = [
            1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
            5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
            9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
        ];
        return $nm[$m] ?? 'Bulan-'.$m;
    }

    /**
     * Tahun basis untuk pelabelan invoice.
     * Ganjil basis di Sep (Sep–Des = tahun ini; Jan–Jun = tahun+1)
     * Genap basis di Feb (Jan dianggap sebelum genap berjalan → tahun-1).
     */
    public static function baseYearForSemester(string $semester, ?Carbon $today = null): int
    {
        $today = $today ?: now();
        $y = (int)$today->year;
        $m = (int)$today->month;

        if (strtolower($semester) === 'ganjil') {
            if ($m === 1) return $y - 1; // Jan masih ganjil sebelumnya
            if ($m >= 9) return $y;      // Sep–Des tahun ini
            return $y - 1;               // Feb–Aug: basis tahun sebelumnya
        } else {
            // Genap
            if ($m === 1) return $y - 1; // Jan sebelum genap berjalan
            return $y;                   // Feb–Des tahun ini
        }
    }

    /** Tebak semester dari bulan berjalan (untuk kondisi libur/unknown) */
    protected static function guessSemesterByMonth(?Carbon $today = null): string
    {
        $today = $today ?: now();
        $m = (int)$today->month;

        // Genap: Feb–Aug (Agustus ikut genap sesuai mapping 4x/6x)
        if ($m >= 2 && $m <= 8) return 'genap';

        // Ganjil: Sep–Jan
        return 'ganjil';
    }

    /**
     * Paket bulan (bulan,tahun,label) untuk semester AKTIF (dari SemesterHelper).
     * Menghasilkan urutan bulan sesuai jumlah angsuran yang dipilih.
     */
    public static function monthsForActiveSemester(int $angsuran): array
    {
        $active = SemesterHelper::getActiveSemester(); // bisa array/string
        if (is_array($active)) {
            $semester = strtolower((string)($active['kode'] ?? $active['semester'] ?? $active['name'] ?? ''));
        } else {
            $semester = strtolower((string)$active);
        }

        if ($semester !== 'ganjil' && $semester !== 'genap') {
            $semester = self::guessSemesterByMonth();
        }

        $months   = self::mapping($semester, $angsuran);
        $baseYear = self::baseYearForSemester($semester);

        $items = [];
        foreach ($months as $m) {
            $year = $baseYear;
            // Ganjil: Jan–Jun masuk tahun berikutnya (basis di Sep)
            if ($semester === 'ganjil' && $m <= 6) {
                $year = $baseYear + 1;
            }
            $items[] = [
                'bulan' => $m,
                'tahun' => $year,
                'label' => self::bulanNama($m).' '.$year,
            ];
        }
        return $items;
    }

    /** Format rupiah sederhana */
    public static function rupiah(int $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    /**
     * Bangun kandidat key policy berdasarkan TA & semester.
     * Urutan prioritas: rpl:{TA}:{semester} > rpl:{TA} > total_tagihan_rpl
     */
    protected static function buildPolicyKeys(?string $tahunAkademik, ?string $semesterAwal): array
    {
        $ta  = trim((string) $tahunAkademik);
        $sem = strtolower(trim((string) $semesterAwal));

        $keys = [];
        if ($ta && ($sem === 'ganjil' || $sem === 'genap')) {
            $keys[] = "rpl:{$ta}:{$sem}";
        }
        if ($ta) {
            $keys[] = "rpl:{$ta}";
        }
        $keys[] = "total_tagihan_rpl";
        return $keys;
    }

    /**
     * Ambil Total Tagihan untuk mahasiswa RPL.
     * Prioritas:
     * 1) Override per-mahasiswa (jika > 0)
     * 2) settings: rpl:{TA}:{semester}
     * 3) settings: rpl:{TA}
     * 4) settings: total_tagihan_rpl
     * (Tidak menggunakan total_tagihan global agar tidak bentrok dengan program lain.)
     */
    public static function totalTagihanFor($mahasiswa): int
    {
        if (!$mahasiswa) return 0;

        // 1) Override individu kalau diisi dan > 0
        foreach (['total_tagihan', 'biaya_total', 'total', 'tagihan_total'] as $field) {
            if (isset($mahasiswa->{$field}) && is_numeric($mahasiswa->{$field})) {
                $v = (int) $mahasiswa->{$field};
                if ($v > 0) return $v;
            }
        }

        // 2–4) Cohort policy via settings
        if (!Schema::hasTable('settings')) return 0;

        $keys = self::buildPolicyKeys(
            $mahasiswa->tahun_akademik ?? null,
            $mahasiswa->semester_awal ?? null
        );

        $rows = DB::table('settings')->whereIn('key', $keys)->get()->keyBy('key');

        foreach ($keys as $k) {
            if (isset($rows[$k])) {
                // normalisasi angka (support "25.000.000")
                $raw = (string) $rows[$k]->value;
                $num = (int) preg_replace('/\D+/', '', $raw);
                if ($num > 0) return $num;
            }
        }

        // Fail-safe: tidak memaksa angka dummy; biarkan 0 (akan ditangani di UI/controller)
        return 0;
    }

    /**
     * Versi tegas: lempar exception kalau policy/angka tidak ditemukan.
     * Cocok dipakai di controller sisi admin atau generator invoice.
     */
    public static function totalTagihanForOrFail($mahasiswa): int
    {
        $total = self::totalTagihanFor($mahasiswa);
        if ($total <= 0) {
            $ta  = (string)($mahasiswa->tahun_akademik ?? '');
            $sem = strtolower((string)($mahasiswa->semester_awal ?? ''));
            throw new \RuntimeException("Konfigurasi tarif RPL belum tersedia untuk TA={$ta}, semester={$sem}.");
        }
        return $total;
    }

    /**
     * Preview lengkap untuk semester aktif:
     * - items: [label, bulan, tahun, amount, amount_formatted]
     * - total & total_formatted
     * - angsuran
     */
    public static function previewWithAmounts($mahasiswa, int $angsuran): array
    {
        $items = self::monthsForActiveSemester($angsuran);
        $total = max(0, (int) self::totalTagihanFor($mahasiswa));

        // Bagi rata; sisa ke invoice terakhir
        $n    = max(1, (int) $angsuran);
        $base = intdiv($total, $n);
        $sisa = $total - ($base * $n);

        foreach ($items as $i => &$it) {
            $amount = $base + (($i === $n - 1) ? $sisa : 0);
            $it['amount'] = $amount;
            $it['amount_formatted'] = self::rupiah($amount);
        }
        unset($it);

        return [
            'total'           => $total,
            'total_formatted' => self::rupiah($total),
            'items'           => $items,
            'angsuran'        => $angsuran,
        ];
    }
}
