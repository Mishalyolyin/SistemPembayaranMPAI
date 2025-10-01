<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Support\RplBilling;
use App\Models\Invoice;

class AngsuranController extends Controller
{
    /** ===== Aliases (kompat) ===== */
    public function index(Request $r): ViewContract { return $this->create($r); }
    public function form(Request $r): ViewContract  { return $this->create($r); }
    public function simpan(Request $r): RedirectResponse { return $this->store($r); }

    /**
     * Form pilih angsuran + preview nominal per bulan dari policy cohort.
     */
    public function create(Request $request): ViewContract
    {
        $mhs = Auth::guard('mahasiswa')->user();
        abort_unless($mhs, 403, 'Unauthorized');

        $opsiAngsuran = [4, 6, 10];
        $defaultPlan  = (int)($request->input('angsuran', $mhs->angsuran ?? 6));
        if (!in_array($defaultPlan, $opsiAngsuran, true)) {
            $defaultPlan = 6;
        }

        $sudahAda = $mhs->invoices()->exists();

        // Preview: ambil total & pembagian dari policy (TA + semester)
        $preview = null;
        try {
            $preview = RplBilling::previewWithAmounts($mhs, $defaultPlan);
            // $preview = [
            //   'total' => int,
            //   'total_formatted' => 'Rp ...',
            //   'angsuran' => int,
            //   'items' => [ ['bulan'=>9,'tahun'=>2025,'label'=>'September 2025','amount'=>..., 'amount_formatted'=>'Rp ...'], ... ]
            // ]
        } catch (\Throwable $e) {
            // jika policy belum ada → biarkan null, Blade bisa tampilkan pesan ramah
            $preview = null;
        }

        $view = $this->pilihView([
            'mahasiswa.angsuran.create',
            'mahasiswa.angsuran.form',
            'mahasiswa.pilih-angsuran',
            'mahasiswa.angsuran.index',
            'mahasiswa.angsuran',
        ]);

        return view($view, [
            'mahasiswa'    => $mhs,
            'opsiAngsuran' => $opsiAngsuran,
            'defaultPlan'  => $defaultPlan,
            'preview'      => $preview,
            'sudahAda'     => $sudahAda,
        ]);
    }

    /**
     * Simpan pilihan angsuran & generate invoices dari policy aktif.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'angsuran'    => ['required', 'integer', 'in:4,6,10'],
            'bulan_mulai' => ['nullable', 'regex:/^\d{4}\-(0[1-9]|1[0-2])$/'], // kalau kamu butuh simpan ini di profil
        ], [], [
            'angsuran'    => 'Pilihan angsuran',
            'bulan_mulai' => 'Bulan mulai',
        ]);

        $mhs = Auth::guard('mahasiswa')->user();
        abort_unless($mhs, 403, 'Unauthorized');

        // Cegah duplikasi invoice
        if ($mhs->invoices()->exists()) {
            return back()->with('info', 'Invoice sudah pernah dibuat.');
        }

        // Ambil total dari policy cohort (TA + semester) via resolver
        $total = RplBilling::totalTagihanFor($mhs);
        if ($total <= 0) {
            return back()->with('warning', 'Tagihan sedang disiapkan kampus. Silakan cek lagi nanti.');
        }

        $angs   = (int)$data['angsuran'];
        $months = RplBilling::monthsForActiveSemester($angs); // berisi bulan/tahun/label sesuai semester aktif

        // Bagi rata; sisa ditaruh di invoice terakhir
        $base = intdiv($total, $angs);
        $sisa = $total - ($base * $angs);

        foreach ($months as $i => $it) {
            $amount = $base + ($i === $angs - 1 ? $sisa : 0);

            Invoice::create([
                'mahasiswa_id' => $mhs->id,
                'jumlah'       => $amount,
                'status'       => 'Belum',
                // Mapping kolom: asumsi tabel kamu punya kolom 'bulan' (string) & 'keterangan'
                'bulan'        => $it['label'] ?? null,   // contoh: "September 2025"
                'keterangan'   => $it['label'] ?? null,
                // Kalau tabelmu punya 'tahun' numerik, kamu bisa aktifkan:
                // 'tahun'      => $it['tahun'] ?? null,
            ]);
        }

        // Simpan pilihan angsuran ke profil (optional, supaya UI ingat preferensi)
        if (empty($mhs->angsuran) || $mhs->angsuran !== $angs) {
            $mhs->angsuran = $angs;
            if (array_key_exists('bulan_mulai', $data)) {
                // simpan kalau kolom ada pada migration kamu
                if (in_array('bulan_mulai', $mhs->getFillable(), true)) {
                    $mhs->bulan_mulai = $data['bulan_mulai'] ?: null;
                }
            }
            $mhs->save();
        }

        // Redirect ke halaman invoice
        foreach ([
            'mahasiswa.invoices.index',
            'invoices.index',
            'mahasiswa.invoice.index',
            'mahasiswa.invoices',
            'invoices',
            'dashboard',
            'mahasiswa.dashboard',
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
        abort(404, 'View angsuran mahasiswa tidak ditemukan.');
    }
}
