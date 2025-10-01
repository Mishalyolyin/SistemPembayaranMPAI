<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceReguler;
use App\Models\MahasiswaReguler; // ✅ pakai model Reguler
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class InvoiceRegulerController extends Controller
{
    public function index(Request $request)
    {
        $status  = $request->get('status', 'semua');
        $search  = trim($request->get('search', ''));
        $sem     = $request->get('semester');          // 'ganjil' / 'genap'
        $ta      = $request->get('tahun_akademik');    // '2025/2026'
        $layout  = in_array($request->get('layout', 'group'), ['group','flat'], true) ? $request->get('layout', 'group') : 'group';
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(10, min($perPage, 1000));
        $perPage = (int) (round($perPage / 10) * 10);

        $table       = (new InvoiceReguler)->getTable();
        $hasBulan    = Schema::hasColumn($table, 'bulan');
        $hasKet      = Schema::hasColumn($table, 'keterangan');
        $hasTA       = Schema::hasColumn($table, 'tahun_akademik');
        $hasSemester = Schema::hasColumn($table, 'semester');
        $hasJatuh    = Schema::hasColumn($table, 'jatuh_tempo');

        /* ======================== MODE FLAT ======================== */
        if ($layout === 'flat') {
            $q = InvoiceReguler::query()->latest('id');

            if (method_exists(new InvoiceReguler, 'mahasiswaReguler')) $q->with('mahasiswaReguler');
            elseif (method_exists(new InvoiceReguler, 'mahasiswa'))    $q->with('mahasiswa');

            if ($search !== '') {
                $q->where(function ($x) use ($search, $hasBulan, $hasKet, $hasTA) {
                    if ($hasBulan) $x->orWhere('bulan', 'like', "%{$search}%");
                    if ($hasKet)   $x->orWhere('keterangan', 'like', "%{$search}%");
                    if ($hasTA)    $x->orWhere('tahun_akademik', 'like', "%{$search}%");

                    $x->orWhereHas('mahasiswaReguler', function ($m) use ($search) {
                        $m->where('nama', 'like', "%{$search}%")->orWhere('nim', 'like', "%{$search}%");
                    });
                    $x->orWhereHas('mahasiswa', function ($m) use ($search) {
                        $m->where('nama', 'like', "%{$search}%")->orWhere('nim', 'like', "%{$search}%");
                    });
                });
            }

            if ($status !== 'semua') {
                $map = [
                    'pending' => ['belum','belum lunas','menunggu verifikasi','pending','menunggu','unpaid'],
                    'lunas'   => ['lunas','paid','terverifikasi','lunas (otomatis)'],
                    'ditolak' => ['ditolak','reject','gagal','batal','invalid'],
                ];
                $allowed = $map[strtolower($status)] ?? $map['pending'];
                $q->whereIn(DB::raw('LOWER(status)'), $allowed);
            }

            if ($sem) {
                $q->where(function ($w) use ($sem, $hasSemester) {
                    if ($hasSemester) $w->orWhere('semester', $sem);
                    $w->orWhereHas('mahasiswaReguler', fn($m)=>$m->where('semester_awal',$sem));
                    $w->orWhereHas('mahasiswa',       fn($m)=>$m->where('semester_awal',$sem));
                });
            }

            if ($ta) {
                $q->where(function ($w) use ($ta, $hasTA) {
                    if ($hasTA) $w->orWhere('tahun_akademik', 'like', "%{$ta}%");
                    $w->orWhereHas('mahasiswaReguler', fn($m)=>$m->where('tahun_akademik','like',"%{$ta}%"));
                    $w->orWhereHas('mahasiswa',       fn($m)=>$m->where('tahun_akademik','like',"%{$ta}%"));
                });
            }

            $q->orderByRaw("CASE WHEN LOWER(status)='menunggu verifikasi' THEN 0 ELSE 1 END");
            if ($hasJatuh) $q->orderBy('jatuh_tempo','asc');
            $q->orderBy('id','asc');

            $invoices = $q->paginate($perPage)->withQueryString();

            // isi tampilan semester/TA dari profil jika kosong (untuk view)
            $invoices->getCollection()->transform(function ($inv) {
                $m = $inv->mahasiswaReguler ?? $inv->mahasiswa ?? null;
                if ($m) {
                    if (empty($inv->semester))       $inv->semester       = $m->semester_awal ?? $inv->semester;
                    if (empty($inv->tahun_akademik)) $inv->tahun_akademik = $m->tahun_akademik ?? $inv->tahun_akademik;
                }
                return $inv;
            });

            return view('admin.invoices-reguler.index', compact(
                'invoices', 'status', 'search', 'sem', 'ta', 'layout', 'perPage'
            ));
        }

        /* ======================== MODE GROUP ======================== */
        if (!class_exists(MahasiswaReguler::class) || !method_exists(new MahasiswaReguler, 'invoicesReguler')) {
            // fallback aman ke FLAT kalau relasi belum tersedia
            return redirect()->to($request->fullUrlWithQuery(['layout'=>'flat','page'=>1]));
        }

        $ms = MahasiswaReguler::query()
            ->withCount(['invoicesReguler as pending_count' => function ($q) use ($sem, $ta, $search, $hasBulan, $hasKet, $hasTA, $hasSemester) {
                $q->when($hasSemester && $sem, fn($qq)=>$qq->where('semester', $sem))
                  ->when($hasTA && $ta,        fn($qq)=>$qq->where('tahun_akademik','like',"%{$ta}%"))
                  ->whereRaw("LOWER(status)='menunggu verifikasi'")
                  ->when($search !== '', function ($qq) use ($search, $hasBulan, $hasKet, $hasTA) {
                      $qq->where(function ($x) use ($search, $hasBulan, $hasKet, $hasTA) {
                          if ($hasBulan) $x->orWhere('bulan', 'like', "%{$search}%");
                          if ($hasKet)   $x->orWhere('keterangan', 'like', "%{$search}%");
                          if ($hasTA)    $x->orWhere('tahun_akademik', 'like', "%{$search}%");
                      });
                  });
            }])
            ->whereHas('invoicesReguler', function ($q) use ($sem, $ta, $status, $search, $hasBulan, $hasKet, $hasTA, $hasSemester) {
                $q->when($hasSemester && $sem, fn($qq)=>$qq->where('semester',$sem))
                  ->when($hasTA && $ta,        fn($qq)=>$qq->where('tahun_akademik','like',"%{$ta}%"))
                  ->when(strtolower($status) !== 'semua', function ($qq) use ($status) {
                      $map = [
                          'pending' => ['belum','belum lunas','menunggu verifikasi','pending','menunggu','unpaid'],
                          'lunas'   => ['lunas','paid','terverifikasi','lunas (otomatis)'],
                          'ditolak' => ['ditolak','reject','gagal','batal','invalid'],
                      ];
                      $allowed = $map[strtolower($status)] ?? $map['pending'];
                      $qq->whereIn(DB::raw('LOWER(status)'), $allowed);
                  })
                  ->when($search !== '', function ($qq) use ($search, $hasBulan, $hasKet, $hasTA) {
                      $qq->where(function ($x) use ($search, $hasBulan, $hasKet, $hasTA) {
                          if ($hasBulan) $x->orWhere('bulan', 'like', "%{$search}%");
                          if ($hasKet)   $x->orWhere('keterangan', 'like', "%{$search}%");
                          if ($hasTA)    $x->orWhere('tahun_akademik', 'like', "%{$search}%");
                      });
                  });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($x) use ($search) {
                    $x->where('nama','like',"%{$search}%")
                      ->orWhere('nim','like',"%{$search}%");
                });
            })
            ->orderByDesc('pending_count')
            ->orderBy('nama','asc');

        $students = $ms->paginate($perPage)->withQueryString();

        $students->load(['invoicesReguler' => function ($q) use ($sem, $ta, $status, $search, $hasBulan, $hasKet, $hasTA, $hasSemester, $hasJatuh) {
            $q->when($hasSemester && $sem, fn($qq)=>$qq->where('semester',$sem))
              ->when($hasTA && $ta,        fn($qq)=>$qq->where('tahun_akademik','like',"%{$ta}%"))
              ->when(strtolower($status) !== 'semua', function ($qq) use ($status) {
                  $map = [
                      'pending' => ['belum','belum lunas','menunggu verifikasi','pending','menunggu','unpaid'],
                      'lunas'   => ['lunas','paid','terverifikasi','lunas (otomatis)'],
                      'ditolak' => ['ditolak','reject','gagal','batal','invalid'],
                  ];
                  $allowed = $map[strtolower($status)] ?? $map['pending'];
                  $qq->whereIn(DB::raw('LOWER(status)'), $allowed);
              })
              ->when($search !== '', function ($qq) use ($search, $hasBulan, $hasKet, $hasTA) {
                  $qq->where(function ($x) use ($search, $hasBulan, $hasKet, $hasTA) {
                      if ($hasBulan) $x->orWhere('bulan', 'like', "%{$search}%");
                      if ($hasKet)   $x->orWhere('keterangan', 'like', "%{$search}%");
                      if ($hasTA)    $x->orWhere('tahun_akademik', 'like', "%{$search}%");
                  });
              })
              ->orderByRaw("CASE WHEN LOWER(status)='menunggu verifikasi' THEN 0 ELSE 1 END")
              ->when($hasJatuh, fn($qq)=>$qq->orderBy('jatuh_tempo','asc'),
                              fn($qq)=>$qq->orderBy('id','asc'));
        }]);

        $summary = [];
        foreach ($students as $m) {
            $rows = $m->invoicesReguler;
            $total = $rows->sum(function ($i) {
                $val = $i->nominal ?? $i->jumlah ?? 0;
                return (int) preg_replace('/\D+/', '', (string) $val);
            });
            $summary[$m->id] = [
                'count'   => $rows->count(),
                'total'   => $total,
                'pending' => $rows->contains(fn($i)=>mb_strtolower($i->status ?? '')==='menunggu verifikasi'),
            ];
        }

        return view('admin.invoices-reguler.index', [
            'students' => $students,
            'summary'  => $summary,
            'status'   => $status,
            'search'   => $search,
            'sem'      => $sem,
            'ta'       => $ta,
            'layout'   => 'group',
            'perPage'  => $perPage,
        ]);
    }

    public function show(InvoiceReguler $invoice)
    {
        if (method_exists($invoice, 'mahasiswaReguler')) $invoice->load('mahasiswaReguler');
        elseif (method_exists($invoice, 'mahasiswa'))     $invoice->load('mahasiswa');
        return view('admin.invoices-reguler.detail', compact('invoice'));
    }

    public function verify(InvoiceReguler $invoice)
    {
        $invoice->update([
            'status'      => 'Lunas',
            'verified_at' => now(),
            'verified_by' => auth('admin')->id(),
        ]);
        return back()->with('success', 'Invoice reguler diverifikasi (Lunas).');
    }

    public function reject(Request $request, InvoiceReguler $invoice)
    {
        $alasan = trim($request->input('alasan', ''));
        $payload = [
            'status'      => 'Ditolak',
            'verified_at' => null,
            'verified_by' => null,
        ];
        if (Schema::hasColumn($invoice->getTable(), 'alasan_tolak'))      $payload['alasan_tolak'] = $alasan ?: 'Tidak diset';
        if (Schema::hasColumn($invoice->getTable(), 'catatan_penolakan')) $payload['catatan_penolakan'] = $alasan ?: 'Tidak diset';
        $invoice->update($payload);

        return back()->with('success', 'Invoice reguler ditolak.');
    }

    public function reset(InvoiceReguler $invoice)
    {
        DB::transaction(function () use ($invoice) {
            $disk = Storage::disk('public');
            $candidates = [];

            if (!empty($invoice->bukti_pembayaran)) $candidates[] = ltrim($invoice->bukti_pembayaran, '/');
            if (!empty($invoice->bukti)) {
                $b = ltrim($invoice->bukti, '/');
                $candidates[] = strpos($b, '/') === false ? 'bukti_reguler/'.$b : $b;
                $candidates[] = strpos($b, '/') === false ? 'bukti/'.$b        : $b;
            }
            foreach ($candidates as $rel) {
                if ($rel && $disk->exists($rel)) $disk->delete($rel);
            }

            $table = $invoice->getTable();
            $set = ['status' => 'Belum'];
            foreach ([
                'bukti','bukti_pembayaran','uploaded_at','verified_by','verified_at',
                'rejected_by','rejected_at','alasan_tolak','catatan_penolakan','catatan_admin'
            ] as $col) {
                if (Schema::hasColumn($table, $col)) $set[$col] = null;
            }
            $invoice->update($set);
        });

        return back()->with('success', 'Invoice direset: bukti dihapus & status kembali "Belum".');
    }

    public function bukti(InvoiceReguler $invoice)
    {
        [$disk, $path] = $this->resolveBuktiPath($invoice);
        return response()->file($disk->path($path));
    }

    public function downloadBukti(InvoiceReguler $invoice)
    {
        [$disk, $path] = $this->resolveBuktiPath($invoice);
        return response()->download($disk->path($path), basename($path));
    }

    private function resolveBuktiPath(InvoiceReguler $invoice): array
    {
        $disk = Storage::disk('public');
        $candidates = [];

        if (!empty($invoice->bukti_pembayaran)) $candidates[] = ltrim($invoice->bukti_pembayaran, '/');

        if (!empty($invoice->bukti)) {
            $b = ltrim($invoice->bukti, '/');
            if (strpos($b, '/') === false) {
                $candidates[] = 'bukti_reguler/'.$b;
                $candidates[] = 'bukti/'.$b;
            } else {
                $candidates[] = $b;
            }
        }

        foreach ($candidates as $rel) {
            if ($rel && $disk->exists($rel)) return [$disk, $rel];
        }

        abort(404, 'Bukti tidak tersedia / file tidak ditemukan.');
    }
}
