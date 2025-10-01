<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MahasiswaReguler;
use App\Models\InvoiceReguler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage; // ⬅️ added
use Carbon\Carbon;

class MahasiswaRegulerController extends Controller
{
    /* ====================== Helpers ====================== */

    /**
     * Normalisasi nominal uang:
     * - "30.000.000", "30,000,000" -> 30000000
     * - "30 jt", "30juta", "30m"   -> 30000000
     */
    private function normalizeNominal($raw): int
    {
        if ($raw === null) return 0;
        $s = strtolower(trim((string) $raw));
        $s = preg_replace('/\s+/', '', $s);

        // dukungan singkatan (jt/juta/m)
        if (preg_match('/^\d+(\.\d+)?(jt|juta|m)$/', $s)) {
            $num = (float) preg_replace('/[^0-9.]/', '', $s);
            return (int) round($num * 1000000);
        }

        // default: buang non-digit
        $digits = preg_replace('/\D+/', '', (string)$raw);
        return $digits === '' ? 0 : (int) $digits;
    }

    /**
     * Upsert settings key-value ke tabel settings_reguler:
     * - pilih kolom 'key' atau 'name' yang tersedia
     * - otomatis create jika belum ada barisnya
     */
    private function upsertSettingsKV(string $table, string $keyName, int $value): void
    {
        if (!Schema::hasTable($table)) return;

        $hasKV = (Schema::hasColumn($table, 'value') && (Schema::hasColumn($table, 'key') || Schema::hasColumn($table, 'name')));
        if ($hasKV) {
            $keyCol = Schema::hasColumn($table, 'key') ? 'key' : 'name';
            DB::table($table)->updateOrInsert([$keyCol => $keyName], [
                'value'      => (string) $value,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
            return;
        }

        // Skema alternatif: ada kolom langsung 'total_tagihan'
        if (Schema::hasColumn($table, 'total_tagihan')) {
            DB::table($table)->updateOrInsert(['id' => 1], [
                'total_tagihan' => (string) $value,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]);
        }
    }

    /**
     * Ambil nama kolom FK invoice -> mahasiswa_reguler_id atau mahasiswa_id (legacy)
     */
    private function invoiceFkColumn(): ?string
    {
        $invTable = (new InvoiceReguler)->getTable();
        if (Schema::hasColumn($invTable, 'mahasiswa_reguler_id')) return 'mahasiswa_reguler_id';
        if (Schema::hasColumn($invTable, 'mahasiswa_id'))        return 'mahasiswa_id';
        return null;
    }

    /* ====================== Index + Filter + Ringkasan ====================== */

    public function index(Request $request)
    {
        Carbon::setLocale('id');

        // ===== pilihan per-page =====
        $perPageOptions = [120, 240, 480, 960, 1500, 3000];
        $perPage = (int) ($request->get('per_page', 120));
        if (!in_array($perPage, $perPageOptions, true)) $perPage = 120;

        $table         = (new MahasiswaReguler)->getTable();
        $base          = MahasiswaReguler::query();
        $search        = trim($request->get('q', $request->get('search', '')));
        $semester      = $request->get('semester');
        $tahunAkademik = $request->get('tahun_akademik');

        // Pencarian (nama/NIM/email jika ada)
        if ($search !== '') {
            $base->where(function ($x) use ($search, $table) {
                $or = false;
                if (Schema::hasColumn($table, 'nama')) {
                    $x->where('nama', 'like', "%{$search}%"); $or = true;
                }
                if (Schema::hasColumn($table, 'nim')) {
                    $or ? $x->orWhere('nim','like',"%{$search}%") : $x->where('nim','like',"%{$search}%"); $or = true;
                }
                if (Schema::hasColumn($table, 'email')) {
                    $or ? $x->orWhere('email','like',"%{$search}%") : $x->where('email','like',"%{$search}%");
                }
            });
        }

        // Filter semester_awal & tahun_akademik
        if ($semester && Schema::hasColumn($table, 'semester_awal')) {
            $base->whereRaw('LOWER(semester_awal) = ?', [mb_strtolower($semester)]);
        }
        if ($tahunAkademik && Schema::hasColumn($table, 'tahun_akademik')) {
            $base->where('tahun_akademik', $tahunAkademik);
        }

        $orderCol  = Schema::hasColumn($table, 'tanggal_upload') ? 'tanggal_upload' : 'created_at';

        // ========= Ringkasan metrik (tanpa pagination; sesuai filter) =========
        $filtered = clone $base;

        $jumlahMahasiswa = (clone $filtered)->count();

        $fk = $this->invoiceFkColumn();
        $totalTagihan = 0;
        $totalLunas   = 0;
        if ($fk) {
            $ids = (clone $filtered)->pluck('id');
            $totalTagihan = InvoiceReguler::whereIn($fk, $ids)->sum('jumlah');
            $totalLunas   = InvoiceReguler::whereIn($fk, $ids)->whereRaw('LOWER(status) = ?', ['lunas'])->sum('jumlah');
        }

        // Hitung "Sudah Lunas" (semua invoice lunas)
        $rowsCalc = (clone $filtered)->withCount([
            'invoicesReguler as invoices_total_count',
            'invoicesReguler as invoices_lunas_count' => fn($z) => $z->whereRaw('LOWER(status) = "lunas"'),
        ])->get();

        $sudahLunasCount = $rowsCalc->filter(fn($m) =>
            ($m->invoices_total_count > 0) && ($m->invoices_lunas_count == $m->invoices_total_count)
        )->count();

        // ========= Data utama untuk tabel (HALAMAN AKTIF SAJA) =========
        $paginator = (clone $filtered)
            ->orderByDesc($orderCol)
            ->orderByRaw('LOWER(TRIM(nama)) ASC')
            ->orderBy('nim', 'ASC')
            ->paginate($perPage)
            ->withQueryString();

        // Group current page items by tanggal (YYYY-mm-dd)
        $items = $paginator->getCollection(); // Illuminate\Support\Collection of models

        $groups = $items->groupBy(function ($m) use ($orderCol) {
            $raw = $m->{$orderCol};
            if (!$raw) return '0000-00-00';
            $t = $raw instanceof Carbon ? $raw : Carbon::parse($raw);
            return $t->format('Y-m-d');
        })->map(function ($group) {
            // fallback sort (harusnya sudah terurut dari query)
            return $group->sortBy(function ($m) {
                $name = isset($m->nama) ? trim(preg_replace('/\s+/', ' ', (string)$m->nama)) : '';
                $keyName = $name !== '' ? mb_strtolower($name) : 'zzzz';
                $nim  = (string)($m->nim ?? '');
                return $keyName.'|'.$nim;
            }, SORT_NATURAL);
        });

        // Dropdown tahun akademik
        $tahunAkademikList = MahasiswaReguler::query()
            ->whereNotNull('tahun_akademik')
            ->where('tahun_akademik','<>','')
            ->distinct()
            ->orderByDesc('tahun_akademik')
            ->pluck('tahun_akademik');

        // index penomoran baris (untuk blade)
        $rowStart = max(0, ($paginator->firstItem() ?? 1) - 1);

        return view('admin.mahasiswa-reguler.index', [
            // Ringkasan
            'jumlahMahasiswa'   => $jumlahMahasiswa,
            'totalTagihan'      => $totalTagihan,
            'totalLunas'        => $totalLunas,
            'sudahLunasCount'   => $sudahLunasCount,

            // Tabel & grouping (HALAMAN AKTIF)
            'groups'            => $groups,

            // Pagination state
            'paginator'         => $paginator,
            'perPage'           => $perPage,
            'perPageOptions'    => $perPageOptions,
            'rowStart'          => $rowStart,

            // Filter state
            'tahunAkademikList' => $tahunAkademikList,
            'search'            => $search,
            'semester'          => $semester,
            'tahunAkademik'     => $tahunAkademik,
        ]);
    }

    /* ====================== Detail ====================== */

    /**
     * Route canonical: GET /admin/mahasiswa-reguler/{mahasiswa}
     * (Paritas nama method dengan route "show")
     */
    public function show(MahasiswaReguler $mahasiswa)
    {
        $invTable = (new InvoiceReguler)->getTable();

        $mahasiswa->load(['invoicesReguler' => function ($q) use ($invTable) {
            if (Schema::hasColumn($invTable, 'tahun') && Schema::hasColumn($invTable, 'bulan')) {
                $q->orderBy('tahun')->orderBy('bulan');
            } elseif (Schema::hasColumn($invTable, 'bulan')) {
                $q->orderBy('bulan');
            } else {
                $q->latest();
            }
        }]);

        // Normalisasi tanggal upload jika ada
        $mTable = $mahasiswa->getTable();
        if (Schema::hasColumn($mTable, 'tanggal_upload')) {
            if ($mahasiswa->tanggal_upload && !($mahasiswa->tanggal_upload instanceof Carbon)) {
                $mahasiswa->tanggal_upload = Carbon::parse($mahasiswa->tanggal_upload);
            }
        }

        $total = $mahasiswa->invoicesReguler->count();
        $lunas = $mahasiswa->invoicesReguler->where(fn($x) => strtolower($x->status) === 'lunas')->count();

        return view('admin.mahasiswa-reguler.show', [
            'mahasiswa' => $mahasiswa,
            'total'     => $total,
            'lunas'     => $lunas,
        ]);
    }

    /**
     * Legacy alias (kalau ada Blade lama yang masih manggil showTagihan)
     */
    public function showTagihan($id)
    {
        $m = MahasiswaReguler::findOrFail($id);
        return $this->show($m);
    }

    /* ====================== Import CSV ====================== */

    public function import(Request $request)
    {
        // Terima 'csv_file' ATAU 'file'
        $fileKey = $request->hasFile('csv_file') ? 'csv_file' : 'file';

        $request->validate([
            $fileKey          => ['required','file','mimes:csv,txt'],
            'semester_awal'   => ['required','in:ganjil,genap'],
            'tahun_akademik'  => ['required','string'],
        ], [], [
            $fileKey          => 'File CSV',
            'semester_awal'   => 'Semester Awal',
            'tahun_akademik'  => 'Tahun Akademik',
        ]);

        // Baca CSV
        $rows = @array_map('str_getcsv', file($request->file($fileKey)->getRealPath()));
        if (!$rows || count($rows) < 2) {
            return back()->with('error', 'CSV kosong atau tidak memiliki header.');
        }

        $header = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows));
        $take = function(array $row, string $key, $default = null) use ($header) {
            $idx = array_search($key, $header);
            return $idx !== false ? ($row[$idx] ?? $default) : $default;
        };

        $imported = 0; $skipped = 0; $blank = 0; $dupe = 0;
        $now = now();
        $mTable = (new MahasiswaReguler)->getTable();

        DB::beginTransaction();
        try {
            foreach ($rows as $line) {
                if (!is_array($line) || (count($line) === 1 && trim((string)$line[0]) === '')) continue;
                if (count($line) < count($header)) $line = array_pad($line, count($header), null);

                $nama  = trim((string) $take($line,'nama',''));
                $nim   = trim((string) $take($line,'nim',''));
                $email = trim((string) $take($line,'email',''));

                if ($nama === '' || $nim === '') { $blank++; continue; }

                // Cegah duplikat NIM pada TA sama
                $exists = MahasiswaReguler::where('nim', $nim)
                    ->where('tahun_akademik', preg_replace('/\s+/', '', $request->tahun_akademik))
                    ->exists();
                if ($exists) { $dupe++; continue; }

                // Payload fleksibel (cek kolom dulu)
                $payload = [
                    'nama'           => $nama,
                    'nim'            => $nim,
                    'password'       => Hash::make('12345678'),
                    'status'         => 'Aktif',
                    'semester_awal'  => strtolower($request->semester_awal),
                    'tahun_akademik' => preg_replace('/\s+/', '', $request->tahun_akademik),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                if (Schema::hasColumn($mTable, 'email'))    $payload['email']    = $email ?: null;
                if (Schema::hasColumn($mTable, 'no_hp'))    $payload['no_hp']    = $take($line,'no_hp');
                if (Schema::hasColumn($mTable, 'alamat'))   $payload['alamat']   = $take($line,'alamat');
                if (Schema::hasColumn($mTable, 'bulan_mulai')) $payload['bulan_mulai'] = $take($line,'bulan_mulai');

                if (Schema::hasColumn($mTable, 'total_tagihan')) {
                    $payload['total_tagihan'] = $this->normalizeNominal($take($line,'total_tagihan',0));
                }

                MahasiswaReguler::create($payload);
                $imported++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal impor: '.$e->getMessage());
        }

        $msg = "Impor selesai. Berhasil: {$imported}";
        if ($dupe)  $msg .= ", duplikat: {$dupe}";
        if ($blank) $msg .= ", baris kosong: {$blank}";
        if ($skipped) $msg .= ", dilewati: {$skipped}";

        return redirect()
            ->route('admin.mahasiswa-reguler.index')
            ->with('success', $msg.'.');
    }

    /* ====================== Bulk delete ====================== */

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('warning', 'Tidak ada data yang dipilih.');
        }

        DB::transaction(function () use ($ids) {
            $invTable = (new InvoiceReguler)->getTable();

            // Hapus invoice reguler (handle 2 kemungkinan FK)
            if (Schema::hasColumn($invTable, 'mahasiswa_id')) {
                InvoiceReguler::whereIn('mahasiswa_id', $ids)->delete();
            }
            if (Schema::hasColumn($invTable, 'mahasiswa_reguler_id')) {
                InvoiceReguler::whereIn('mahasiswa_reguler_id', $ids)->delete();
            }

            MahasiswaReguler::whereIn('id', $ids)->delete();
        });

        return back()->with('success', count($ids).' data mahasiswa reguler berhasil dihapus.');
    }

    /* ====================== Resource opsional ====================== */

    public function create()
    {
        return view('admin.mahasiswa-reguler.create');
    }

    public function store(Request $request)
    {
        $table = (new MahasiswaReguler)->getTable();

        $rules = [
            'nama'           => ['required','string','max:255'],
            'nim'            => ['required','string','max:100'],
            'email'          => ['nullable','email','max:255'],
            'no_hp'          => ['nullable','string','max:50'],
            'alamat'         => ['nullable','string'],
            'status'         => ['nullable','string','max:50'],
            'angsuran'       => ['nullable','integer','min:0'],
            'total_tagihan'  => ['nullable'], // sanitasi manual
            'semester_awal'  => ['nullable','string','max:50'],
            'tahun_akademik' => ['nullable','string','max:50'],
            'bulan_mulai'    => ['nullable','string','max:20'],
            'password'       => ['required','string','min:6'],
        ];

        if (Schema::hasColumn($table, 'nim'))   $rules['nim'][]   = Rule::unique($table,'nim');
        if (Schema::hasColumn($table, 'email')) $rules['email'][] = Rule::unique($table,'email');

        $data = $request->validate($rules);

        if (array_key_exists('total_tagihan', $data)) {
            $data['total_tagihan'] = $this->normalizeNominal($data['total_tagihan']);
        }

        $data['password'] = Hash::make($data['password']);

        MahasiswaReguler::create($data);

        return redirect()->route('admin.mahasiswa-reguler.index')
            ->with('success', 'Mahasiswa reguler berhasil ditambahkan.');
    }

    public function edit(MahasiswaReguler $mahasiswa_reguler)
    {
        return view('admin.mahasiswa-reguler.edit', ['mahasiswa' => $mahasiswa_reguler]);
    }

    public function update(Request $request, MahasiswaReguler $mahasiswa_reguler)
    {
        $table = (new MahasiswaReguler)->getTable();

        $rules = [
            'nama'           => ['required','string','max:255'],
            'nim'            => ['required','string','max:100'],
            'email'          => ['nullable','email','max:255'],
            'no_hp'          => ['nullable','string','max:50'],
            'alamat'         => ['nullable','string'],
            'status'         => ['nullable','string','max:50'],
            'angsuran'       => ['nullable','integer','min:0'],
            'total_tagihan'  => ['nullable'], // sanitasi manual
            'semester_awal'  => ['nullable','string','max:50'],
            'tahun_akademik' => ['nullable','string','max:50'],
            'bulan_mulai'    => ['nullable','string','max:20'],
            'password'       => ['nullable','string','min:6'],
        ];

        if (Schema::hasColumn($table, 'nim')) {
            $rules['nim'][] = Rule::unique($table,'nim')->ignore($mahasiswa_reguler->id);
        }
        if (Schema::hasColumn($table, 'email')) {
            $rules['email'][] = Rule::unique($table,'email')->ignore($mahasiswa_reguler->id);
        }

        $data = $request->validate($rules);

        if (array_key_exists('total_tagihan', $data)) {
            $data['total_tagihan'] = $this->normalizeNominal($data['total_tagihan']);
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $mahasiswa_reguler->update($data);

        return redirect()->route('admin.mahasiswa-reguler.index')
            ->with('success', 'Mahasiswa reguler berhasil diperbarui.');
    }

    public function destroy(MahasiswaReguler $mahasiswa_reguler)
    {
        $mahasiswa_reguler->delete();

        return redirect()->route('admin.mahasiswa-reguler.index')
            ->with('success', 'Mahasiswa reguler berhasil dihapus.');
    }

    /* ====================== Reset Angsuran ====================== */

    public function resetAngsuran(MahasiswaReguler $mahasiswa)
    {
        DB::transaction(function () use ($mahasiswa) {
            // Null-kan pilihan skema
            $mahasiswa->update([
                'angsuran'    => null,
                'bulan_mulai' => null,
            ]);

            // Tentukan FK yang dipakai tabel invoices_reguler
            $fk = $this->invoiceFkColumn();
            if (!$fk) return;

            // Ambil semua invoice mahasiswa ini
            $invoices = InvoiceReguler::where($fk, $mahasiswa->id)->get();

            // Hapus file bukti jika ada (dukung dua nama kolom)
            foreach ($invoices as $inv) {
                if ($inv->bukti && Storage::disk('public')->exists($inv->bukti)) {
                    Storage::disk('public')->delete($inv->bukti);
                }
                if (!empty($inv->bukti_pembayaran) && Storage::disk('public')->exists($inv->bukti_pembayaran)) {
                    Storage::disk('public')->delete($inv->bukti_pembayaran);
                }
            }

            // Hapus SEMUA invoice reguler mahasiswa tsb (bukan hanya pending)
            InvoiceReguler::where($fk, $mahasiswa->id)->delete();
        });

        return back()->with('success', 'Angsuran direset total: semua invoice & bukti telah dihapus. Silakan set ulang skema.');
    }

    /* ====================== Mass Update Total Tagihan (REGULER) ====================== */

    /**
     * Mass update 'total_tagihan' untuk mahasiswa REGULER dari halaman settings:
     * - fields: scope (all/filtered), semester (ganjil/genap), tahun_akademik, total_tagihan
     * - tidak menyentuh invoice lama
     * - simpan default terakhir ke tabel settings_reguler key "total_tagihan_reguler"
     */
    public function updateTotalAll(Request $r)
    {
        $data = $r->validate([
            'total_tagihan'   => ['required'], // boleh "25.000.000" / "25 jt"
            'scope'           => ['nullable','in:all,filtered'],
            'semester'        => ['nullable','in:ganjil,genap'],
            'tahun_akademik'  => ['nullable','string'],
        ], [], [
            'total_tagihan'  => 'Total tagihan',
            'semester'       => 'Semester',
            'tahun_akademik' => 'Tahun akademik',
        ]);

        $nominal = $this->normalizeNominal($data['total_tagihan']);
        $scope   = $data['scope'] ?? 'all';

        $table = (new MahasiswaReguler)->getTable();
        $q     = MahasiswaReguler::query();

        if ($scope === 'filtered') {
            if (!empty($data['semester']) && Schema::hasColumn($table, 'semester_awal')) {
                $q->whereRaw('LOWER(semester_awal) = ?', [mb_strtolower($data['semester'])]);
            }
            if (!empty($data['tahun_akademik']) && Schema::hasColumn($table, 'tahun_akademik')) {
                $ta = preg_replace('/\s+/', '', $data['tahun_akademik']); // normalisasi spasi
                $q->whereRaw('REPLACE(tahun_akademik, " ", "") = ?', [$ta]);
            }
        }

        $count = (clone $q)->count();
        if ($count === 0) {
            return back()->with('warning', 'Tidak ada mahasiswa Reguler yang cocok dengan filter.');
        }

        DB::transaction(function () use ($q, $table, $nominal) {
            $payload = ['total_tagihan' => $nominal];
            if (Schema::hasColumn($table, 'updated_at')) {
                $payload['updated_at'] = now();
            }
            $q->update($payload);
        });

        // simpan default terakhir ke settings_reguler
        $this->upsertSettingsKV('settings_reguler', 'total_tagihan_reguler', $nominal);

        return back()->with('success', "Total tagihan Reguler diperbarui untuk {$count} mahasiswa.");
    }
}
