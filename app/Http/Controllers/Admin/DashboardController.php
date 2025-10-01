<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\MahasiswaReguler;
use App\Models\Invoice;
use App\Models\InvoiceReguler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /* ====================== Helpers umum ====================== */

    /** "26.500.000" / "26500000" / "Rp26jt" -> 26500000 */
    private function toInt($v): int
    {
        if ($v === null) return 0;
        if (is_int($v)) return $v;
        if (is_numeric($v)) return (int) $v;
        return (int) preg_replace('/\D+/', '', (string) $v);
    }

    /** WHERE IN case-insensitive (untuk status invoice) */
    private function whereInCI($q, string $column, array $values): void
    {
        $vals = array_map(fn ($s) => mb_strtolower($s), $values);
        $placeholders = implode(',', array_fill(0, count($vals), '?'));
        $q->whereRaw('LOWER('.$column.") IN ($placeholders)", $vals);
    }

    /* ====================== Helpers COHORT & GLOBAL ====================== */

    /**
     * Ambil NILAI COHORT terbaru dari tabel setting (apa pun TA/semesternya).
     * - $table: 'settings' (RPL) / 'settings_reguler' (Reguler)
     * - $prefix: 'rpl' / 'reguler'
     * - dukung kolom key|name + value
     * Return 0 kalau tidak ada.
     */
    private function getLatestCohortValue(string $table, string $prefix): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'value')) {
            return 0;
        }

        $keyCol = Schema::hasColumn($table, 'key')
            ? 'key'
            : (Schema::hasColumn($table, 'name') ? 'name' : null);

        if (!$keyCol) return 0;

        $q = DB::table($table)->where($keyCol, 'like', $prefix.':%:%');
        if (Schema::hasColumn($table, 'updated_at')) $q->orderByDesc('updated_at');
        if (Schema::hasColumn($table, 'id'))         $q->orderByDesc('id');

        $row = $q->first();
        return $this->toInt($row->value ?? 0);
    }

    /**
     * Ambil NILAI GLOBAL dari tabel setting.
     * - Bentuk kolom langsung: total_tagihan
     * - Bentuk KV: key/name ∈ $keys (contoh: total_tagihan_rpl / total_tagihan_reguler)
     */
    private function getGlobalDefault(string $table, array $keys): int
    {
        if (!Schema::hasTable($table)) return 0;

        // Kolom langsung total_tagihan
        if (Schema::hasColumn($table, 'total_tagihan')) {
            $q = DB::table($table);
            if (Schema::hasColumn($table, 'updated_at')) $q->orderByDesc('updated_at');
            if (Schema::hasColumn($table, 'id'))         $q->orderByDesc('id');

            $val = $this->toInt($q->value('total_tagihan'));
            if ($val > 0) return $val;
        }

        // Key-Value
        $keyCol = Schema::hasColumn($table, 'key')
            ? 'key'
            : (Schema::hasColumn($table, 'name') ? 'name' : null);

        if ($keyCol && Schema::hasColumn($table, 'value')) {
            $q = DB::table($table)->whereIn($keyCol, $keys);
            if (Schema::hasColumn($table, 'updated_at')) $q->orderByDesc('updated_at');
            if (Schema::hasColumn($table, 'id'))         $q->orderByDesc('id');

            $row = $q->first();
            return $this->toInt($row->value ?? 0);
        }

        return 0;
    }

    /**
     * Resolver simpel: ADA cohort → pakai cohort; kalau TIDAK ADA → pakai global; kalau dua-duanya kosong → 0.
     * Tanpa lihat tanggal/kalender.
     */
    private function resolveTarifCohortOrGlobal(string $settingsTable, string $prefix, array $globalKeys): int
    {
        $cohort = $this->getLatestCohortValue($settingsTable, $prefix);
        if ($cohort > 0) return $cohort;

        $global = $this->getGlobalDefault($settingsTable, $globalKeys);
        if ($global > 0) return $global;

        return 0;
    }

    /* ====================== Controller ====================== */

    public function index()
    {
        // ===== Jumlah mahasiswa (aktif) =====
        $aktifRpl = Mahasiswa::count();
        $aktifReg = MahasiswaReguler::count();

        // ===== Status invoice =====
        $pendingSet = ['Belum', 'Belum Lunas', 'Menunggu Verifikasi', 'Pending', 'Menunggu'];
        $lunasSet   = ['Lunas', 'Lunas (Otomatis)', 'Paid', 'Terverifikasi'];

        // ================== RPL ==================
        // Cohort kalau ada, else Global (tanpa kalender)
        $perMhsRpl = $this->resolveTarifCohortOrGlobal(
            'settings',           // tabel setting RPL
            'rpl',                // prefix key cohort
            ['total_tagihan_rpl', 'total_tagihan'] // kandidat global keys
        );

        $sudahLunasRpl = Invoice::query()
            ->tap(fn ($q) => $this->whereInCI($q, 'status', $lunasSet))
            ->count();

        $menungguRpl = Invoice::query()
            ->tap(fn ($q) => $this->whereInCI($q, 'status', $pendingSet))
            ->count();

        // ================== Reguler ==================
        // Cohort kalau ada, else Global (tanpa kalender)
        $perMhsReg = $this->resolveTarifCohortOrGlobal(
            'settings_reguler',
            'reguler',
            ['total_tagihan_reguler', 'total_tagihan']
        );

        $sudahLunasReg = InvoiceReguler::query()
            ->tap(fn ($q) => $this->whereInCI($q, 'status', $lunasSet))
            ->count();

        $menungguReg = InvoiceReguler::query()
            ->tap(fn ($q) => $this->whereInCI($q, 'status', $pendingSet))
            ->count();

        // ===== Widget opsional: daftar mahasiswa yang punya invoice =====
        $mahasiswaRPL = Mahasiswa::with(['invoices' => fn ($q) => $q->latest()->limit(12)])
            ->whereHas('invoices')->latest('id')->limit(10)->get();

        $mahasiswaReguler = MahasiswaReguler::with(['invoices' => fn ($q) => $q->latest()->limit(12)])
            ->whereHas('invoices')->latest('id')->limit(10)->get();

        // ===== Kirim ke view (alias dipertahankan biar Blade lama aman) =====
        return view('admin.dashboard', [
            // RPL
            'aktifRpl'               => $aktifRpl,
            'jumlahMahasiswaRPL'     => $aktifRpl,
            'totalTagihanRPL'        => $perMhsRpl,
            'totalTagihanRpl'        => $perMhsRpl, // alias
            'sudahLunasRpl'          => $sudahLunasRpl,
            'sudahLunasRPL'          => $sudahLunasRpl, // alias
            'jumlahLunasRPL'         => $sudahLunasRpl, // alias
            'menungguRpl'            => $menungguRpl,
            'menungguVerifikasiRPL'  => $menungguRpl,   // alias

            // Reguler
            'aktifReg'                   => $aktifReg,
            'jumlahMahasiswaReguler'     => $aktifReg,
            'totalTagihanReguler'        => $perMhsReg,
            'totalTagihanReg'            => $perMhsReg,  // alias
            'sudahLunasReg'              => $sudahLunasReg,
            'sudahLunasReguler'          => $sudahLunasReg, // alias
            'jumlahLunasReguler'         => $sudahLunasReg, // alias
            'menungguReg'                => $menungguReg,
            'menungguVerifikasiReguler'  => $menungguReg,   // alias

            // Widget
            'mahasiswaRPL'               => $mahasiswaRPL,
            'mahasiswaReguler'           => $mahasiswaReguler,
        ]);
    }
}
