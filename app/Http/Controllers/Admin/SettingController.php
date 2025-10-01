<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\Invoice;

class SettingController extends Controller
{
    /* ====================== Helpers ====================== */

    /** "30.000.000", "30 jt", "30,000,000" -> 30000000 */
    private function normalizeNominal($value): int
    {
        if ($value === null) return 0;
        $digits = preg_replace('/\D+/', '', (string)$value);
        return $digits === '' ? 0 : (int)$digits;
    }

    /** Cek apakah tabel settings pakai skema key-value (key/name + value) */
    private function settingsHasKV(): bool
    {
        return Schema::hasTable('settings') &&
               (Schema::hasColumn('settings', 'key') || Schema::hasColumn('settings', 'name')) &&
               Schema::hasColumn('settings', 'value');
    }

    /** Ambil nilai setting dengan aman di dua skema */
    private function getSetting(string $key, $default = null)
    {
        if (!Schema::hasTable('settings')) return $default;

        if ($this->settingsHasKV()) {
            $keyCol = Schema::hasColumn('settings', 'key') ? 'key' : 'name';
            $row = DB::table('settings')->where($keyCol, $key)->first();
            return $row->value ?? $default;
        }

        // fallback: kolom langsung "total_tagihan" hanya untuk default global
        if ($key === 'total_tagihan_rpl' && Schema::hasColumn('settings', 'total_tagihan')) {
            $row = DB::table('settings')->where('id', 1)->first();
            return $row->total_tagihan ?? $default;
        }

        return $default;
    }

    /** Set nilai setting (mendukung KV; fallback kolom langsung hanya untuk global) */
    private function setSetting(string $key, $value): void
    {
        if (!Schema::hasTable('settings')) return;

        if ($this->settingsHasKV()) {
            $keyCol = Schema::hasColumn('settings', 'key') ? 'key' : 'name';
            DB::table('settings')->updateOrInsert([$keyCol => $key], [
                'value'      => $value,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            return;
        }

        // fallback: kalau tidak KV, hanya dukung default global
        if ($key === 'total_tagihan_rpl' && Schema::hasColumn('settings', 'total_tagihan')) {
            DB::table('settings')->updateOrInsert(['id' => 1], [
                'total_tagihan' => $value,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]);
        }
    }

    /** Ambil daftar policy cohort (hanya untuk skema KV) */
    private function getPolicies()
    {
        if (!$this->settingsHasKV()) return collect();
        $keyCol = Schema::hasColumn('settings', 'key') ? 'key' : 'name';
        return DB::table('settings')
            ->where($keyCol, 'like', 'rpl:%')
            ->orderBy($keyCol)
            ->get();
    }

    /** Cek apakah cohort (TA + sem) sudah punya invoice RPL */
    private function cohortHasInvoices(string $ta, string $sem): bool
    {
        // Asumsi relasi Invoice -> mahasiswa (RPL) ada
        return Invoice::whereHas('mahasiswa', function ($q) use ($ta, $sem) {
            $q->where('tahun_akademik', $ta)
              ->where('semester_awal', strtolower($sem));
        })->exists();
    }

    /** Redirect aman: ke route kalau ada, kalau tidak -> back() */
    private function redirectToSettings()
    {
        return \Route::has('admin.settings.total-tagihan')
            ? redirect()->route('admin.settings.total-tagihan')
            : back();
    }

    /**
     * NEW: Ambil daftar Tahun Akademik per-semester dari tabel mahasiswa (RPL)
     * - Prioritas tabel: mahasiswas (default Eloquent) -> mahasiswa (fallback)
     * - Prioritas kolom: semester_awal|semester, tahun_akademik|tahun_akademik_awal|ta
     */
    private function getTaBySemester(): array
    {
        $result = [
            'ganjil' => collect(),
            'genap'  => collect(),
        ];

        $tableCandidates = ['mahasiswas', 'mahasiswa']; // nama tabel RPL di proyekmu
        $semCols = ['semester_awal', 'semester'];
        $taCols  = ['tahun_akademik', 'tahun_akademik_awal', 'ta'];

        foreach ($tableCandidates as $table) {
            if (!Schema::hasTable($table)) continue;

            $semCol = null;
            foreach ($semCols as $c) {
                if (Schema::hasColumn($table, $c)) { $semCol = $c; break; }
            }
            $taCol = null;
            foreach ($taCols as $c) {
                if (Schema::hasColumn($table, $c)) { $taCol = $c; break; }
            }
            if (!$semCol || !$taCol) continue;

            $rows = DB::table($table)
                ->select([$semCol.' as semester', $taCol.' as ta'])
                ->whereNotNull($semCol)
                ->whereNotNull($taCol)
                ->distinct()
                ->get();

            foreach ($rows as $r) {
                $s = strtolower(trim((string)$r->semester));
                $ta = trim((string)$r->ta);
                if (in_array($s, ['ganjil','genap'], true) && $ta !== '') {
                    $result[$s]->push($ta);
                }
            }

            // kalau sudah dapat dari salah satu tabel, cukup
            break;
        }

        return [
            'ganjil' => $result['ganjil']->filter()->unique()->sort()->values()->all(),
            'genap'  => $result['genap']->filter()->unique()->sort()->values()->all(),
        ];
    }

    /* ====================== Views ====================== */

    /**
     * Halaman edit tarif RPL:
     * - Default RPL (total_tagihan_rpl)
     * - (opsional) policy cohort "rpl:{TA}:{semester}"
     * - NEW: kirim taBySemester ke view untuk isi dropdown TA (sesuai semester)
     */
    public function editTotalTagihan()
    {
        $total       = (int) $this->getSetting('total_tagihan_rpl', 0);
        $policies    = $this->getPolicies(); // kosong jika bukan KV
        $taBySemester = $this->getTaBySemester();

        return view('admin.settings.edit-total-tagihan', [
            'total'        => $total,
            'policies'     => $policies,
            'taBySemester' => $taBySemester, // <-- dipakai di Blade untuk dropdown
        ]);
    }

    /* ====================== Save ====================== */

    /**
     * Simpan tarif RPL.
     * - scope=global  -> total_tagihan_rpl
     * - scope=cohort  -> rpl:{TA}:{semester}  (ganjil/genap)
     *   *Hard-lock*: bila cohort sudah punya invoice, tolak perubahan.
     * Tidak ada mass-update ke tabel mahasiswa/invoice.
     */
    public function updateTotalTagihan(Request $request)
    {
        if (!Schema::hasTable('settings')) {
            return back()->with('error', 'Tabel settings tidak ditemukan.');
        }

        // Aliases & normalisasi awal
        if ($request->has('semester') && !$request->has('semester_awal')) {
            $request->merge(['semester_awal' => $request->input('semester')]);
        }
        if ($request->has('tahunAkademik') && !$request->has('tahun_akademik')) {
            $request->merge(['tahun_akademik' => $request->input('tahunAkademik')]);
        }

        $scope = strtolower((string)$request->input('scope', 'global'));
        $semIn = $request->input('semester_awal');
        if ($semIn !== null) {
            $request->merge(['semester_awal' => strtolower((string)$semIn)]);
        }
        $request->merge(['scope' => in_array($scope, ['global','cohort'], true) ? $scope : 'global']);

        // Validasi dasar
        $request->validate([
            'scope'          => ['required','in:global,cohort'],
            'total_tagihan'  => ['required'],
        ]);

        // Validasi kondisional
        if ($request->input('scope') === 'cohort') {
            $request->validate([
                'tahun_akademik' => ['required','string','max:20'],
                'semester_awal'  => ['required', Rule::in(['ganjil','genap'])],
            ], [], [
                'tahun_akademik' => 'Tahun akademik',
                'semester_awal'  => 'Semester',
            ]);

            // Soft check: pastikan TA yang dipilih ada di daftar untuk semester tsb (kalau tersedia)
            $taBySemester = $this->getTaBySemester();
            $sem = (string)$request->input('semester_awal');
            $ta  = (string)$request->input('tahun_akademik');
            if (!empty($taBySemester[$sem]) && !in_array($ta, $taBySemester[$sem], true)) {
                return back()->with('error', 'Tahun Akademik tidak ditemukan untuk semester yang dipilih.');
            }
        } else {
            $request->validate([
                'tahun_akademik' => ['sometimes','nullable','string','max:20'],
                'semester_awal'  => ['sometimes','nullable', Rule::in(['ganjil','genap'])],
            ]);
        }

        // Normalisasi nominal
        $nominal = $this->normalizeNominal($request->input('total_tagihan'));
        if ($nominal <= 0) {
            return back()->with('error', 'Nominal tidak valid.');
        }

        // === Simpan ===
        if ($request->input('scope') === 'cohort') {
            // Pastikan skema settings mendukung KV; kalau tidak, beri info
            if (!$this->settingsHasKV()) {
                return back()->with('warning', 'Database settings kamu belum mendukung kebijakan per cohort. Hanya default global yang bisa disimpan.');
            }

            $ta  = (string)$request->input('tahun_akademik');
            $sem = (string)$request->input('semester_awal');
            $key = "rpl:{$ta}:{$sem}";

            // Hard-lock: cohort yang sudah punya invoice tidak boleh diubah
            if ($this->cohortHasInvoices($ta, $sem)) {
                return back()->with('error', "Gagal: cohort TA {$ta} ({$sem}) sudah memiliki invoice. Kebijakan tidak bisa diubah.");
            }

            $this->setSetting($key, $nominal);

            return $this->redirectToSettings()
                ->with('success', "Tarif RPL untuk TA {$ta} ({$sem}) disimpan: Rp " . number_format($nominal, 0, ',', '.'));
        }

        // scope = global
        $this->setSetting('total_tagihan_rpl', $nominal);

        return $this->redirectToSettings()
            ->with('success', "Default tarif RPL disimpan: Rp " . number_format($nominal, 0, ',', '.'));
    }
}
