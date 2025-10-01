<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Models\Invoice;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /* ================= Helpers ================= */

    private function normalizeAmount($v): int {
        if ($v === null) return 0;
        if (is_int($v))  return $v;
        if (is_float($v)) return (int) round($v);
        $d = preg_replace('/\D+/', '', (string)$v);
        return $d === '' ? 0 : (int) $d;
    }

    /** parse "24/25" atau "2024/2025" → [Y1,Y2] */
    private function parseAcademicYear(?string $ta): ?array {
        if (!$ta) return null;
        if (preg_match('/^(\d{2})\/(\d{2})$/', $ta, $m)) {
            $y1 = 2000 + (int)$m[1]; $y2 = 2000 + (int)$m[2]; return [$y1,$y2];
        }
        if (preg_match('/^(\d{4})\/(\d{4})$/', $ta, $m)) {
            return [(int)$m[1], (int)$m[2]];
        }
        return null;
    }

    /** anchor start & end sesuai REKAP FINAL */
    private function semesterWindow(string $semester, array $ta): array {
        [$y1, $y2] = $ta; // mis. 2024/2025
        $sem = strtolower(trim($semester));

        if ($sem === 'ganjil') {
            // 20 Sep Y1 – 31 Jan Y2
            $start = Carbon::create($y1, 9, 20, 0, 0);
            $end   = Carbon::create($y2, 1, 31, 23, 59, 59);
        } else {
            // GENAP: 20 Feb Y2 – 31 Jul Y2
            $start = Carbon::create($y2, 2, 20, 0, 0);
            $end   = Carbon::create($y2, 7, 31, 23, 59, 59);
        }
        return [$start, $end];
    }

    /** tentukan anchor mulai masa studi (bukan upload) */
    private function resolveStartAnchor($mhs): Carbon
    {
        $sem = strtolower(trim((string)($mhs->semester_awal ?? 'ganjil')));

        $ta = $this->parseAcademicYear($mhs->tahun_akademik ?? '');
        if (!$ta) {
            $y = (int)($mhs->tahun_awal ?? ($mhs->created_at?->year ?? date('Y')));
            $ta = [$y, $y + 1];
        }

        [$y1, $y2] = $ta;
        if ($sem === 'ganjil') {
            // 20 Sep Y1
            return Carbon::create($y1, 9, 20, 0, 0, 0);
        } else {
            // 20 Feb Y2
            return Carbon::create($y2, 2, 20, 0, 0, 0);
        }
    }

    /** Hitung masa studi; RPL=12 bln, Reguler=24 bln */
    private function buildMasaStudi($mhs, $invoices): array
    {
        $jenis = strtolower((string)($mhs->jenis_mahasiswa ?? $mhs->jalur ?? 'rpl'));
        $durasiBulan = ($jenis === 'reguler') ? 24 : 12;

        $start = $this->resolveStartAnchor($mhs);   // <-- 20 Sep / 20 Feb
        $now   = Carbon::now();

        // bulan berjalan (hormati hari-20)
        $elapsed  = $this->diffInWholeMonthsRespectingDay($start, $now);
        $elapsed  = min($elapsed, $durasiBulan);    // clamp biar nggak > durasi

        $sisa     = max(0, $durasiBulan - $elapsed);
        $progress = min(100, (int) floor(($elapsed / max(1, $durasiBulan)) * 100));

        $end      = (clone $start)->addMonthsNoOverflow($durasiBulan);

        $mulaiLabel   = ($mhs->semester_awal ? ucfirst($mhs->semester_awal) . ' ' : '')
                    . $start->year . ' (' . $start->locale('id')->translatedFormat('d MMMM Y') . ')';
        $selesaiLabel = $end->locale('id')->translatedFormat('d MMM Y');

        return [
            'total_bulan'    => $durasiBulan,
            'elapsed_bulan'  => $elapsed,
            'sisa_bulan'     => $sisa,
            'progress_pct'   => $progress,
            'mulai_label'    => $mulaiLabel,
            'mulai_date'     => $start,
            'selesai_label'  => $selesaiLabel,
            'selesai_date'   => $end,
        ];
    }

    /* ================= Page ================= */

    public function index()
    {
        $mhs = Auth::guard('mahasiswa')->user();
        abort_unless($mhs, 403, 'Unauthorized');

        // Ambil invoice (kompatibel jumlah/nominal)
        $cols = ['id','bulan','status','created_at'];
        if (Schema::hasColumn('invoices', 'jumlah'))   $cols[] = 'jumlah';
        elseif (Schema::hasColumn('invoices', 'nominal')) $cols[] = 'nominal';

        $invoices = Invoice::where('mahasiswa_id', $mhs->id)->get($cols)
            ->map(function ($inv) {
                $amount = $inv->jumlah ?? $inv->nominal ?? 0;
                $inv->jumlah = $this->normalizeAmount($amount);
                unset($inv->nominal);
                return $inv;
            });

        // Ringkasan tagihan
        $statusLunas = ['lunas','lunas (otomatis)','terverifikasi','paid'];
        $totalTagihan = (int) $invoices->sum('jumlah');
        $totalLunas   = (int) $invoices->filter(fn($i) => in_array(strtolower(trim((string)$i->status)), $statusLunas, true))
                                       ->sum('jumlah');
        $sisaTagihan  = max(0, $totalTagihan - $totalLunas);

        // Masa Studi — sesuai anchor semester, bukan upload
        $masa = $this->buildMasaStudi($mhs, $invoices);

        return view('mahasiswa.dashboard', [
            'mahasiswa'    => $mhs,
            'invoices'     => $invoices,
            'totalTagihan' => $totalTagihan,
            'totalLunas'   => $totalLunas,
            'sisaTagihan'  => $sisaTagihan,
            'masa'         => $masa,
        ]);
    }
    // Tambah di class (di atas buildMasaStudi), helper bulan bulat:
    private function diffInWholeMonthsRespectingDay(Carbon $start, Carbon $now): int
    {
        // hitung selisih bulan kasar
        $months = ($now->year - $start->year) * 12 + ($now->month - $start->month);
        // kalau hari sekarang BELUM melewati hari anchor, kurangi 1
        if ($now->day < $start->day) {
            $months -= 1;
        }
        return max(0, $months);
    }

}
