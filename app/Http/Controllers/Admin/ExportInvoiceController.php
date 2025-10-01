<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Models\Mahasiswa;
use App\Models\Invoice;
use App\Models\MahasiswaReguler;
use App\Models\InvoiceReguler;

class ExportInvoiceController extends Controller
{
    /**
     * Status yang dihitung sebagai LUNAS (dibandingkan dalam lowercase).
     * - RPL: 'Lunas' & 'Lunas (Otomatis)'
     * - Reguler: umumnya 'Lunas' (tetap aman karena perbandingan lowercase).
     */
    private function paidStatusLower(): array
    {
        return ['lunas', 'lunas (otomatis)'];
    }

    /**
     * Sedikit guard agar nilai tidak diparse Excel sebagai formula (=, +, -, @ di awal).
     * Tidak mengubah data di DB; hanya output CSV agar aman dibuka di Excel.
     */
    private function csvSafe(string $value): string
    {
        $value = trim($value);
        if ($value === '') return $value;
        $first = $value[0];
        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'".$value; // tampil sama di Excel, tidak jadi formula
        }
        return $value;
    }

    /**
     * Stream CSV dengan BOM (UTF-8) supaya Excel nyaman.
     */
    private function streamCsv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            $writer($out);
            fclose($out);
        }, $filename, [
            'Content-Type'  => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    // ============================================================
    // ===============           R P L           ==================
    // ============================================================

    /**
     * Export Invoices RPL → CSV: No, Nama, NIM, Total Kelunasan
     * Mode:
     * - ?mode=single&nim=123... | &mahasiswa_id=ID
     * - ?mode=all → hanya mahasiswa yang SELURUH invoice-nya sudah lunas
     */
    public function exportRpl(Request $request): StreamedResponse
    {
        $mode      = strtolower((string) $request->get('mode', 'all')); // single | all
        $paidLower = $this->paidStatusLower();

        $filename = $mode === 'single'
            ? 'invoices_rpl_single_' . date('Y-m-d') . '.csv'
            : 'invoices_rpl_all_lunas_' . date('Y-m-d') . '.csv';

        return $this->streamCsv($filename, function ($out) use ($mode, $paidLower, $request) {
            // Header CSV
            fputcsv($out, ['No', 'Nama', 'NIM', 'Total Kelunasan']);

            if ($mode === 'single') {
                // Cari mahasiswa via ID atau NIM
                $m = null;
                $id = $request->get('mahasiswa_id');
                $nim = $request->get('nim');

                if (!is_null($id) && $id !== '') {
                    $m = Mahasiswa::find($id);
                } elseif (!is_null($nim) && $nim !== '') {
                    $m = Mahasiswa::where('nim', trim((string) $nim))->first();
                }

                if (!$m) {
                    fputcsv($out, [1, 'TIDAK DITEMUKAN', $this->csvSafe((string)($nim ?? $id)), 0]);
                    return;
                }

                $totalPaid = (int) Invoice::where('mahasiswa_id', $m->id)
                    ->whereIn(DB::raw('LOWER(status)'), $paidLower)
                    ->sum('jumlah');

                fputcsv($out, [1, $this->csvSafe($m->nama), $this->csvSafe($m->nim), $totalPaid]);
                return;
            }

            // MODE: all → hanya mahasiswa yang seluruh invoice-nya sudah lunas (tidak ada unpaid sama sekali)
            // unpaid_count = jumlah invoice yang statusnya BUKAN paid (atau status NULL)
            $agg = Invoice::selectRaw("
                    mahasiswa_id,
                    SUM(CASE WHEN LOWER(status) IN ('lunas','lunas (otomatis)') THEN jumlah ELSE 0 END) AS total_paid,
                    SUM(CASE WHEN status IS NULL OR LOWER(status) NOT IN ('lunas','lunas (otomatis)') THEN 1 ELSE 0 END) AS unpaid_count,
                    COUNT(*) AS invoice_count
                ")
                ->groupBy('mahasiswa_id')
                ->having('unpaid_count', '=', 0)
                ->get()
                ->keyBy('mahasiswa_id');

            if ($agg->isEmpty()) {
                // Tidak ada baris, CSV berisi header saja.
                return;
            }

            $mahasiswas = Mahasiswa::whereIn('id', $agg->keys())->get()->keyBy('id');

            $no = 1;
            foreach ($agg as $mhsId => $row) {
                $m = $mahasiswas[$mhsId] ?? null;
                if (!$m) continue;
                fputcsv($out, [
                    $no++,
                    $this->csvSafe($m->nama),
                    $this->csvSafe($m->nim),
                    (int) $row->total_paid
                ]);
            }
        });
    }

    // ============================================================
    // =============        R E G U L E R        ==================
    // ============================================================

    /**
     * Export Invoices Reguler → CSV: No, Nama, NIM, Total Kelunasan
     * Mode:
     * - ?mode=single&nim=123... | &mahasiswa_id=ID
     * - ?mode=all → hanya mahasiswa reguler yang seluruh invoice-nya sudah lunas
     */
    public function exportReguler(Request $request): StreamedResponse
    {
        $mode      = strtolower((string) $request->get('mode', 'all')); // single | all
        $paidLower = $this->paidStatusLower();

        $filename = $mode === 'single'
            ? 'invoices_reguler_single_' . date('Y-m-d') . '.csv'
            : 'invoices_reguler_all_lunas_' . date('Y-m-d') . '.csv';

        return $this->streamCsv($filename, function ($out) use ($mode, $paidLower, $request) {
            // Header CSV
            fputcsv($out, ['No', 'Nama', 'NIM', 'Total Kelunasan']);

            if ($mode === 'single') {
                $m = null;
                $id = $request->get('mahasiswa_id');
                $nim = $request->get('nim');

                if (!is_null($id) && $id !== '') {
                    $m = MahasiswaReguler::find($id);
                } elseif (!is_null($nim) && $nim !== '') {
                    $m = MahasiswaReguler::where('nim', trim((string) $nim))->first();
                }

                if (!$m) {
                    fputcsv($out, [1, 'TIDAK DITEMUKAN', $this->csvSafe((string)($nim ?? $id)), 0]);
                    return;
                }

                $totalPaid = (int) InvoiceReguler::where('mahasiswa_reguler_id', $m->id)
                    ->whereIn(DB::raw('LOWER(status)'), $paidLower)
                    ->sum('jumlah');

                fputcsv($out, [1, $this->csvSafe($m->nama), $this->csvSafe($m->nim), $totalPaid]);
                return;
            }

            // MODE: all → hanya mahasiswa reguler yang seluruh invoice-nya sudah lunas
            $agg = InvoiceReguler::selectRaw("
                    mahasiswa_reguler_id,
                    SUM(CASE WHEN LOWER(status) IN ('lunas','lunas (otomatis)') THEN jumlah ELSE 0 END) AS total_paid,
                    SUM(CASE WHEN status IS NULL OR LOWER(status) NOT IN ('lunas','lunas (otomatis)') THEN 1 ELSE 0 END) AS unpaid_count,
                    COUNT(*) AS invoice_count
                ")
                ->groupBy('mahasiswa_reguler_id')
                ->having('unpaid_count', '=', 0)
                ->get()
                ->keyBy('mahasiswa_reguler_id');

            if ($agg->isEmpty()) {
                return; // header-only
            }

            $mahasiswas = MahasiswaReguler::whereIn('id', $agg->keys())->get()->keyBy('id');

            $no = 1;
            foreach ($agg as $mhsId => $row) {
                $m = $mahasiswas[$mhsId] ?? null;
                if (!$m) continue;
                fputcsv($out, [
                    $no++,
                    $this->csvSafe($m->nama),
                    $this->csvSafe($m->nim),
                    (int) $row->total_paid
                ]);
            }
        });
    }
}
