<?php

namespace App\Support;

use Carbon\Carbon;
use App\Helpers\SemesterHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegulerBilling
{
    /**
     * Urutan bulan dasar per semester (10 bulan per siklus akademik).
     * - Ganjil: Sep–Jun  => [9..12,1..6]
     * - Genap : Feb–Nov  => [2..11]
     *
     * Untuk angsuran 8x -> ambil 8 pertama dari siklus aktif (fungsi lama).
     * Untuk angsuran 20x -> dua siklus beruntun (20 bulan) dgn penyesuaian tahun otomatis (fungsi lama).
     */
    protected static function baseMonthSequence(string $semester): array
    {
        $s = strtolower(trim($semester));
        if ($s === 'ganjil') {
            return [9,10,11,12,1,2,3,4,5,6];
        }
        if ($s === 'genap') {
            return [2,3,4,5,6,7,8,9,10,11];
        }
        throw new \InvalidArgumentException('Semester reguler tidak dikenal (ganjil/genap).');
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
     * Tahun basis untuk pelabelan (selaras dgn definisi kalender akademik kamu):
     * - Ganjil basis di Sep (Sep–Des = tahun basis, Jan–Jun = tahun basis + 1)
     * - Genap  basis di Feb (Feb–Nov = tahun basis)
     *
     * (Dipakai oleh fungsi lama berbasis "semester aktif".)
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

    /** Tebak semester bila helper ngasih nilai "libur"/unknown (kompatibilitas lama) */
    protected static function guessSemesterByMonth(?Carbon $today = null): string
    {
        $today = $today ?: now();
        $m = (int)$today->month;
        // Genap: Feb–Aug (Opsi A: Agustus tetap dianggap berjalan)
        if ($m >= 2 && $m <= 8) return 'genap';
        return 'ganjil'; // Sep–Jan
    }

    /**
     * ====== FUNGSI BARU – BERBASIS ANCHOR MAHASISWA (disarankan) ======
     */

    /** Normalisasi semester_awal */
    protected static function normSemester(?string $semesterAwal): string
    {
        $s = strtolower(trim((string)$semesterAwal));
        return in_array($s, ['ganjil','genap'], true) ? $s : 'ganjil';
    }

    /** Anchor month dari semester_awal (ganjil=Sep/9, genap=Feb/2) */
    protected static function anchorMonth(string $semesterAwal): int
    {
        return self::normSemester($semesterAwal) === 'genap' ? 2 : 9;
    }

    /** Parse "2025/2026" atau "2025" → 2025 */
    protected static function parseAnchorYear(int|string|null $tahunAwal): int
    {
        if (is_int($tahunAwal)) return $tahunAwal;
        if (is_string($tahunAwal) && preg_match('/(\d{4})/', $tahunAwal, $m)) {
            return (int) $m[1];
        }
        return (int) now()->year; // fallback aman
    }

    /**
     * POLA 8x REGULER sesuai rekap final (Opsi A – billing tidak libur):
     * - Start GANJIL:
     *   Sep(Y), Jan(Y+1), Mar(Y+1), Jul(Y+1),
     *   Sep(Y+1), Jan(Y+2), Mar(Y+2), Jul(Y+2)
     *
     * - Start GENAP:
     *   Feb(Y), Jul(Y), Sep(Y), Nov(Y),
     *   Feb(Y+1), Jul(Y+1), Sep(Y+1), Nov(Y+1)
     *
     * @return array<int, array{bulan:int, tahun:int, label:string}>
     */
    protected static function schedule8ForAnchor(string $semesterAwal, int|string|null $tahunAwal): array
    {
        $sem = self::normSemester($semesterAwal);
        $Y   = self::parseAnchorYear($tahunAwal);

        if ($sem === 'ganjil') {
            $rows = [
                ['m'=>9,  'y'=>$Y    ], // Sep(Y)
                ['m'=>1,  'y'=>$Y + 1], // Jan(Y+1)
                ['m'=>3,  'y'=>$Y + 1], // Mar(Y+1)
                ['m'=>7,  'y'=>$Y + 1], // Jul(Y+1)

                ['m'=>9,  'y'=>$Y + 1], // Sep(Y+1)
                ['m'=>1,  'y'=>$Y + 2], // Jan(Y+2)
                ['m'=>3,  'y'=>$Y + 2], // Mar(Y+2)
                ['m'=>7,  'y'=>$Y + 2], // Jul(Y+2)
            ];
        } else {
            $rows = [
                ['m'=>2,  'y'=>$Y    ], // Feb(Y)
                ['m'=>7,  'y'=>$Y    ], // Jul(Y)
                ['m'=>9,  'y'=>$Y    ], // Sep(Y)
                ['m'=>11, 'y'=>$Y    ], // Nov(Y)

                ['m'=>2,  'y'=>$Y + 1], // Feb(Y+1)
                ['m'=>7,  'y'=>$Y + 1], // Jul(Y+1)
                ['m'=>9,  'y'=>$Y + 1], // Sep(Y+1)
                ['m'=>11, 'y'=>$Y + 1], // Nov(Y+1)
            ];
        }

        return array_map(fn($r)=>[
            'bulan' => (int)$r['m'],
            'tahun' => (int)$r['y'],
            'label' => self::bulanNama((int)$r['m']).' '.(int)$r['y'],
        ], $rows);
    }

    /**
     * POLA 20x REGULER (Opsi A):
     * - 20 bulan kalender berturut-turut dari anchor (ganjil=Sep, genap=Feb)
     * - Agustus tetap ikut bila terlewati
     *
     * @return array<int, array{bulan:int, tahun:int, label:string}>
     */
    protected static function schedule20ForAnchor(string $semesterAwal, int|string|null $tahunAwal): array
    {
        $startMonth = self::anchorMonth($semesterAwal); // 9 atau 2
        $Y          = self::parseAnchorYear($tahunAwal);

        $start = Carbon::create($Y, $startMonth, 1);
        $out   = [];

        for ($i = 0; $i < 20; $i++) {
            $dt = $start->copy()->addMonths($i);
            $out[] = [
                'bulan' => (int)$dt->month,
                'tahun' => (int)$dt->year,
                'label' => self::bulanNama((int)$dt->month).' '.(int)$dt->year,
            ];
        }
        return $out;
    }

    /**
     * ENTRY-POINT REGULER (disarankan):
     * Bangun paket bulan berdasarkan ANCHOR MAHASISWA (bukan semester aktif global).
     *
     * @param  object $mahasiswaReguler  (butuh: semester_awal, tahun_awal/akademik)
     * @param  int    $angsuran          8 atau 20
     * @return array<int, array{bulan:int, tahun:int, label:string}>
     */
    public static function monthsForMahasiswa($mahasiswaReguler, int $angsuran): array
    {
        $semAwal = self::normSemester($mahasiswaReguler->semester_awal ?? 'ganjil');
        $thAwal  = self::parseAnchorYear($mahasiswaReguler->tahun_akademik ?? $mahasiswaReguler->tahun_awal ?? null);

        if ((int)$angsuran === 20) {
            return self::schedule20ForAnchor($semAwal, $thAwal);
        }
        // default 8x
        return self::schedule8ForAnchor($semAwal, $thAwal);
    }

    /**
     * ====== FUNGSI LAMA (kompatibilitas) ======
     * Paket bulan (bulan,tahun,label) utk SEMESTER AKTIF (menggunakan SemesterHelper).
     * Disarankan TIDAK dipakai untuk Reguler (pakai monthsForMahasiswa), tapi tetap dipertahankan.
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

        return self::generateMonths($semester, $angsuran);
    }

    /**
     * Generate N bulan berurut untuk semester aktif (fungsi lama – kompatibilitas).
     * - Ambil urutan dasar (10 bulan) lalu potong/ulang siklus hingga N terpenuhi.
     * - Tahun otomatis bertambah saat melewati batas siklus & untuk bulan Jan–Jun di semester ganjil.
     */
    protected static function generateMonths(string $semester, int $angsuran, ?Carbon $today = null): array
    {
        $baseSeq  = self::baseMonthSequence($semester);
        $baseYear = self::baseYearForSemester($semester, $today);
        $len      = count($baseSeq);

        $items = [];
        for ($i = 0; $i < $angsuran; $i++) {
            $cycle   = intdiv($i, $len);          // ke berapa kali putaran siklus 10-bulan
            $pos     = $i % $len;
            $bulan   = $baseSeq[$pos];
            $tahun   = $baseYear + $cycle;

            // Khusus ganjil: Jan–Jun milik tahun basis + 1 (di tiap siklus)
            if (strtolower($semester) === 'ganjil' && $bulan <= 6) {
                $tahun++;
            }

            $items[] = [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => self::bulanNama($bulan).' '.$tahun,
            ];
        }
        return $items;
    }

    /** Format rupiah */
    public static function rupiah(int $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    /**
     * Bangun kandidat key policy (tabel settings_reguler).
     * Prioritas: reguler:{TA}:{semester} > reguler:{TA} > total_tagihan_reguler
     */
    protected static function buildPolicyKeys(?string $tahunAkademik, ?string $semesterAwal): array
    {
        $ta  = trim((string) $tahunAkademik);
        $sem = strtolower(trim((string) $semesterAwal));

        $keys = [];
        if ($ta && ($sem === 'ganjil' || $sem === 'genap')) {
            $keys[] = "reguler:{$ta}:{$sem}";
        }
        if ($ta) {
            $keys[] = "reguler:{$ta}";
        }
        $keys[] = "total_tagihan_reguler";
        return $keys;
    }

    /**
     * Ambil total tagihan untuk mahasiswa Reguler.
     * Prioritas:
     * 1) Override per-individu (jika > 0)
     * 2) settings_reguler: reguler:{TA}:{semester}
     * 3) settings_reguler: reguler:{TA}
     * 4) settings_reguler: total_tagihan_reguler
     */
    public static function totalTagihanFor($mahasiswaReguler): int
    {
        if (!$mahasiswaReguler) return 0;

        // 1) Override individu kalau diisi dan > 0
        foreach (['total_tagihan', 'biaya_total', 'total', 'tagihan_total'] as $field) {
            if (isset($mahasiswaReguler->{$field}) && is_numeric($mahasiswaReguler->{$field})) {
                $v = (int) $mahasiswaReguler->{$field};
                if ($v > 0) return $v;
            }
        }

        // 2–4) Cohort policy via settings_reguler
        if (!Schema::hasTable('settings_reguler')) return 0;

        $keys = self::buildPolicyKeys(
            $mahasiswaReguler->tahun_akademik ?? null,
            $mahasiswaReguler->semester_awal ?? null
        );

        $rows = DB::table('settings_reguler')->whereIn('key', $keys)->get()->keyBy('key');

        foreach ($keys as $k) {
            if (isset($rows[$k])) {
                $raw = (string) $rows[$k]->value;
                $num = (int) preg_replace('/\D+/', '', $raw); // dukung "25.000.000"
                if ($num > 0) return $num;
            }
        }

        return 0; // biar controller/UX yang fail-closed (no angka palsu)
    }

    /** Versi tegas: error kalau policy/angka tidak ditemukan */
    public static function totalTagihanForOrFail($mahasiswaReguler): int
    {
        $total = self::totalTagihanFor($mahasiswaReguler);
        if ($total <= 0) {
            $ta  = (string)($mahasiswaReguler->tahun_akademik ?? '');
            $sem = strtolower((string)($mahasiswaReguler->semester_awal ?? ''));
            throw new \RuntimeException("Konfigurasi tarif Reguler belum tersedia untuk TA={$ta}, semester={$sem}.");
        }
        return $total;
    }

    /**
     * Preview lengkap: bagi nominal ke N angsuran, sisa ke invoice terakhir.
     * Otomatis pakai ANCHOR MAHASISWA bila tersedia; fallback ke semester aktif (logic lama).
     *
     * Output:
     * - total, total_formatted
     * - items: [bulan, tahun, label, amount, amount_formatted]
     * - angsuran
     */
    public static function previewWithAmounts($mahasiswaReguler, int $angsuran): array
    {
        // Prefer anchor mahasiswa (semester_awal & tahun_awal/akademik), kalau tidak ada → pakai logic lama
        $hasAnchor = isset($mahasiswaReguler->semester_awal) || isset($mahasiswaReguler->tahun_awal) || isset($mahasiswaReguler->tahun_akademik);

        $items = $hasAnchor
            ? self::monthsForMahasiswa($mahasiswaReguler, $angsuran)
            : self::monthsForActiveSemester($angsuran);

        $total = max(0, (int) self::totalTagihanFor($mahasiswaReguler));

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

    /**
     * (Opsional) Due date util – default tanggal 5 setiap bulan tagihan.
     * Format: 'YYYY-MM-DD'
     */
    public static function dueDate(int $year, int $month, int $day = 5): string
    {
        return Carbon::create($year, $month, $day)->toDateString();
    }

    /**
     * (Opsional) Attach due_date ke schedule.
     * @param array<int, array{bulan:int, tahun:int, label:string}> $schedule
     * @return array<int, array{bulan:int, tahun:int, label:string, due_date:string}>
     */
    public static function attachDueDate(array $schedule, int $day = 5): array
    {
        return array_map(function ($row) use ($day) {
            $row['due_date'] = self::dueDate((int)$row['tahun'], (int)$row['bulan'], $day);
            return $row;
        }, $schedule);
    }
}
