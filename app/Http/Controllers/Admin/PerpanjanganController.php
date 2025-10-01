<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\MahasiswaReguler;
use App\Models\Invoice;
use App\Models\InvoiceReguler;
use App\Support\RplBilling; // pastikan file ini ada (kita tambah methodnya di langkah 2)
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class PerpanjanganController extends Controller
{
    // ==========================
    // ===== REGULER (bulanan)
    // ==========================

    /**
     * Form tambah bulan untuk Reguler
     */
    public function regulerForm(MahasiswaReguler $m)
    {
        // ambil info terakhir (prioritas angsuran_ke)
        $last = InvoiceReguler::where('mahasiswa_reguler_id', $m->id)
            ->orderByDesc('angsuran_ke')
            ->orderByDesc('created_at')
            ->first();

        return view('admin.perpanjangan.reguler', [
            'mahasiswa' => $m,
            'last'      => $last,
        ]);
    }

    /**
     * Proses append bulan Reguler
     */
    public function regulerAppend(Request $request, MahasiswaReguler $m)
    {
        $data = $request->validate([
            'jumlah_bulan'      => 'required|integer|min:1|max:24',
            'nominal_per_bulan' => 'nullable|integer|min:0',
            'due_day'           => 'nullable|integer|min:1|max:28', // default 5
        ]);

        $n      = (int) $data['jumlah_bulan'];
        $amount = (int) ($data['nominal_per_bulan'] ?? 0);
        $dueDay = (int) ($data['due_day'] ?? 5);

        // Mulai dari bulan SETELAH invoice terakhir
        $last = InvoiceReguler::where('mahasiswa_reguler_id', $m->id)
            ->orderByDesc('angsuran_ke')
            ->orderByDesc('created_at')
            ->first();

        [$startY, $startM] = $this->nextMonthFromLabel($last?->bulan);

        $hasDueDate    = Schema::hasColumn('invoices_reguler', 'jatuh_tempo');
        $hasAngsuranKe = Schema::hasColumn('invoices_reguler', 'angsuran_ke');
        $startKe       = (int) InvoiceReguler::where('mahasiswa_reguler_id', $m->id)->max('angsuran_ke');
        if ($startKe <= 0) {
            $startKe = (int) InvoiceReguler::where('mahasiswa_reguler_id', $m->id)->count();
        }

        $inserted = 0;

        for ($i = 0; $i < $n; $i++) {
            $dt = Carbon::create($startY, $startM, 1)->addMonths($i);
            $label = $this->bulanNama($dt->month).' '.$dt->year;

            // cegah duplikat label
            $exists = InvoiceReguler::where('mahasiswa_reguler_id', $m->id)
                ->where('bulan', $label)
                ->exists();
            if ($exists) {
                continue;
            }

            $row = [
                'mahasiswa_reguler_id' => $m->id,
                'bulan'                => $label,
                'jumlah'               => $amount,
                'status'               => 'Belum',
            ];
            if ($hasAngsuranKe) {
                $row['angsuran_ke'] = $startKe + $i + 1;
            }
            if ($hasDueDate) {
                $row['jatuh_tempo'] = Carbon::create($dt->year, $dt->month, $dueDay)->format('Y-m-d');
            }

            InvoiceReguler::create($row);
            $inserted++;
        }

        return back()->with('success', "Berhasil menambahkan {$inserted} bulan invoice Reguler.");
    }

    // ==========================
    // ===== RPL (4x/6x/10x)
    // ==========================

    /**
     * Form tambah SEMESTER untuk RPL (patuh pola 4x/6x/10x)
     */
    public function rplForm(Mahasiswa $m)
    {
        // cari invoice terakhir (prioritas angsuran_ke)
        $last = Invoice::where('mahasiswa_id', $m->id)
            ->orderByDesc('angsuran_ke')
            ->orderByDesc('created_at')
            ->first();

        return view('admin.perpanjangan.rpl', [
            'mahasiswa' => $m,
            'last'      => $last,
        ]);
    }

    /**
     * Proses append SEMESTER RPL
     */
    public function rplAppend(Request $request, Mahasiswa $m)
    {
        $data = $request->validate([
            'semester'   => 'required|in:ganjil,genap',
            'skema'      => 'required|in:4,6,10',
            'total'      => 'nullable|integer|min:0', // dibagi rata; kalau null ⇒ 0
            'due_day'    => 'nullable|integer|min:1|max:28',
        ]);

        $sem    = strtolower($data['semester']);
        $skema  = (int) $data['skema'];
        $total  = (int) ($data['total'] ?? 0);
        $dueDay = (int) ($data['due_day'] ?? 5);

        // Tentukan anchor tahun SEMESTER berikutnya setelah invoice terakhir
        $last = Invoice::where('mahasiswa_id', $m->id)
            ->orderByDesc('angsuran_ke')
            ->orderByDesc('created_at')
            ->first();

        $after = $this->labelToCarbon($last?->bulan) ?? now('Asia/Jakarta');

        // Ambil via RplBilling jika tersedia, fallback inline jika tidak
        if (method_exists(RplBilling::class, 'nextAnchorYear')) {
            $Y = RplBilling::nextAnchorYear($sem, $after);
        } else {
            $Y = $this->rplNextAnchorYearFallback($sem, $after);
        }

        if (method_exists(RplBilling::class, 'scheduleForSemester')) {
            $sched = RplBilling::scheduleForSemester($sem, $skema, $Y);
        } else {
            $sched = $this->rplScheduleForSemesterFallback($sem, $skema, $Y);
        }

        $count = count($sched);
        $base  = $count ? intdiv($total, $count) : 0;
        $sisa  = $total - ($base * $count);

        $hasDueDate    = Schema::hasColumn('invoices', 'jatuh_tempo');
        $hasAngsuranKe = Schema::hasColumn('invoices', 'angsuran_ke');
        $startKe       = (int) Invoice::where('mahasiswa_id', $m->id)->max('angsuran_ke');
        if ($startKe <= 0) {
            $startKe = (int) Invoice::where('mahasiswa_id', $m->id)->count();
        }

        $inserted = 0;

        foreach ($sched as $i => $it) {
            $label = $it['label']; // "NamaBulan Tahun"

            // hindari duplikat
            $exists = Invoice::where('mahasiswa_id', $m->id)
                ->where('bulan', $label)
                ->exists();
            if ($exists) {
                continue;
            }

            $row = [
                'mahasiswa_id' => $m->id,
                'bulan'        => $label,
                'jumlah'       => $base + (($i === $count - 1) ? $sisa : 0),
                'status'       => 'Belum',
            ];
            if ($hasAngsuranKe) {
                $row['angsuran_ke'] = $startKe + $i + 1;
            }
            if ($hasDueDate) {
                $row['jatuh_tempo'] = Carbon::create((int)$it['tahun'], (int)$it['bulan'], $dueDay)->format('Y-m-d');
            }

            Invoice::create($row);
            $inserted++;
        }

        return back()->with('success', "Semester {$sem} ({$skema}x) ditambahkan untuk RPL ({$inserted} invoice baru).");
    }

    // ==========================
    // ===== Helpers
    // ==========================

    /**
     * Ambil bulan+tahun SETELAH label "NamaBulan Tahun"
     * @return array{int,int} [year, month]
     */
    protected function nextMonthFromLabel(?string $label): array
    {
        $dt = $this->labelToCarbon($label) ?? now('Asia/Jakarta');
        $dt = $dt->copy()->addMonth();
        return [(int)$dt->year, (int)$dt->month];
    }

    /**
     * Parse "NamaBulan Tahun" → Carbon, atau null kalau gagal
     */
    protected function labelToCarbon(?string $label): ?Carbon
    {
        if (!$label) {
            return null;
        }
        if (!preg_match('/(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})/u', $label, $m)) {
            return null;
        }
        $map = [
            'Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,
            'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12,
        ];
        $month = (int)($map[$m[1]] ?? 1);
        $year  = (int)$m[2];
        return Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta');
    }

    protected function bulanNama(int $m): string
    {
        $nm = [
            1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
            7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
        ];
        return $nm[$m] ?? 'Bulan-'.$m;
    }

    /**
     * FALLBACK: Anchor tahun SEMESTER berikutnya (pakai 20 Sep / 20 Feb)
     */
    protected function rplNextAnchorYearFallback(string $semester, Carbon $after): int
    {
        $sem = strtolower(trim($semester));
        $y   = (int) $after->year;

        if ($sem === 'ganjil') {
            $anchor = Carbon::create($y, 9, 20, 0, 0, 0, 'Asia/Jakarta');
            return $after->lt($anchor) ? $y : $y + 1;
        }
        // genap
        $anchor = Carbon::create($y, 2, 20, 0, 0, 0, 'Asia/Jakarta');
        return $after->lt($anchor) ? $y : $y + 1;
    }

    /**
     * FALLBACK: Build paket bulan untuk satu SEMESTER RPL (4x/6x/10x)
     */
    protected function rplScheduleForSemesterFallback(string $semester, int $skema, int $Y): array
    {
        $sem = strtolower(trim($semester));
        if (!in_array($sem, ['ganjil','genap'], true)) {
            throw new \InvalidArgumentException('Semester RPL tidak dikenal.');
        }
        if (!in_array($skema, [4,6,10], true)) {
            throw new \InvalidArgumentException('Skema RPL harus 4, 6, atau 10.');
        }

        $rows = [];
        if ($sem === 'ganjil') {
            if ($skema === 4) {
                $rows = [
                    ['m'=>9,  'y'=>$Y],     // Sep Y
                    ['m'=>12, 'y'=>$Y],     // Des Y
                    ['m'=>3,  'y'=>$Y+1],   // Mar Y+1
                    ['m'=>6,  'y'=>$Y+1],   // Jun Y+1
                ];
            } elseif ($skema === 6) {
                $rows = [
                    ['m'=>9,  'y'=>$Y],     // Sep Y
                    ['m'=>11, 'y'=>$Y],     // Nov Y
                    ['m'=>1,  'y'=>$Y+1],   // Jan Y+1
                    ['m'=>3,  'y'=>$Y+1],   // Mar Y+1
                    ['m'=>5,  'y'=>$Y+1],   // Mei Y+1
                    ['m'=>6,  'y'=>$Y+1],   // Jun Y+1
                ];
            } else { // 10x
                $rows = [
                    ['m'=>9,'y'=>$Y],['m'=>10,'y'=>$Y],['m'=>11,'y'=>$Y],['m'=>12,'y'=>$Y],
                    ['m'=>1,'y'=>$Y+1],['m'=>2,'y'=>$Y+1],['m'=>3,'y'=>$Y+1],['m'=>4,'y'=>$Y+1],['m'=>5,'y'=>$Y+1],['m'=>6,'y'=>$Y+1],
                ];
            }
        } else { // genap
            if ($skema === 4) {
                $rows = [
                    ['m'=>2,  'y'=>$Y], // Feb Y
                    ['m'=>5,  'y'=>$Y], // Mei Y
                    ['m'=>8,  'y'=>$Y], // Ags Y
                    ['m'=>11, 'y'=>$Y], // Nov Y
                ];
            } elseif ($skema === 6) {
                $rows = [
                    ['m'=>2,  'y'=>$Y], // Feb
                    ['m'=>4,  'y'=>$Y], // Apr
                    ['m'=>6,  'y'=>$Y], // Jun
                    ['m'=>8,  'y'=>$Y], // Ags
                    ['m'=>10, 'y'=>$Y], // Okt
                    ['m'=>12, 'y'=>$Y], // Des
                ];
            } else { // 10x
                $rows = [];
                for ($m=2; $m<=11; $m++) {
                    $rows[] = ['m'=>$m,'y'=>$Y]; // Feb..Nov Y
                }
            }
        }

        return array_map(function ($r) {
            return [
                'bulan' => (int)$r['m'],
                'tahun' => (int)$r['y'],
                'label' => $this->bulanNama((int)$r['m']).' '.(int)$r['y'],
            ];
        }, $rows);
    }
}
