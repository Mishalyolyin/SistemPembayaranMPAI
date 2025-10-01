<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\RegulerSetting;     // tabel: settings_reguler
use App\Models\InvoiceReguler;     // kalau butuh cross-check invoice

class RegulerSettingController extends Controller
{
    /* ====================== Helpers ====================== */

    /** "30.000.000", "30 jt", "30,000,000", "30m" -> 30000000 */
    private function normalizeNominal($raw): int
    {
        if ($raw === null) return 0;
        $s = strtolower(trim((string)$raw));

        // dukungan singkatan: "30 jt", "30juta", "30m"
        $s = preg_replace('/\s+/', '', $s);
        if (preg_match('/^\d+(\.\d+)?(jt|juta|m)$/', $s)) {
            $num = (float) preg_replace('/[^0-9.]/', '', $s);
            return (int) round($num * 1000000);
        }

        // buang semua non-digit
        $s = preg_replace('/[^0-9]/', '', $s);
        return (int) ($s === '' ? 0 : $s);
    }

    /** Cek apakah key ada di settings_reguler */
    private function settingsHasKV(string $key): bool
    {
        return RegulerSetting::where('key', $key)->exists();
    }

    /** Ambil nilai setting (int) dari settings_reguler */
    private function getSetting(string $key): int
    {
        $row = RegulerSetting::where('key', $key)->first();
        return (int) ($row->value ?? 0);
    }

    /** Set nilai setting (string) ke settings_reguler */
    private function setSetting(string $key, int $value): void
    {
        RegulerSetting::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }

    /** Ambil daftar Tahun Akademik per semester dari tabel mahasiswa_reguler */
    private function getTaBySemester(): array
    {
        $result = [
            'ganjil' => collect(),
            'genap'  => collect(),
        ];

        $table = 'mahasiswa_reguler';
        if (!Schema::hasTable($table)) {
            return [
                'ganjil' => [],
                'genap'  => [],
            ];
        }

        $semCols = ['semester_awal', 'semester'];
        $taCols  = ['tahun_akademik', 'tahun_akademik_awal', 'ta'];

        // temukan kolom yang tersedia
        $semCol = null; foreach ($semCols as $c) { if (Schema::hasColumn($table, $c)) { $semCol = $c; break; } }
        $taCol  = null; foreach ($taCols  as $c) { if (Schema::hasColumn($table, $c)) { $taCol  = $c; break; } }

        if (!$semCol || !$taCol) {
            return [
                'ganjil' => [],
                'genap'  => [],
            ];
        }

        $rows = DB::table($table)
            ->select($semCol.' as sem', DB::raw('REPLACE('.$taCol.', " ", "") as ta'))
            ->whereNotNull($semCol)
            ->whereNotNull($taCol)
            ->where($taCol, '<>', '')
            ->get();

        $ganjil = $rows->where('sem', 'ganjil')->pluck('ta')->unique()->values()->all();
        $genap  = $rows->where('sem', 'genap')->pluck('ta')->unique()->values()->all();

        return [
            'ganjil' => $ganjil,
            'genap'  => $genap,
        ];
    }

    /** Redirect aman ke halaman edit reguler settings */
    private function redirectToEdit()
    {
        return \Route::has('admin.settings.reguler-settings.edit')
            ? redirect()->route('admin.settings.reguler-settings.edit')
            : back();
    }

    /* ====================== Views ====================== */

    /**
     * Halaman edit:
     * - default (total_tagihan_reguler)
     * - daftar policy cohort (key reguler:*)
     * - opsi dropdown TA per semester (kalau tersedia di DB)
     */
    public function edit()
    {
        $default  = $this->getSetting('total_tagihan_reguler');
        $policies = RegulerSetting::where('key', 'like', 'reguler:%')
            ->orderBy('key')
            ->get();

        $taBySemester = $this->getTaBySemester(); // ['ganjil'=>[], 'genap'=>[]]

        return view('admin.reguler-settings.edit', [
            'total'        => $default,
            'policies'     => $policies,
            'taBySemester' => $taBySemester,
        ]);
    }

    /**
     * Simpan:
     * - scope=global -> total_tagihan_reguler
     * - scope=cohort -> reguler:{TA} atau reguler:{TA}:{semester}
     */
    public function update(Request $request)
    {
        // normalisasi input angka
        $nominal = $this->normalizeNominal($request->input('total_tagihan'));

        // konsolidasi nama field
        $semIn = $request->input('semester') ?? $request->input('semester_awal');
        if ($semIn !== null) {
            $request->merge(['semester_awal' => strtolower((string) $semIn)]);
        }
        $scope = $request->input('scope', 'global');
        $request->merge(['scope' => in_array($scope, ['global','cohort'], true) ? $scope : 'global']);

        // Validasi wajib
        $request->validate([
            'scope'         => ['required','in:global,cohort'],
            'total_tagihan' => ['required'],
        ], [], [
            'total_tagihan' => 'Total Tagihan',
        ]);

        // Validasi cohort
        if ($request->input('scope') === 'cohort') {
            $request->validate([
                'tahun_akademik' => ['required','string','max:20'],
                'semester_awal'  => ['required', Rule::in(['ganjil','genap'])],
            ], [], [
                'tahun_akademik' => 'Tahun Akademik',
                'semester_awal'  => 'Semester',
            ]);

            $ta  = preg_replace('/\s+/', '', (string)$request->input('tahun_akademik'));
            $sem = (string)$request->input('semester_awal');

            // Key prioritas tertinggi: TA + semester
            $key = "reguler:{$ta}:{$sem}";
            $this->setSetting($key, $nominal);

            // (opsional) juga simpan fallback per-TA bila belum ada
            $key2 = "reguler:{$ta}";
            if (!$this->settingsHasKV($key2)) {
                $this->setSetting($key2, $nominal);
            }

            // NB: tidak mengubah invoice yang telah terbit.
            return $this->redirectToEdit()
                ->with('success', "Tarif Reguler untuk TA {$ta} ({$sem}) disimpan: Rp " . number_format($nominal, 0, ',', '.'));
        }

        // scope = global
        $this->setSetting('total_tagihan_reguler', $nominal);

        return $this->redirectToEdit()
            ->with('success', "Default tarif Reguler disimpan: Rp " . number_format($nominal, 0, ',', '.'));
    }
}
