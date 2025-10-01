<?php

namespace App\Http\Controllers\MahasiswaReguler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Support\RegulerBilling;
use App\Models\InvoiceReguler;

class AngsuranRegulerController extends Controller
{
    /** ===== Aliases (kompat lama) ===== */
    public function form(Request $r): ViewContract { return $this->create($r); }
    public function simpan(Request $r): RedirectResponse { return $this->store($r); }

    /**
     * Form pilih angsuran + preview nominal per bulan dari policy cohort (TA + semester).
     */
    public function create(Request $request): ViewContract
    {
        $mhs = Auth::guard('mahasiswa_reguler')->user();
        abort_unless($mhs, 403, 'Unauthorized');

        // Opsi angsuran yang dipakai untuk Reguler
        $opsiAngsuran = [8, 20]; // konsisten dengan desain Reguler
        $defaultPlan  = (int)($request->input('angsuran', $mhs->angsuran ?? 8));
        if (!in_array($defaultPlan, $opsiAngsuran, true)) $defaultPlan = 8;

        $sudahAda = $mhs->invoicesReguler()->exists();

        // Preview cicilan berdasarkan policy cohort
        $preview = null;
        try {
            $preview = RegulerBilling::previewWithAmounts($mhs, $defaultPlan);
            // $preview:
            //  - total, total_formatted, angsuran
            //  - items: [{bulan,tahun,label,amount,amount_formatted}, ...]
        } catch (\Throwable $e) {
            $preview = null; // biar Blade bisa nunjukin pesan “policy belum diset”
        }

        $view = $this->pilihView([
            'mahasiswa_reguler.angsuran.create',
            'mahasiswa_reguler.pilih-angsuran',     // kompat lama
            'mahasiswa_reguler.angsuran.form',      // kompat lama
            'mahasiswa_reguler.angsuran.index',
            'mahasiswa_reguler.angsuran',
        ]);

        return view($view, compact('mhs','opsiAngsuran','defaultPlan','preview','sudahAda'));
    }

    /**
     * Simpan pilihan angsuran & generate invoices dari policy aktif (tanpa recalc yang lama).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'angsuran'    => ['required','integer','in:8,20'],
            'bulan_mulai' => ['nullable','regex:/^\d{4}\-(0[1-9]|1[0-2])$/'], // opsional; disimpan ke profil kalau ada kolomnya
        ], [], [
            'angsuran'    => 'Pilihan angsuran',
            'bulan_mulai' => 'Bulan mulai',
        ]);

        $mhs = Auth::guard('mahasiswa_reguler')->user();
        abort_unless($mhs, 403, 'Unauthorized');

        // Cegah duplikasi invoice
        if ($mhs->invoicesReguler()->exists()) {
            return back()->with('info', 'Invoice sudah pernah dibuat.');
        }

        // Ambil total dari policy cohort (TA + semester)
        $total = RegulerBilling::totalTagihanFor($mhs);
        if ($total <= 0) {
            return back()->with('warning', 'Tagihan sedang disiapkan kampus. Silakan cek lagi nanti.');
        }

        $angs   = (int)$data['angsuran'];
        $months = RegulerBilling::monthsForActiveSemester($angs); // daftar bulan/tahun/label sesuai semester aktif

        // Bagi rata; sisa di invoice terakhir
        $base = intdiv($total, $angs);
        $sisa = $total - ($base * $angs);

        foreach ($months as $i => $it) {
            $amount = $base + ($i === $angs - 1 ? $sisa : 0);

            InvoiceReguler::create([
                'mahasiswa_reguler_id' => $mhs->id,
                'jumlah'               => $amount,
                'status'               => 'Belum',
                // mapping kolom sesuai migrasi kamu:
                'bulan'                => $it['label'] ?? null, // ex: "Februari 2026"
                'keterangan'           => $it['label'] ?? null,
                // kalau ada kolom tahun numerik, aktifkan:
                // 'tahun'             => $it['tahun'] ?? null,
            ]);
        }

        // Simpan preferensi angsuran & bulan_mulai (kalau ada kolomnya)
        if (empty($mhs->angsuran) || $mhs->angsuran !== $angs) {
            $mhs->angsuran = $angs;
        }
        if (property_exists($mhs, 'bulan_mulai') || in_array('bulan_mulai', $mhs->getFillable(), true)) {
            $mhs->bulan_mulai = $data['bulan_mulai'] ?? null;
        }
        $mhs->save();

        // Redirect yang aman (fallback ke beberapa route)
        foreach ([
            'reguler.invoices.index',
            'mahasiswa_reguler.invoice.index', // alias lama
            'reguler.dashboard',
        ] as $r) {
            if (Route::has($r)) {
                return redirect()->route($r)->with('success', 'Invoice berhasil dibuat.');
            }
        }

        return redirect('/')->with('success', 'Invoice berhasil dibuat.');
    }

    /* ================= Helpers ================= */

    private function pilihView(array $kandidat): string
    {
        foreach ($kandidat as $v) {
            if (view()->exists($v)) return $v;
        }
        abort(404, 'View angsuran mahasiswa reguler tidak ditemukan.');
    }
}
