<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Services\BrivaService;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /** ----------------------------------------------------------------
     *  Alias resource: show() -> detail()
     *  ---------------------------------------------------------------- */
    public function show(...$args)
    {
        if (method_exists($this, 'detail')) {
            return $this->detail(...$args);
        }
        abort(404, 'Halaman detail invoice tidak tersedia.');
    }

    /** Form pilih skema angsuran (pertama kali). */
    public function setupAngsuran()
    {
        $mahasiswa = Auth::guard('mahasiswa')->user();

        // Kalau sudah punya skema, langsung ke daftar invoice
        if (!empty($mahasiswa->angsuran) && (int) $mahasiswa->angsuran > 0) {
            return $this->redirectToInvoiceIndex();
        }

        return view('mahasiswa.pilih-angsuran', compact('mahasiswa'));
    }

    /**
     * Simpan skema angsuran & generate invoice.
     * (tanpa SemesterHelper)
     */
    public function simpanAngsuran(Request $request)
    {
        // Terima "angsuran" (baru) ATAU "jumlah" (legacy)
        $angs = (int) ($request->input('angsuran', $request->input('jumlah')));
        if (!in_array($angs, [4, 6, 10], true)) {
            return back()->with('error', 'Pilihan angsuran tidak valid.');
        }

        /** @var \App\Models\Mahasiswa $user */
        $user = Auth::guard('mahasiswa')->user();

        // Sumber nominal total (profil -> settings RPL -> settings umum)
        $totalTagihan = (int) (
            $user->total_tagihan
            ?? $user->tagihan
            ?? DB::table('settings')->whereIn(DB::raw('LOWER(`key`)'), ['total_tagihan_rpl', 'total_tagihan'])
                ->orderByRaw("CASE LOWER(`key`) WHEN 'total_tagihan_rpl' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->value('value')
            ?? 0
        );

        // Simpan skema di profil (hindari mass-assignment)
        $user->angsuran = $angs;
        if (Schema::hasColumn($user->getTable(), 'bulan_mulai') && $request->filled('bulan_mulai')) {
            $user->bulan_mulai = $request->input('bulan_mulai'); // 2025-09 / "September" / "9"
        }
        $user->save();

        // Bangun daftar bulan tagihan (REKAP-compliant, kecuali kalau ada override bulan_mulai)
        $bulanTagihan = $this->buildBulanTagihan(
            $user->semester_awal,
            $user->tahun_akademik,
            $angs,
            $user->bulan_mulai ?? null
        );

        // Generate (atomic + anti-dobel)
        $ok = $this->generateInvoicesFor(
            $user->id,
            (int) $totalTagihan,
            $bulanTagihan,
            $angs,
            $user->semester_awal,
            $user->tahun_akademik
        );

        if (!$ok) {
            // kalau ada race & unik nabrak, kita fallback sukses senyap
            return $this->redirectToInvoiceIndex()->with('info', 'Tagihan sudah tersedia.');
        }

        return $this->redirectToInvoiceIndex()->with('success', 'Tagihan berhasil dibuat.');
    }

    /**
     * Daftar invoice mahasiswa.
     * - kalau belum set skema → redirect ke setup
     * - kalau skema ada tapi invoice kosong → generate otomatis
     */
    public function index()
    {
        /** @var \App\Models\Mahasiswa $mahasiswa */
        $mahasiswa = Auth::guard('mahasiswa')->user();

        if (empty($mahasiswa->angsuran) || (int) $mahasiswa->angsuran <= 0) {
            return redirect()->route('mahasiswa.angsuran.form');
        }

        // Ambil invoice yang ada
        $invoices = Invoice::where('mahasiswa_id', $mahasiswa->id)->get();

        // Jika belum ada → generate otomatis berdasarkan skema tersimpan
        if ($invoices->isEmpty()) {
            $bulanTagihan = $this->buildBulanTagihan(
                $mahasiswa->semester_awal,
                $mahasiswa->tahun_akademik,
                (int) $mahasiswa->angsuran,
                $mahasiswa->bulan_mulai ?? null
            );
            $totalTagihan = (int) (
                $mahasiswa->total_tagihan
                ?? DB::table('settings')->whereIn(DB::raw('LOWER(`key`)'), ['total_tagihan_rpl', 'total_tagihan'])
                    ->orderByRaw("CASE LOWER(`key`) WHEN 'total_tagihan_rpl' THEN 0 ELSE 1 END")
                    ->orderByDesc('updated_at')
                    ->value('value')
                ?? 0
            );

            $this->generateInvoicesFor(
                $mahasiswa->id,
                $totalTagihan,
                $bulanTagihan,
                (int) $mahasiswa->angsuran,
                $mahasiswa->semester_awal,
                $mahasiswa->tahun_akademik
            );

            $invoices = Invoice::where('mahasiswa_id', $mahasiswa->id)->get();
        }

        // Ringkasan
        $totalTagihan = (int) (
            $mahasiswa->total_tagihan
            ?? DB::table('settings')->whereIn(DB::raw('LOWER(`key`)'), ['total_tagihan_rpl', 'total_tagihan'])
                ->orderByRaw("CASE LOWER(`key`) WHEN 'total_tagihan_rpl' THEN 0 ELSE 1 END")
                ->orderByDesc('updated_at')
                ->value('value')
            ?? 0
        );

        $totalPaid = $invoices
            ->filter(function ($inv) {
                $s = strtolower((string) ($inv->status ?? ''));
                return in_array($s, ['lunas', 'lunas (otomatis)', 'terverifikasi'], true);
            })
            ->sum(function ($inv) {
                return (int) ($inv->jumlah ?? $inv->nominal ?? 0);
            });

        $remaining = max(0, $totalTagihan - $totalPaid);

        // Sort: prioritas angsuran_ke jika ada; else parse "NamaBulan Tahun"; else created_at
        if (Schema::hasColumn('invoices', 'angsuran_ke')) {
            $invoices = $invoices->sortBy('angsuran_ke')->values();
        } else {
            $bulanMap = [
                'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4, 'Mei' => 5, 'Juni' => 6,
                'Juli' => 7, 'Agustus' => 8, 'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12,
            ];
            $invoices = $invoices->sortBy(function ($inv) use ($bulanMap) {
                $label = (string) ($inv->bulan ?? '');
                if (preg_match('/^(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})$/u', $label, $m)) {
                    $month = $bulanMap[$m[1]] ?? 0;
                    $year  = (int) $m[2];
                    return ($year * 100) + $month;
                }
                return (int) Carbon::parse($inv->created_at)->format('Ymd');
            })->values();
        }

        return view('mahasiswa.invoice', compact('mahasiswa', 'invoices', 'totalTagihan', 'totalPaid', 'remaining'));
    }

    /** Detail invoice */
    public function detail($id)
    {
        $mahasiswa = Auth::guard('mahasiswa')->user();

        try {
            $invoice = Invoice::where('mahasiswa_id', $mahasiswa->id)->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->redirectToInvoiceIndex()->with('error', 'Invoice tidak ditemukan.');
        }

        $invoices = Invoice::where('mahasiswa_id', $mahasiswa->id)->get();

        return view('mahasiswa.invoice-detail', compact('mahasiswa', 'invoice', 'invoices'));
    }

    /** Upload bukti transfer → status Menunggu Verifikasi. */
    public function upload(Request $request, $id)
    {
        /** @var \App\Models\Mahasiswa $user */
        $user = Auth::guard('mahasiswa')->user();

        // Lock jika sudah Lulus
        if (strcasecmp((string) ($user->status ?? ''), 'lulus') === 0) {
            return back()->with('error', 'Akun Anda sudah Lulus. Upload bukti baru tidak diizinkan.');
        }

        // izinkan JPG/PNG/PDF
        $request->validate([
            'bukti' => 'required|file|mimes:jpeg,jpg,png,pdf|max:40960',
        ]);

        try {
            $invoice = Invoice::where('mahasiswa_id', $user->id)->findOrFail($id);

            // Tidak boleh upload ke invoice yang sudah Lunas/Terverifikasi
            $s = strtolower((string) ($invoice->status ?? ''));
            if (in_array($s, ['lunas', 'lunas (otomatis)', 'terverifikasi'], true)) {
                return back()->with('error', 'Tagihan sudah diverifikasi. Upload bukti tidak diizinkan.');
            }

            $file = $request->file('bukti');

            if ($file && $file->isValid()) {
                $ext      = strtolower($file->getClientOriginalExtension());
                $filename = now()->format('Ymd_His') . '_' . $user->id . '_' . $invoice->id . '.' . $ext;

                // simpan ke disk public/bukti
                $file->storeAs('bukti', $filename, 'public');

                // simpan aman (tanpa mass-assignment)
                $invoice->bukti  = $filename;
                $invoice->status = 'Menunggu Verifikasi';
                if (Schema::hasColumn($invoice->getTable(), 'uploaded_at')) {
                    $invoice->uploaded_at = now();
                }
                $invoice->save();
            }

            return back()->with('success', 'Bukti berhasil di-upload dan menunggu verifikasi.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Upload gagal: ' . $e->getMessage());
        }
    }

    /** Reset bukti → hapus file & status kembali Belum. */
    public function reset($id)
    {
        /** @var \App\Models\Mahasiswa $user */
        $user = Auth::guard('mahasiswa')->user();

        // Lock jika sudah Lulus
        if (strcasecmp((string) ($user->status ?? ''), 'lulus') === 0) {
            return back()->with('error', 'Akun Anda sudah Lulus. Reset bukti tidak diizinkan.');
        }

        $invoice = Invoice::where('mahasiswa_id', $user->id)->findOrFail($id);

        if (in_array($invoice->status, ['Lunas', 'Lunas (Otomatis)', 'Terverifikasi'], true)) {
            return back()->with('error', 'Tagihan ini sudah diverifikasi dan tidak bisa di-reset.');
        }

        if ($invoice->bukti) {
            Storage::disk('public')->delete('bukti/' . $invoice->bukti);
            $invoice->bukti  = null;
        }
        $invoice->status = 'Belum';
        if (Schema::hasColumn($invoice->getTable(), 'uploaded_at')) {
            $invoice->uploaded_at = null;
        }
        $invoice->save();

        return back()->with('success', 'Bukti berhasil di-reset.');
    }

    /**
     * Download kwitansi per tagihan (tanpa form).
     */
    public function kwitansi($id)
    {
        /** @var \App\Models\Mahasiswa $user */
        $user = Auth::guard('mahasiswa')->user();

        $invoice = Invoice::where('mahasiswa_id', $user->id)->findOrFail($id);

        $st = strtolower(trim($invoice->status ?? ''));
        $okStatuses = ['lunas', 'lunas (otomatis)', 'terverifikasi', 'paid'];
        if (!in_array($st, $okStatuses, true)) {
            return back()->with('error', 'Kwitansi hanya tersedia untuk tagihan yang sudah Lunas.');
        }

        // derive angkatan & tanggal bayar sesuai template
        $angkatan = '';
        if (!empty($user->tahun_akademik) && preg_match('/(\d{4})/', $user->tahun_akademik, $m)) {
            $angkatan = $m[1];
        }
        $tanggal_bayar = $invoice->verified_at
            ?? $invoice->updated_at
            ?? $invoice->uploaded_at
            ?? now();

        $payload = [
            'invoice'       => $invoice,
            'mahasiswa'     => $user,
            'angkatan'      => $angkatan,
            'tanggal_bayar' => $tanggal_bayar,
        ];

        $view = view()->exists('mahasiswa.pdf.kwitansi')
            ? 'mahasiswa.pdf.kwitansi'
            : (view()->exists('pdf.kwitansi_rpl') ? 'pdf.kwitansi_rpl' : 'pdf.kwitansi');

        $pdf = Pdf::loadView($view, $payload)->setPaper('A4', 'portrait');

        return $pdf->download("kwitansi-{$invoice->id}.pdf");
    }

    /**
     * Kwitansi BULK (semua tagihan LUNAS) → 1 PDF tabel panjang.
     */
    public function kwitansiBulk(Request $request)
    {
        /** @var \App\Models\Mahasiswa $user */
        $user = Auth::guard('mahasiswa')->user();

        $okStatuses = ['lunas','lunas (otomatis)','terverifikasi','paid'];

        $invoices = Invoice::where('mahasiswa_id', $user->id)
            ->whereIn(DB::raw('LOWER(status)'), $okStatuses)
            ->get();

        if ($invoices->isEmpty()) {
            return back()->with('warning', 'Belum ada tagihan yang Lunas.');
        }

        // Sort: angsuran_ke bila ada, jika tidak urutkan berdasarkan "NamaBulan YYYY"
        if (Schema::hasColumn('invoices', 'angsuran_ke')) {
            $invoices = $invoices->sortBy('angsuran_ke')->values();
        } else {
            $bulanMap = [
                'Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,
                'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12,
            ];
            $invoices = $invoices->sortBy(function ($inv) use ($bulanMap) {
                $label = (string) ($inv->bulan ?? '');
                if (preg_match('/^(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})$/u', $label, $m)) {
                    $month = $bulanMap[$m[1]] ?? 0;
                    $year  = (int) $m[2];
                    return ($year * 100) + $month;
                }
                return (int) Carbon::parse($inv->created_at)->format('Ymd');
            })->values();
        }

        // Angkatan (opsional)
        $angkatan = '';
        if (!empty($user->tahun_akademik) && preg_match('/(\d{4})/', $user->tahun_akademik, $m)) {
            $angkatan = $m[1];
        }

        $total = $invoices->sum(function($inv){
            return (int)($inv->jumlah ?? $inv->nominal ?? 0);
        });

        $payload = [
            'mahasiswa' => $user,
            'invoices'  => $invoices,
            'angkatan'  => $angkatan,
            'total'     => $total,
        ];

        // View tabel bulk (landscape)
        $view = view()->exists('mahasiswa.pdf.kwitansi-bulk-table')
            ? 'mahasiswa.pdf.kwitansi-bulk-table'
            : 'pdf.kwitansi-bulk-table';

        $pdf = Pdf::loadView($view, $payload)->setPaper('A4', 'landscape');

        $nama = Str::slug($user->nama ?? 'mahasiswa');
        return $pdf->download("kwitansi-semua-lunas-{$nama}.pdf");
    }

    /** (OPTIONAL legacy) Tampilkan form kwitansi — kompat */
    public function showKwitansiForm($id)
    {
        $invoice = Invoice::where('mahasiswa_id', Auth::guard('mahasiswa')->id())->findOrFail($id);
        return view('mahasiswa.kwitansi_form', compact('invoice'));
    }

    /** (OPTIONAL legacy) Download kwitansi via POST dari form lama */
    public function downloadKwitansi(Request $request, $id)
    {
        $user    = Auth::guard('mahasiswa')->user();
        $invoice = Invoice::where('mahasiswa_id', $user->id)->findOrFail($id);

        $st = strtolower(trim($invoice->status ?? ''));
        $okStatuses = ['lunas', 'lunas (otomatis)', 'terverifikasi', 'paid'];
        if (!in_array($st, $okStatuses, true)) {
            return back()->with('error', 'Kwitansi hanya tersedia untuk tagihan yang sudah Lunas.');
        }

        $angkatan = '';
        if (!empty($user->tahun_akademik) && preg_match('/(\d{4})/', $user->tahun_akademik, $m)) {
            $angkatan = $m[1];
        }
        $tanggal_bayar = $invoice->verified_at
            ?? $invoice->updated_at
            ?? $invoice->uploaded_at
            ?? now();

        $payload = [
            'invoice'       => $invoice,
            'mahasiswa'     => $user,
            'angkatan'      => $angkatan,
            'tanggal_bayar' => $tanggal_bayar,
        ];

        $view = view()->exists('mahasiswa.pdf.kwitansi')
            ? 'mahasiswa.pdf.kwitansi'
            : (view()->exists('pdf.kwitansi_rpl') ? 'pdf.kwitansi_rpl' : 'pdf.kwitansi');

        $pdf = Pdf::loadView($view, $payload)->setPaper('A4', 'portrait');

        return $pdf->download("kwitansi-{$invoice->id}.pdf");
    }

    /* ======================== HELPERS ======================== */

    /**
     * Bangun daftar bulan tagihan sepanjang N angsuran.
     */
    private function buildBulanTagihan(?string $semesterAwal, ?string $tahunAkademik, int $jumlahAngsuran, ?string $bulanMulai = null): array
    {
        $mapBulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $nameToNum = array_change_key_case(array_flip($mapBulan), CASE_LOWER);

        // Parse tahun akademik (format "YYYY/YYYY+1"), fallback tahun berjalan
        $yearStart = (int) date('Y');
        if (!empty($tahunAkademik) && preg_match('/(\d{4})\s*\/\s*(\d{4})/', $tahunAkademik, $mm)) {
            $yearStart = (int) $mm[1];
        }

        // Jika ada override bulan_mulai → sekuensial biasa (behaviour lama)
        if (!empty($bulanMulai)) {
            $startMonth = 9;
            $bm = trim($bulanMulai);
            if (is_numeric($bm)) {
                $startMonth = max(1, min(12, (int) $bm));
            } else {
                $key = strtolower($bm);
                if (isset($nameToNum[$key])) {
                    $startMonth = (int) $nameToNum[$key];
                }
            }

            $out = [];
            for ($i = 0; $i < $jumlahAngsuran; $i++) {
                $monthNum   = (($startMonth - 1 + $i) % 12) + 1;
                $yearOffset = intdiv(($startMonth - 1 + $i), 12);
                $year       = $yearStart + $yearOffset;
                $out[]      = $mapBulan[$monthNum] . ' ' . $year;
            }
            return $out;
        }

        $sem = strtolower((string) $semesterAwal);
        $listMonths = [];

        if ($sem === 'ganjil') {
            if ($jumlahAngsuran === 4) {
                $listMonths = [9, 12, 3, 6];
            } elseif ($jumlahAngsuran === 6) {
                $listMonths = [9, 11, 1, 3, 5, 6];
            } else { // 10x
                $listMonths = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6];
            }
        } else { // genap
            if ($jumlahAngsuran === 4) {
                $listMonths = [2, 5, 8, 11];
            } elseif ($jumlahAngsuran === 6) {
                $listMonths = [2, 4, 6, 8, 10, 12];
            } else { // 10x
                $listMonths = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
            }
        }

        // Konversi ke label "NamaBulan YYYY"
        $out = [];
        foreach ($listMonths as $m) {
            $y = $yearStart;
            if ($sem === 'ganjil' && $m <= 6) {
                $y = $yearStart + 1;
            }
            $out[] = ($mapBulan[$m] ?? 'Unknown') . ' ' . $y;
        }
        return $out;
    }

    /**
     * Generator invoice yang aman & idempotent untuk 1 mahasiswa.
     * (B1 FIXED: ISI HANYA va_cust_code — TIDAK generate VA lokal)
     */
    private function generateInvoicesFor(
        int $mahasiswaId,
        int $totalTagihan,
        array $bulanTagihan,
        int $jumlahAngsuran,
        ?string $semesterAwal,
        ?string $tahunAkademik
    ): bool {
        if ($jumlahAngsuran <= 0 || $totalTagihan <= 0 || empty($bulanTagihan)) {
            return false;
        }

        // Nominal per angsuran (sisa masuk cicilan terakhir)
        $per  = intdiv($totalTagihan, $jumlahAngsuran);
        $sisa = $totalTagihan - ($per * $jumlahAngsuran);

        $hasAngsKe = Schema::hasColumn('invoices', 'angsuran_ke');

        // Ambil cust_code (atau fallback NIM last-N) SEKALI untuk mahasiswa ini
        $mhs = DB::table('mahasiswas')->where('id', $mahasiswaId)->select('nim','cust_code')->first();
        $custCodeFixed = $mhs?->cust_code ?? BrivaService::makeCustCode((string) ($mhs->nim ?? $mahasiswaId));

        return DB::transaction(function () use ($mahasiswaId, $bulanTagihan, $jumlahAngsuran, $per, $sisa, $hasAngsKe, $semesterAwal, $tahunAkademik, $custCodeFixed) {
            // Reset semua invoice milik mahasiswa ini (sesuai flow kamu)
            Invoice::where('mahasiswa_id', $mahasiswaId)->delete();

            foreach (array_values($bulanTagihan) as $i => $bulan) {
                $nominal = $per + ($i === ($jumlahAngsuran - 1) ? $sisa : 0);

                $payload = [
                    'mahasiswa_id' => $mahasiswaId,
                    'bulan'        => $bulan,
                    'status'       => 'Belum',
                    'jumlah'       => $nominal,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];

                // angsuran_ke (jika ada kolomnya)
                if ($hasAngsKe) {
                    $payload['angsuran_ke'] = $i + 1;
                }

                // metadata lain (opsional)
                if (Schema::hasColumn('invoices', 'kode')) {
                    $payload['kode'] = Str::upper(Str::random(10));
                }
                if (Schema::hasColumn('invoices', 'semester')) {
                    $payload['semester'] = $semesterAwal;
                }
                if (Schema::hasColumn('invoices', 'tahun_akademik')) {
                    $payload['tahun_akademik'] = $tahunAkademik;
                }
                if (Schema::hasColumn('invoices', 'jatuh_tempo')) {
                    // estimasi tgl 5 tiap bulan
                    [$namaBulan, $tahun] = array_pad(preg_split('/\s+/', $bulan), 2, date('Y'));
                    $map = ['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
                    $mnum = $map[$namaBulan] ?? (int) date('n');
                    $yr   = is_numeric($tahun) ? (int)$tahun : (int) date('Y');
                    $payload['jatuh_tempo'] = Carbon::create($yr, $mnum, 5)->toDateString();
                }

                // === B1: ISI HANYA va_cust_code (kalau kolom ada). TIDAK set va_full/va_briva_no. ===
                if (Schema::hasColumn('invoices', 'va_cust_code')) {
                    $payload['va_cust_code'] = $custCodeFixed;
                }
                if (Schema::hasColumn('invoices', 'va_full')) {
                    $payload['va_full'] = null; // biarkan diisi webhook va-assigned
                }
                if (Schema::hasColumn('invoices', 'va_briva_no')) {
                    $payload['va_briva_no'] = null; // biarkan diisi webhook jika diperlukan
                }
                if (Schema::hasColumn('invoices', 'va_expired_at')) {
                    $payload['va_expired_at'] = null; // menunggu info bank (opsional)
                }

                try {
                    // idempotent-safe dengan unique composite di DB
                    Invoice::create($payload);

                    // ⚠️ JANGAN panggil createVaFor() / makeFullVa() — VA datang dari BRI via webhook.
                } catch (QueryException $e) {
                    // Kalau kebentur unik (race/double submit), lanjut aja
                    if (!str_contains($e->getMessage(), 'uniq_rpl_mhs_angsuran')) {
                        throw $e;
                    }
                }
            }

            return true;
        });
    }

    /** Helper: redirect ke index invoice dengan nama route apapun yang tersedia */
    private function redirectToInvoiceIndex()
    {
        foreach ([
            'mahasiswa.invoices.index',
            'mahasiswa.invoice.index',
            'invoices.index',
            'mahasiswa.invoices',
            'invoices',
        ] as $r) {
            if (Route::has($r)) {
                return redirect()->route($r);
            }
        }
        return redirect('/mahasiswa/invoices');
    }
}
