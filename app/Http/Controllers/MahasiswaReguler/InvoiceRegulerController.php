<?php

namespace App\Http\Controllers\MahasiswaReguler;

use App\Http\Controllers\Controller;
use App\Models\InvoiceReguler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use App\Support\RegulerBilling;
use Carbon\Carbon;

class InvoiceRegulerController extends Controller
{
    /**
     * Tampilkan form setup angsuran.
     */
    public function setupAngsuran()
    {
        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();
        return view('mahasiswa_reguler.pilih-angsuran', compact('mahasiswa'));
    }

    /**
     * Daftar & ringkasan invoice reguler.
     */
    public function index()
    {
        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();

        if (!$mahasiswa->angsuran) {
            return redirect()->route('reguler.invoice.setup');
        }

        $totalTagihan = (int) $mahasiswa->total_tagihan;

        $invoices = $this->getSortedInvoices($mahasiswa->id);

        $totalPaid = $invoices
            ->filter(function ($inv) {
                $s = strtolower((string) ($inv->status ?? ''));
                return in_array($s, ['lunas', 'lunas (otomatis)', 'terverifikasi'], true);
            })
            ->sum(function ($inv) {
                return (int) ($inv->jumlah ?? $inv->nominal ?? 0);
            });

        $remaining = $totalTagihan - $totalPaid;

        return view('mahasiswa_reguler.invoice', compact(
            'mahasiswa',
            'invoices',
            'totalTagihan',
            'totalPaid',
            'remaining'
        ));
    }

    /**
     * Simpan pilihan angsuran & generate ulang invoice (kompat lama).
     */
    public function simpanAngsuran(Request $request)
    {
        $data = $request->validate([
            'angsuran'    => 'required|integer|min:1|max:20',
            'bulan_mulai' => 'required|string',
        ]);

        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();

        $mahasiswa->update([
            'angsuran'    => $data['angsuran'],
            'bulan_mulai' => $data['bulan_mulai'],
        ]);

        $totalTagihan = (int) $mahasiswa->total_tagihan;
        $count        = max(1, (int) $data['angsuran']);
        $perBulan     = $count ? intdiv($totalTagihan, $count) : 0;

        // hapus batch lama
        InvoiceReguler::where('mahasiswa_reguler_id', $mahasiswa->id)->delete();

        // plan 8/20 = schedule anchor
        if (in_array((int) $data['angsuran'], [8, 20], true)) {
            if (method_exists(RegulerBilling::class, 'monthsForMahasiswa')) {
                $schedule = RegulerBilling::monthsForMahasiswa($mahasiswa, (int) $data['angsuran']);
            } elseif (method_exists(RegulerBilling::class, 'monthsForActiveSemester')) {
                $schedule = RegulerBilling::monthsForActiveSemester((int) $data['angsuran']);
            } else {
                $schedule = null;
            }

            if (is_array($schedule)) {
                $this->generateInvoices($mahasiswa->id, $schedule, $count, $perBulan);
            } else {
                $this->generateInvoices($mahasiswa->id, $data['bulan_mulai'], $count, $perBulan);
            }
        } else {
            $this->generateInvoices($mahasiswa->id, $data['bulan_mulai'], $count, $perBulan);
        }

        return redirect()->route('reguler.invoice.index')->with('success', 'Invoice reguler berhasil digenerate.');
    }

    /**
     * Upload bukti transfer.
     */
    public function upload(Request $request, InvoiceReguler $invoice)
    {
        $this->authorizeInvoice($invoice);

        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();
        if (strcasecmp((string)($mahasiswa->status ?? ''), 'lulus') === 0) {
            return back()->with('error', 'Akun sudah Lulus. Upload bukti baru tidak diizinkan.');
        }

        $s = strtolower((string)($invoice->status ?? ''));
        if (in_array($s, ['lunas','lunas (otomatis)','terverifikasi'], true)) {
            return back()->with('error', 'Tagihan sudah diverifikasi. Upload bukti tidak diizinkan.');
        }

        $request->validate([
            'bukti' => 'required|image|mimes:jpeg,jpg,png|max:10240',
        ]);

        $ext      = $request->file('bukti')->extension();
        $filename = time().'_'.$mahasiswa->id.'_'.$invoice->id.'.'.$ext;

        $request->file('bukti')->storeAs('bukti_reguler', $filename, 'public');

        $invoice->update([
            'bukti'  => $filename,
            'status' => 'Menunggu Verifikasi',
        ]);

        return back()->with('success', 'Bukti reguler berhasil diupload.');
    }

    /**
     * Reset / hapus bukti transfer.
     */
    public function reset(InvoiceReguler $invoice)
    {
        $this->authorizeInvoice($invoice);

        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();
        if (strcasecmp((string)($mahasiswa->status ?? ''), 'lulus') === 0) {
            return back()->with('error', 'Akun sudah Lulus. Reset bukti tidak diizinkan.');
        }

        if (in_array($invoice->status, ['Lunas','Lunas (Otomatis)'], true)) {
            return back()->with('error', 'Tagihan sudah diverifikasi, tidak bisa direset.');
        }

        if ($invoice->bukti) {
            Storage::disk('public')->delete("bukti_reguler/{$invoice->bukti}");
            Storage::disk('public')->delete("bukti/{$invoice->bukti}");
            $invoice->update(['bukti' => null, 'status' => 'Belum']);
        }

        return back()->with('success', 'Bukti reguler berhasil direset.');
    }

    /**
     * Detail satu invoice.
     */
    public function detail(InvoiceReguler $invoice)
    {
        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();
        $this->authorizeInvoice($invoice);
        return view('mahasiswa_reguler.invoice-detail', compact('mahasiswa','invoice'));
    }

    /**
     * Form Kwitansi (isi data opsional sebelum unduh).
     */
    public function kwitansiForm(InvoiceReguler $invoice)
    {
        $this->authorizeInvoice($invoice);
        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();

        return view('mahasiswa_reguler.kwitansi-form', [
            'mahasiswa' => $mahasiswa,
            'invoice'   => $invoice,
        ]);
    }

    /**
     * Download Kwitansi (single).
     */
    public function kwitansiDownload(Request $request, InvoiceReguler $invoice)
    {
        $this->authorizeInvoice($invoice);

        $status = strtolower((string)($invoice->status ?? ''));
        if (!in_array($status, ['lunas','lunas (otomatis)','terverifikasi'], true)) {
            return back()->with('error', 'Kwitansi hanya tersedia untuk tagihan yang sudah lunas.');
        }

        $data = $request->validate([
            'angkatan' => 'nullable|digits_between:2,4',
            'no_hp'    => 'nullable|regex:/^[0-9+\-\s]{8,20}$/',
        ], [
            'no_hp.regex' => 'Format No. HP tidak valid.',
        ]);

        $mhs = Auth::guard('mahasiswa_reguler')->user();

        $viewData = [
            'mahasiswaReguler' => (object) array_merge($mhs->toArray(), [
                'angkatan' => $data['angkatan'] ?? $mhs->angkatan,
                'no_hp'    => $data['no_hp']    ?? $mhs->no_hp,
            ]),
            'mahasiswa' => $mhs,
            'invoice'   => $invoice,
            'invoices'  => collect([$invoice]), // <-- kompat untuk view yang pakai $invoices
            'ttdData'   => $this->getTtdBase64IfAny(),
            'today'     => now(),
        ];

        $fileName = sprintf('Kwitansi_%s_%s.pdf', $mhs->nim ?? 'NIM', preg_replace('/\s+/', '_', (string)$invoice->bulan));

        $candidates = [
            'mahasiswa_reguler.pdf.kwitansi-reguler-single',
            'mahasiswa_reguler.kwitansi',
        ];

        return $this->renderPdf($candidates, $viewData, $fileName, /*download*/ true);
    }

    /**
     * Preview inline (opsional; untuk tombol "Kwitansi" non-download).
     */
    public function kwitansiDirect(InvoiceReguler $invoice)
    {
        $this->authorizeInvoice($invoice);
        $status = strtolower((string)($invoice->status ?? ''));
        if (!in_array($status, ['lunas','lunas (otomatis)','terverifikasi'], true)) {
            abort(403, 'Kwitansi hanya untuk tagihan yang sudah lunas.');
        }

        $mhs = Auth::guard('mahasiswa_reguler')->user();
        $viewData = [
            'mahasiswaReguler' => $mhs,
            'mahasiswa' => $mhs,
            'invoice'   => $invoice,
            'invoices'  => collect([$invoice]), // <-- kompat
            'ttdData'   => $this->getTtdBase64IfAny(),
            'today'     => now(),
        ];

        $fileName = sprintf('Kwitansi_%s_%s.pdf', $mhs->nim ?? 'NIM', preg_replace('/\s+/', '_', (string)$invoice->bulan));
        $candidates = [
            'mahasiswa_reguler.pdf.kwitansi-reguler-single',
            'mahasiswa_reguler.kwitansi',
        ];

        return $this->renderPdf($candidates, $viewData, $fileName, /*download*/ false);
    }

    /**
     * Download Kwitansi BULK (semua yang Lunas).
     * (FIX: tanpa order kolom 'tahun'; sort pakai util yang sama)
     */
    public function kwitansiBulk(Request $request)
    {
        $mhsId = Auth::guard('mahasiswa_reguler')->id();
        $mhs   = Auth::guard('mahasiswa_reguler')->user();

        $all = $this->getSortedInvoices($mhsId);

        $rows = $all->filter(function ($inv) {
            $s = strtolower((string)($inv->status ?? ''));
            return in_array($s, ['lunas','terverifikasi','lunas (otomatis)'], true);
        })->values();

        if ($rows->isEmpty()) {
            return back()->with('warning', 'Belum ada invoice lunas untuk dibuat kwitansi.');
        }

        $viewData = [
            'mahasiswa' => $mhs,
            'rows'      => $rows,
            'invoices'  => $rows, // <-- kompat untuk view yang expect $invoices
            'today'     => now(),
        ];

        $fileName = 'Kwitansi_Semua_'.$mhs->nim.'.pdf';
        $candidates = [
            'mahasiswa_reguler.pdf.kwitansi-bulk-table-reguler',
            'mahasiswa_reguler.kwitansi-bulk-table-reguler',
        ];

        return $this->renderPdf($candidates, $viewData, $fileName, /*download*/ true);
    }

    /**
     * Ambil & urutkan invoice secara stabil.
     */
    protected function getSortedInvoices(int $userId)
    {
        $query = InvoiceReguler::where('mahasiswa_reguler_id', $userId);

        if (Schema::hasColumn('invoices_reguler', 'angsuran_ke')) {
            return $query->orderBy('angsuran_ke')->orderBy('id')->get();
        }

        $rows = $query->get();
        $bulanMap = [
            'Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,
            'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12,
        ];

        return $rows->sortBy(function ($inv) use ($bulanMap) {
            $label = (string) ($inv->bulan ?? '');
            $year = 0; $month = 0;
            if (preg_match('/^(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})$/u', $label, $m)) {
                $month = $bulanMap[$m[1]] ?? 0;
                $year  = (int) $m[2];
            }
            return ($year * 100) + $month; // YYYYMM
        })->values();
    }

    /**
     * Generate record invoice tiap bulan (mode baru & lama).
     *
     * @param  int                     $userId
     * @param  string|array<int,array> $startBulanOrSchedule
     * @param  int                     $count
     * @param  int                     $amount
     */
    protected function generateInvoices($userId, $startBulanOrSchedule, $count, $amount): void
    {
        $mahasiswa     = Auth::guard('mahasiswa_reguler')->user();
        $totalTagihan  = (int) ($mahasiswa->total_tagihan ?? 0);
        $n             = max(1, (int) $count);
        $base          = (int) $amount;
        $sisa          = max(0, $totalTagihan - ($base * $n));

        $hasDueDate    = Schema::hasColumn('invoices_reguler', 'jatuh_tempo');
        $hasAngsuranKe = Schema::hasColumn('invoices_reguler', 'angsuran_ke');

        // MODE BARU: schedule array
        if (is_array($startBulanOrSchedule)) {
            $schedule = array_values($startBulanOrSchedule);
            $nItems   = min($n, count($schedule));

            for ($i = 0; $i < $nItems; $i++) {
                $it       = $schedule[$i] ?? [];
                $bulanNum = (int) ($it['bulan'] ?? 0);
                $tahunNum = (int) ($it['tahun'] ?? now()->year);

                $label = ($it['label'] ?? null)
                    ?: (method_exists(RegulerBilling::class, 'bulanNama')
                        ? RegulerBilling::bulanNama($bulanNum).' '.$tahunNum
                        : ($this->bulanNamaFallback($bulanNum).' '.$tahunNum));

                $data = [
                    'mahasiswa_reguler_id' => $userId,
                    'bulan'                => $label,
                    'jumlah'               => $base + (($i === $nItems - 1) ? $sisa : 0),
                    'status'               => 'Belum',
                ];

                if ($hasDueDate) {
                    $data['jatuh_tempo'] = method_exists(RegulerBilling::class, 'dueDate')
                        ? RegulerBilling::dueDate($tahunNum, $bulanNum, 5)
                        : Carbon::create($tahunNum, max(1,min(12,$bulanNum)), 5)->format('Y-m-d');
                }
                if ($hasAngsuranKe) {
                    $data['angsuran_ke'] = $i + 1;
                }

                InvoiceReguler::create($data);
            }
            return;
        }

        // MODE LAMA: rolling dari bulan_mulai
        $bulanList = [
            'Januari','Februari','Maret','April','Mei','Juni',
            'Juli','Agustus','September','Oktober','November','Desember'
        ];

        $startBulan = (string) $startBulanOrSchedule;
        $startIndex = array_search($startBulan, $bulanList, true);
        if ($startIndex === false) $startIndex = 0;

        $baseYear = (int) now()->year;

        for ($i = 0; $i < $n; $i++) {
            $namaBulan = $bulanList[($startIndex + $i) % 12];
            $label     = $namaBulan.' '.$baseYear;

            $data = [
                'mahasiswa_reguler_id' => $userId,
                'bulan'                => $label,
                'jumlah'               => $base + (($i === $n - 1) ? $sisa : 0),
                'status'               => 'Belum',
            ];

            if ($hasDueDate) {
                $bulanNum = array_search($namaBulan, $bulanList, true) + 1;
                $data['jatuh_tempo'] = method_exists(RegulerBilling::class, 'dueDate')
                    ? RegulerBilling::dueDate($baseYear, $bulanNum, 5)
                    : Carbon::create($baseYear, $bulanNum, 5)->format('Y-m-d');
            }
            if ($hasAngsuranKe) {
                $data['angsuran_ke'] = $i + 1;
            }

            InvoiceReguler::create($data);
        }
    }

    /**
     * Cek kepemilikan invoice.
     */
    protected function authorizeInvoice(InvoiceReguler $invoice): void
    {
        if ($invoice->mahasiswa_reguler_id !== Auth::guard('mahasiswa_reguler')->id()) {
            abort(403);
        }
    }

    /**
     * Fallback nama bulan Indonesia.
     */
    protected function bulanNamaFallback(int $m): string
    {
        $nm = [
            1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April',
            5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus',
            9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'
        ];
        return $nm[$m] ?? 'Bulan-'.$m;
    }

    /**
     * Ambil base64 tanda tangan jika ada.
     */
    protected function getTtdBase64IfAny(): ?string
    {
        $path = storage_path('app/public/kwitansi/ttd.png');
        if (file_exists($path)) {
            return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
        }
        return null;
    }

    /**
     * Helper render PDF dengan fallback kandidat view.
     */
    protected function renderPdf(array $viewCandidates, array $data, string $fileName, bool $download)
    {
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $view = null;
            foreach ($viewCandidates as $v) {
                if (View::exists($v)) { $view = $v; break; }
            }
            $view = $view ?: $viewCandidates[0];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data)->setPaper('A4', 'portrait');
            return $download ? $pdf->download($fileName) : $pdf->stream($fileName);
        }

        foreach ($viewCandidates as $v) {
            if (View::exists($v)) {
                return view($v, $data)->with('warning', 'Paket dompdf belum terpasang—silakan cetak ke PDF dari browser.');
            }
        }
        return view($viewCandidates[0], $data)->with('warning', 'Paket dompdf belum terpasang—silakan cetak ke PDF dari browser.');
    }
}
