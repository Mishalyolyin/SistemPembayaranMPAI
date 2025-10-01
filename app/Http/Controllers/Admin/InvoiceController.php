<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Mahasiswa; // dipakai untuk GROUP layout
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $status  = $request->get('status', 'semua');
        $search  = trim($request->get('search', ''));
        $sem     = $request->get('semester');          // 'ganjil' / 'genap'
        $ta      = $request->get('tahun_akademik');    // contoh '2025/2026'
        $layout  = $request->get('layout', 'group');   // default: GROUP

        // page size: kelipatan 10, min 10, max 1000
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(10, min($perPage, 1000));
        $perPage = (int) (round($perPage / 10) * 10);

        // kolom opsional
        $hasBulan    = Schema::hasColumn('invoices', 'bulan');
        $hasKet      = Schema::hasColumn('invoices', 'keterangan');
        $hasTA       = Schema::hasColumn('invoices', 'tahun_akademik');
        $hasSemester = Schema::hasColumn('invoices', 'semester');
        $hasJatuh    = Schema::hasColumn('invoices', 'jatuh_tempo');

        /* ======================== FLAT (kompat lama) ======================== */
        if ($layout === 'flat') {
            $q = Invoice::query()->with('mahasiswa')->latest('id');

            if ($search !== '') {
                $q->where(function ($x) use ($search, $hasBulan, $hasKet, $hasTA) {
                    if ($hasBulan) $x->orWhere('bulan', 'like', "%{$search}%");
                    if ($hasKet)   $x->orWhere('keterangan', 'like', "%{$search}%");
                    if ($hasTA)    $x->orWhere('tahun_akademik', 'like', "%{$search}%");
                    $x->orWhereHas('mahasiswa', function ($m) use ($search) {
                        $m->where('nama', 'like', "%{$search}%")
                          ->orWhere('nim',  'like', "%{$search}%")
                          ->orWhere('tahun_akademik', 'like', "%{$search}%");
                    });
                });
            }

            if ($status !== 'semua') {
                $map = [
                    'pending' => ['belum','belum lunas','menunggu verifikasi','pending','menunggu','unpaid'],
                    'lunas'   => ['lunas','paid','terverifikasi','lunas (otomatis)'],
                    'ditolak' => ['ditolak','reject','gagal','batal','invalid'],
                ];
                $allowed = $map[$status] ?? $map['pending'];
                $q->whereIn(DB::raw('LOWER(status)'), $allowed);
            }

            if ($sem) {
                $q->where(function ($w) use ($sem, $hasSemester) {
                    if ($hasSemester) $w->orWhere('semester', $sem);
                    $w->orWhereHas('mahasiswa', fn ($m) => $m->where('semester_awal', $sem));
                });
            }

            if ($ta) {
                $q->where(function ($w) use ($ta, $hasTA) {
                    if ($hasTA) $w->orWhere('tahun_akademik', 'like', "%{$ta}%");
                    $w->orWhereHas('mahasiswa', fn ($m) => $m->where('tahun_akademik', 'like', "%{$ta}%"));
                });
            }

            // pending di atas (opsional)
            $q->orderByRaw("CASE WHEN status='Menunggu Verifikasi' THEN 0 ELSE 1 END");

            // ⬅ gunakan $perPage
            $invoices = $q->paginate($perPage)->withQueryString();

            // isi tampilan semester/TA dari profil jika kosong (hanya untuk view)
            $invoices->getCollection()->transform(function ($inv) {
                if (empty($inv->semester) && $inv->relationLoaded('mahasiswa')) {
                    $inv->semester = $inv->mahasiswa->semester_awal ?? $inv->semester;
                }
                if (empty($inv->tahun_akademik) && $inv->relationLoaded('mahasiswa')) {
                    $inv->tahun_akademik = $inv->mahasiswa->tahun_akademik ?? $inv->tahun_akademik;
                }
                return $inv;
            });

            // NOTE: render ke view YANG SAMA
            return view('admin.invoices.index', compact('invoices', 'status', 'search', 'sem', 'ta', 'layout') + [
                'per_page' => $perPage,
            ]);
        }

        /* ======================== GROUP (1 baris / mahasiswa) ======================== */
        // Safety: kalau model Mahasiswa tidak punya relasi 'invoices', fallback ke flat
        if (!method_exists(new Mahasiswa, 'invoices')) {
            return redirect()->to(request()->fullUrlWithQuery(['layout'=>'flat']));
        }

        // Query mahasiswa yang memiliki invoices sesuai filter
        $ms = Mahasiswa::query()
            // hitung jumlah invoice pending (untuk sorting)
            ->withCount(['invoices as pending_count' => function ($q) use ($sem, $ta, $search, $hasBulan, $hasKet, $hasTA, $hasSemester) {
                $q->when($hasSemester && $sem, fn($qq)=>$qq->where('semester', $sem))
                  ->when($hasTA && $ta,        fn($qq)=>$qq->where('tahun_akademik', 'like', "%{$ta}%"))
                  ->where('status', 'Menunggu Verifikasi')
                  ->when($search !== '', function ($qq) use ($search, $hasBulan, $hasKet, $hasTA) {
                      $qq->where(function ($x) use ($search, $hasBulan, $hasKet, $hasTA) {
                          if ($hasBulan) $x->orWhere('bulan', 'like', "%{$search}%");
                          if ($hasKet)   $x->orWhere('keterangan', 'like', "%{$search}%");
                          if ($hasTA)    $x->orWhere('tahun_akademik', 'like', "%{$search}%");
                      });
                  });
            }])
            // hanya mahasiswa yang punya invoice match filter
            ->whereHas('invoices', function ($q) use ($sem, $ta, $status, $search, $hasBulan, $hasKet, $hasTA, $hasSemester) {
                $q->when($hasSemester && $sem, fn($qq)=>$qq->where('semester',$sem))
                  ->when($hasTA && $ta,        fn($qq)=>$qq->where('tahun_akademik','like',"%{$ta}%"))
                  ->when(strtolower($status) !== 'semua', function ($qq) use ($status) {
                      $map = [
                          'pending' => ['belum','belum lunas','menunggu verifikasi','pending','menunggu','unpaid'],
                          'lunas'   => ['lunas','paid','terverifikasi','lunas (otomatis)'],
                          'ditolak' => ['ditolak','reject','gagal','batal','invalid'],
                      ];
                      $allowed = $map[$status] ?? $map['pending'];
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
            // search di level mahasiswa
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($x) use ($search) {
                    $x->where('nama','like',"%{$search}%")
                      ->orWhere('nim','like',"%{$search}%");
                });
            })
            // SORT: pending_count desc, nama asc
            ->orderByDesc('pending_count')
            ->orderBy('nama','asc');

        // paginate per mahasiswa  ⬅ gunakan $perPage
        $students = $ms->paginate($perPage)->withQueryString();

        // eager-load invoices milik tiap mahasiswa sesuai filter & urut kronologis
        $students->load(['invoices' => function ($q) use ($sem, $ta, $status, $search, $hasBulan, $hasKet, $hasTA, $hasSemester, $hasJatuh) {
            $q->when($hasSemester && $sem, fn($qq)=>$qq->where('semester',$sem))
              ->when($hasTA && $ta,        fn($qq)=>$qq->where('tahun_akademik','like',"%{$ta}%"))
              ->when(strtolower($status) !== 'semua', function ($qq) use ($status) {
                  $map = [
                      'pending' => ['belum','belum lunas','menunggu verifikasi','pending','menunggu','unpaid'],
                      'lunas'   => ['lunas','paid','terverifikasi','lunas (otomatis)'],
                      'ditolak' => ['ditolak','reject','gagal','batal','invalid'],
                  ];
                  $allowed = $map[$status] ?? $map['pending'];
                  $qq->whereIn(DB::raw('LOWER(status)'), $allowed);
              })
              ->when($search !== '', function ($qq) use ($search, $hasBulan, $hasKet, $hasTA) {
                  $qq->where(function ($x) use ($search, $hasBulan, $hasKet, $hasTA) {
                      if ($hasBulan) $x->orWhere('bulan', 'like', "%{$search}%");
                      if ($hasKet)   $x->orWhere('keterangan', 'like', "%{$search}%");
                      if ($hasTA)    $x->orWhere('tahun_akademik', 'like', "%{$search}%");
                  });
              })
              ->when($hasJatuh,
                     fn($qq)=>$qq->orderBy('jatuh_tempo','asc'),
                     fn($qq)=>$qq->orderBy('id','asc'));
        }]);

        // ringkasan per mahasiswa
        $summary = [];
        foreach ($students as $m) {
            $rows = $m->invoices; // relasi ke invoices
            $total = $rows->sum(function ($i) {
                $val = $i->nominal ?? $i->jumlah ?? 0;
                return (int) preg_replace('/\D+/', '', (string) $val);
            });
            $summary[$m->id] = [
                'count'   => $rows->count(),
                'total'   => $total,
                'pending' => $rows->contains(fn($i)=>mb_strtolower($i->status)==='menunggu verifikasi'),
            ];
        }

        // render ke view hybrid yang sama
        return view('admin.invoices.index', [
            'students' => $students,
            'summary'  => $summary,
            'status'   => $status,
            'search'   => $search,
            'sem'      => $sem,
            'ta'       => $ta,
            'layout'   => 'group',
            'per_page' => $perPage,
        ]);
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('mahasiswa');

        // isi tampilan semester/TA jika kosong
        if (empty($invoice->semester)) {
            $invoice->semester = $invoice->mahasiswa->semester_awal ?? $invoice->semester;
        }
        if (empty($invoice->tahun_akademik)) {
            $invoice->tahun_akademik = $invoice->mahasiswa->tahun_akademik ?? $invoice->tahun_akademik;
        }

        return view('admin.invoices.detail', compact('invoice'));
    }

    public function verify(Invoice $invoice)
    {
        $invoice->update([
            'status'      => 'Lunas',
            'verified_at' => now(),
            'verified_by' => auth('admin')->id(),
        ]);
        return back()->with('success', 'Invoice diverifikasi (Lunas).');
    }

    public function reject(Request $request, Invoice $invoice)
    {
        $alasan = trim($request->input('alasan', ''));
        $invoice->update([
            'status'       => 'Ditolak',
            'alasan_tolak' => $alasan ?: 'Tidak diset',
            'verified_at'  => null,
            'verified_by'  => null,
        ]);
        return back()->with('success', 'Invoice ditolak.');
    }

    public function reset(Invoice $invoice)
    {
        $invoice->update([
            'status'       => 'Menunggu Verifikasi',
            'alasan_tolak' => null,
            'verified_at'  => null,
            'verified_by'  => null,
        ]);
        return back()->with('success', 'Status invoice direset.');
    }

    public function bukti(Invoice $invoice)
    {
        $path = $invoice->bukti;
        if (!$path) abort(404, 'Bukti tidak tersedia');

        // dukung dua pola penyimpanan: "bukti/xxx.ext" atau "xxx.ext"
        if (!str_contains($path, '/')) $path = 'bukti/' . $path;

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) abort(404, 'File bukti tidak ditemukan');

        return response()->file($disk->path($path));
    }
}
