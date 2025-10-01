<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MahasiswaController extends Controller
{
    /* ====================== Helpers ====================== */

    /** Bersihkan input uang: "30.000.000", "30 jt", "30,000,000" -> 30000000 */
    private function normalizeNominal($value): int
    {
        if ($value === null) return 0;
        $s = (string) $value;
        $digits = preg_replace('/\D+/', '', $s);
        return $digits === '' ? 0 : (int) $digits;
    }

    /**
     * Simpan default total_tagihan ke tabel settings (2 skema didukung):
     * - Skema 1: kolom langsung "total_tagihan" (single row).
     * - Skema 2: key-value ("key"/"name" + "value").
     */
    private function upsertSettingsTotal(string $table, int $value): void
    {
        if (!Schema::hasTable($table)) return;

        if (Schema::hasColumn($table, 'total_tagihan')) {
            DB::table($table)->updateOrInsert(['id' => 1], [
                'total_tagihan' => $value,
                'updated_at'    => now(),
            ]);
            return;
        }

        $hasKey   = Schema::hasColumn($table, 'key') || Schema::hasColumn($table, 'name');
        $hasValue = Schema::hasColumn($table, 'value');
        if ($hasKey && $hasValue) {
            $keyCol = Schema::hasColumn($table, 'key') ? 'key' : 'name';
            DB::table($table)->updateOrInsert([$keyCol => 'total_tagihan'], [
                'value'      => $value,
                'updated_at' => now(),
            ]);
        }
    }

    /* ====================== Index/List ====================== */

    public function index(Request $request)
    {
        $table = (new Mahasiswa)->getTable();
        $q     = Mahasiswa::query();

        // Pencarian (q / search)
        $search = trim($request->get('q', $request->get('search', '')));
        if ($search !== '') {
            $q->where(function ($s) use ($search, $table) {
                $added = false;
                if (Schema::hasColumn($table, 'nama')) {
                    $s->where('nama', 'like', "%{$search}%");
                    $added = true;
                }
                if (Schema::hasColumn($table, 'nim')) {
                    $added ? $s->orWhere('nim', 'like', "%{$search}%")
                           : $s->where('nim', 'like', "%{$search}%");
                    $added = true;
                }
                if (Schema::hasColumn($table, 'email')) {
                    $added ? $s->orWhere('email', 'like', "%{$search}%")
                           : $s->where('email', 'like', "%{$search}%");
                }
            });
        }

        // Filter semester awal
        if (($sem = $request->get('semester')) && Schema::hasColumn($table, 'semester_awal')) {
            $q->whereRaw('LOWER(semester_awal) = ?', [mb_strtolower($sem)]);
        }

        // Filter tahun akademik
        if (($ta = $request->get('tahun_akademik')) && Schema::hasColumn($table, 'tahun_akademik')) {
            $q->where('tahun_akademik', $ta);
        }

        // Filter jenis mahasiswa (RPL / Reguler)
        if (($jenis = $request->get('jenis_mahasiswa')) && Schema::hasColumn($table, 'jenis_mahasiswa')) {
            $q->where('jenis_mahasiswa', strtoupper($jenis));
        }

        // ===== ORDERING: tanggal impor DESC, lalu alfabetis di dalam tanggal =====
        $useUpload = Schema::hasColumn($table, 'tanggal_upload');
        $orderCol  = $useUpload ? 'tanggal_upload' : 'created_at';
        if (Schema::hasColumn($table, 'nama')) {
            $q->orderByDesc($orderCol)
              ->orderByRaw("LOWER(TRIM(nama)) ASC")
              ->orderBy('nim', 'ASC');
        } else {
            $q->orderByDesc($orderCol)->orderBy('nim', 'ASC');
        }

        // ===== Pagination: kelipatan 10 (10..1000) =====
        $perPageReq  = (int) $request->get('per_page', 120);
        $perPage     = max(10, min($perPageReq, 1000));          // clamp ke 10..1000
        $perPage     = (int) (round($perPage / 10) * 10);        // bulatkan kelipatan 10
        $allowedPerPage = range(10, 1000, 10);                   // opsi buat dropdown

        $paginator  = $q->paginate($perPage)->appends($request->query());
        $collection = $paginator->getCollection();

        // Group per tanggal pada items di halaman ini
        Carbon::setLocale('id');
        $grouped = $collection->groupBy(function ($m) use ($orderCol) {
            $raw = $m->{$orderCol};
            if (!$raw) return '0000-00-00';
            $t = $raw instanceof Carbon ? $raw : Carbon::parse($raw);
            return $t->format('Y-m-d');
        })->map(function ($group) {
            return $group->sortBy(function ($m) {
                $name = isset($m->nama) ? trim(preg_replace('/\s+/', ' ', (string)$m->nama)) : '';
                $keyName = $name !== '' ? mb_strtolower($name) : 'zzzz'; // nama kosong → taruh bawah
                $nim  = (string)($m->nim ?? '');
                return $keyName.'|'.$nim;
            }, SORT_NATURAL);
        });

        // Dropdown Tahun Akademik
        $tahunAkademikList = collect();
        if (Schema::hasColumn($table, 'tahun_akademik')) {
            $tahunAkademikList = Mahasiswa::query()
                ->whereNotNull('tahun_akademik')
                ->where('tahun_akademik', '<>', '')
                ->distinct()
                ->orderByDesc('tahun_akademik')
                ->pluck('tahun_akademik');
        }

        return view('admin.mahasiswa.index', [
            // baru
            'groups'            => $grouped,
            'paginator'         => $paginator,
            'perPage'           => $perPage,
            'perPageOptions'    => $allowedPerPage,
            // kompatibilitas lama (jika blade masih pakai $mahasiswas)
            'mahasiswas'        => $grouped,
            // filter dropdown
            'tahunAkademikList' => $tahunAkademikList,
            'search'            => $search,
            'semester'          => $sem ?? null,
            'tahunAkademik'     => $ta ?? null,
            'jenisMahasiswa'    => isset($jenis) ? strtoupper($jenis) : null,
        ]);
    }

    /* ====================== CRUD per mahasiswa ====================== */

    public function create()
    {
        return view('admin.mahasiswa.create');
    }

    public function store(Request $request)
    {
        $table = (new Mahasiswa)->getTable();

        $rules = [
            'nama'            => ['required','string','max:255'],
            'nim'             => ['required','string','max:100'],
            'email'           => ['nullable','email','max:255'],
            'no_hp'           => ['nullable','string','max:50'],
            'alamat'          => ['nullable','string'],
            'status'          => ['nullable','string','max:50'],
            'angsuran'        => ['nullable','integer','min:0'],
            'total_tagihan'   => ['nullable'],
            'semester_awal'   => ['nullable','string','max:50'],
            'tahun_akademik'  => ['nullable','string','max:50'],
            'bulan_mulai'     => ['nullable','string','max:20'],
            'password'        => ['required','string','min:6'],
            'tanggal_upload'  => ['nullable','date'],
            'jenis_mahasiswa' => ['nullable','in:RPL,Reguler'],
        ];

        if (Schema::hasColumn($table, 'nim'))   $rules['nim'][]   = Rule::unique($table, 'nim');
        if (Schema::hasColumn($table, 'email')) $rules['email'][] = Rule::unique($table, 'email');

        $data = $request->validate($rules);

        if (array_key_exists('total_tagihan', $data)) {
            $data['total_tagihan'] = $this->normalizeNominal($data['total_tagihan']);
        }

        $data['password'] = Hash::make($data['password']);

        if (Schema::hasColumn($table, 'tanggal_upload') && empty($data['tanggal_upload'])) {
            $data['tanggal_upload'] = now();
        }

        if (Schema::hasColumn($table, 'jenis_mahasiswa')) {
            $jm = $data['jenis_mahasiswa'] ?? 'RPL';
            $data['jenis_mahasiswa'] = strtoupper($jm);
        }

        Mahasiswa::create($data);

        return redirect()->route('admin.mahasiswa.index')
            ->with('success', 'Mahasiswa berhasil ditambahkan.');
    }

    public function show(Mahasiswa $mahasiswa)
    {
        return redirect()->route('admin.mahasiswa.tagihan', ['mahasiswa' => $mahasiswa->id]);
    }

    public function edit(Mahasiswa $mahasiswa)
    {
        return view('admin.mahasiswa.edit', compact('mahasiswa'));
    }

    public function update(Request $request, Mahasiswa $mahasiswa)
    {
        $table = (new Mahasiswa)->getTable();

        $rules = [
            'nama'            => ['required','string','max:255'],
            'nim'             => ['required','string','max:100'],
            'email'           => ['nullable','email','max:255'],
            'no_hp'           => ['nullable','string','max:50'],
            'alamat'          => ['nullable','string'],
            'status'          => ['nullable','string','max:50'],
            'angsuran'        => ['nullable','integer','min:0'],
            'total_tagihan'   => ['nullable'],
            'semester_awal'   => ['nullable','string','max:50'],
            'tahun_akademik'  => ['nullable','string','max:50'],
            'bulan_mulai'     => ['nullable','string','max:20'],
            'password'        => ['nullable','string','min:6'],
            'tanggal_upload'  => ['nullable','date'],
            'jenis_mahasiswa' => ['nullable','in:RPL,Reguler'],
        ];

        if (Schema::hasColumn($table, 'nim')) {
            $rules['nim'][] = Rule::unique($table, 'nim')->ignore($mahasiswa->id);
        }
        if (Schema::hasColumn($table, 'email')) {
            $rules['email'][] = Rule::unique($table, 'email')->ignore($mahasiswa->id);
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

        if (Schema::hasColumn($table, 'jenis_mahasiswa') && isset($data['jenis_mahasiswa'])) {
            $data['jenis_mahasiswa'] = strtoupper($data['jenis_mahasiswa']);
        }

        $mahasiswa->update($data);

        return redirect()->route('admin.mahasiswa.index')
            ->with('success', 'Mahasiswa berhasil diperbarui.');
    }

    public function destroy(Mahasiswa $mahasiswa)
    {
        $mahasiswa->delete();
        return redirect()->route('admin.mahasiswa.index')
            ->with('success', 'Mahasiswa berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $raw = $request->input('ids', $request->input('select', []));
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids)) {
            return back()->with('warning', 'Tidak ada mahasiswa yang dipilih.');
        }

        DB::transaction(function () use ($ids) {
            if (Schema::hasColumn((new Invoice)->getTable(), 'mahasiswa_id')) {
                Invoice::whereIn('mahasiswa_id', $ids)->delete();
            }
            Mahasiswa::whereIn('id', $ids)->delete();
        });

        return back()->with('success', 'Mahasiswa terpilih berhasil dihapus.');
    }

    /* ====================== Import (Streaming, anti time-out) ====================== */

    public function import(Request $request)
    {
        $request->validate([
            'file'             => ['required','file','mimes:csv,txt'],
            'semester_awal'    => ['nullable','in:ganjil,genap'],
            'tahun_akademik'   => ['nullable','string'],
            'jenis_mahasiswa'  => ['nullable','in:RPL,Reguler'],
        ]);

        // Guard rails
        @ignore_user_abort(true);
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        DB::disableQueryLog();

        $table   = (new Mahasiswa)->getTable();
        $columns = Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
        $has     = fn(string $col) => in_array($col, $columns, true);

        $fallbackSem   = $request->input('semester_awal');
        $fallbackTA    = $request->input('tahun_akademik');
        $fallbackJenis = strtoupper($request->input('jenis_mahasiswa', 'RPL')); // default RPL

        $path = $request->file('file')->getRealPath();
        $csv  = new \SplFileObject($path, 'r');
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);
        $csv->setCsvControl(',');

        // Ambil header sekali
        $csv->rewind();
        $header = $csv->fgetcsv() ?: [];
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        }
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

        if (!$header || count($header) === 0) {
            return back()->with('warning', 'CSV kosong atau tidak memiliki header.');
        }

        $take = function(array $row, string $key, $default = null) use ($header) {
            $idx = array_search($key, $header, true);
            return $idx !== false ? ($row[$idx] ?? $default) : $default;
        };

        $created   = 0;
        $updated   = 0;
        $skipped   = 0;
        $processed = 0;
        $batchSize = 500;
        $failNotes = [];

        DB::beginTransaction();
        try {
            while (!$csv->eof()) {
                $row = $csv->fgetcsv();
                if ($row === false || $row === null) continue;

                if (count(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== '')) === 0) continue;

                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), null);
                }

                // Skip header duplikat
                $lowerRow  = array_map(fn($v) => strtolower(trim((string)$v)), $row);
                if ($lowerRow === $header) { $skipped++; continue; }
                $maybeNim  = strtolower((string) ($take($row, 'nim')  ?? ''));
                $maybeNama = strtolower((string) ($take($row, 'nama') ?? ''));
                if ($maybeNim === 'nim' || $maybeNama === 'nama') { $skipped++; continue; }

                try {
                    $nim   = trim((string) $take($row, 'nim', ''));
                    $nama  = trim((string) $take($row, 'nama', ''));
                    $email = trim((string) $take($row, 'email', ''));
                    if ($nim === '' && $email === '' && $nama === '') {
                        $skipped++;
                        continue;
                    }

                    $payload = [];
                    if ($has('nama'))  $payload['nama']  = $nama ?: null;
                    if ($has('nim'))   $payload['nim']   = $nim ?: null;
                    if ($has('email')) $payload['email'] = $email ?: null;

                    if ($pwd = $take($row, 'password')) {
                        if ($has('password')) $payload['password'] = Hash::make($pwd);
                    }

                    foreach (['no_hp','alamat','status','angsuran','total_tagihan','bulan_mulai'] as $opt) {
                        $val = $take($row, $opt);
                        if ($val !== null && $val !== '' && $has($opt)) {
                            $payload[$opt] = $opt === 'total_tagihan'
                                ? $this->normalizeNominal($val)
                                : $val;
                        }
                    }

                    // SEMESTER_AWAL
                    $sem = strtolower((string) $take($row, 'semester_awal', ''));
                    if (!$sem && $fallbackSem) $sem = strtolower($fallbackSem);
                    if (in_array($sem, ['ganjil','genap'], true) && $has('semester_awal')) {
                        $payload['semester_awal'] = $sem;
                    }

                    // TAHUN_AKADEMIK
                    $ta = (string) $take($row, 'tahun_akademik', '');
                    if (!$ta && $fallbackTA) $ta = $fallbackTA;
                    if ($ta !== '' && $has('tahun_akademik')) {
                        $payload['tahun_akademik'] = $ta;
                    }

                    // JENIS_MAHASISWA
                    $csvJenis = strtoupper((string) $take($row, 'jenis_mahasiswa', ''));
                    $jenis    = $csvJenis ?: $fallbackJenis;
                    if ($has('jenis_mahasiswa')) {
                        $payload['jenis_mahasiswa'] = in_array($jenis, ['RPL','REGULER'])
                            ? ($jenis === 'REGULER' ? 'Reguler' : 'RPL')
                            : 'RPL';
                    }

                    // TANGGAL_UPLOAD
                    if ($has('tanggal_upload')) {
                        if ($tgl = $take($row, 'tanggal_upload')) {
                            try { $payload['tanggal_upload'] = Carbon::parse($tgl); }
                            catch (\Throwable $e) { $payload['tanggal_upload'] = now(); }
                        } else {
                            $payload['tanggal_upload'] = now();
                        }
                    }

                    // Upsert by NIM / email
                    $existing = null;
                    if ($nim !== '' && $has('nim'))   $existing = Mahasiswa::where('nim', $nim)->first();
                    if (!$existing && $email !== '' && $has('email')) $existing = Mahasiswa::where('email', $email)->first();

                    if ($existing) {
                        $existing->update($payload); $updated++;
                    } else {
                        if ($has('password') && empty($payload['password'])) {
                            $payload['password'] = Hash::make('password123');
                        }
                        Mahasiswa::create($payload); $created++;
                    }

                } catch (\Throwable $rowErr) {
                    $skipped++;
                    if (count($failNotes) < 10) {
                        $failNotes[] = "Baris ~#{$processed}: ".$rowErr->getMessage();
                    }
                    Log::warning('Import mahasiswa RPL: skip row -> '.$rowErr->getMessage());
                }

                $processed++;
                if ($processed % $batchSize === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Import mahasiswa RPL gagal: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Gagal impor: '.$e->getMessage());
        }

        // 🔔 FLASH NOTIFIKASI
        $redirect = back()->with('success', "Import selesai. Tambah: {$created}, Ubah: {$updated}.");
        if ($skipped > 0) {
            $msg = "Sebagian baris dilewati: {$skipped}.";
            if (!empty($failNotes)) {
                $msg .= " Contoh error: ".implode(' | ', $failNotes);
            }
            $redirect = $redirect->with('warning', $msg);
        }
        return $redirect;
    }

    /* ====================== Tagihan per Mahasiswa ====================== */

    public function showTagihan($id)
    {
        $mahasiswa = Mahasiswa::with(['invoices' => function ($q) {
            $invTable = (new Invoice)->getTable();
            if (Schema::hasTable($invTable) && Schema::hasColumn($invTable, 'bulan')) {
                $q->orderBy('bulan');
            } else {
                $q->latest();
            }
        }])->findOrFail($id);

        if (Schema::hasColumn($mahasiswa->getTable(), 'tanggal_upload')) {
            if ($mahasiswa->tanggal_upload && !($mahasiswa->tanggal_upload instanceof Carbon)) {
                $mahasiswa->tanggal_upload = Carbon::parse($mahasiswa->tanggal_upload);
            }
        }

        return view('admin.mahasiswa.tagihan', compact('mahasiswa'));
    }

    public function resetAngsuran(Mahasiswa $mahasiswa)
    {
        DB::transaction(function () use ($mahasiswa) {
            $mahasiswa->update(['angsuran' => null, 'bulan_mulai' => null]);

            Invoice::where('mahasiswa_id', $mahasiswa->id)
                ->whereIn(DB::raw('LOWER(status)'), [
                    'belum','belum lunas','pending','menunggu','menunggu verifikasi'
                ])->delete();
        });

        return back()->with('success', 'Pilihan angsuran mahasiswa direset dan invoice pending dihapus.');
    }

    /* ====================== Mass Update Total Tagihan ====================== */

    public function updateTotalAll(Request $request)
    {
        $data = $request->validate([
            'total_tagihan'   => ['required'],
            'semester_awal'   => ['nullable','in:ganjil,genap'],
            'tahun_akademik'  => ['nullable','string','max:20'],
            'status'          => ['nullable','string','max:50'],
            'jenis_mahasiswa' => ['nullable','in:RPL,Reguler'],
        ]);

        $nominal = $this->normalizeNominal($data['total_tagihan']);

        $table = (new Mahasiswa)->getTable();
        $q     = Mahasiswa::query();

        if (!empty($data['semester_awal']) && Schema::hasColumn($table, 'semester_awal')) {
            $q->whereRaw('LOWER(semester_awal) = ?', [mb_strtolower($data['semester_awal'])]);
        }
        if (!empty($data['tahun_akademik']) && Schema::hasColumn($table, 'tahun_akademik')) {
            $q->where('tahun_akademik', $data['tahun_akademik']);
        }
        if (!empty($data['status']) && Schema::hasColumn($table, 'status')) {
            $q->whereRaw('LOWER(status) = ?', [mb_strtolower($data['status'])]);
        }
        if (!empty($data['jenis_mahasiswa']) && Schema::hasColumn($table, 'jenis_mahasiswa')) {
            $q->where('jenis_mahasiswa', strtoupper($data['jenis_mahasiswa']));
        }

        $payload = ['total_tagihan' => $nominal];
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $updated = $q->update($payload);

        $this->upsertSettingsTotal('settings', $nominal);

        return back()->with('success', "Total tagihan RPL diupdate untuk {$updated} mahasiswa dan diset sebagai default dashboard.");
    }

    /**
     * Update 'total_tagihan' per-ID (edit cepat).
     */
    public function updateTotalTagihan(Request $request, $id)
    {
        $request->validate(['total_tagihan' => ['required']]);

        $nominal = $this->normalizeNominal($request->input('total_tagihan'));

        $m = Mahasiswa::findOrFail($id);
        $m->update(['total_tagihan' => $nominal]);

        return back()->with('success', "Total tagihan {$m->nama} diperbarui.");
    }
}
