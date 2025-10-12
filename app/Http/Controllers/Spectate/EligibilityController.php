<?php

namespace App\Http\Controllers\Spectate;

use App\Http\Controllers\Controller;
use App\Services\BrivaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EligibilityController extends Controller
{
    /** GET /api/spectate/eligibility?nim=... */
    public function byNim(Request $req, BrivaService $briva): JsonResponse
    {
        $nim = trim((string) $req->query('nim', ''));
        if ($nim === '') {
            return response()->json(['message' => 'nim is required'], 422);
        }

        // Cari di invoices (RPL)
        $rpl = DB::table('invoices')
            ->where('nim', $nim)
            ->whereIn('status', ['Belum', 'Menunggu Verifikasi'])
            ->orderBy('angsuran_ke')
            ->first();

        // Cari di invoices_reguler
        $reg = DB::table('invoices_reguler')
            ->where('nim', $nim)
            ->whereIn('status', ['Belum', 'Menunggu Verifikasi'])
            ->orderBy('angsuran_ke')
            ->first();

        $chosen = $this->pickEarliest($rpl, $reg);

        if (!$chosen) {
            return response()->json([
                'nim'      => $nim,
                'eligible' => false,
                'reason'   => 'no active installment',
            ], 200);
        }

        $program = $chosen === $rpl ? 'RPL' : 'Reguler';
        $jumlah  = (int) ($chosen->jumlah ?? $chosen->nominal ?? $chosen->amount ?? 0);
        $cust    = BrivaService::makeCustCode($nim);

        // (opsional) logging audit
        logger()->info('spectate.eligibility.nim', ['nim' => $nim, 'program' => $program, 'angsuran_ke' => $chosen->angsuran_ke]);

        return response()->json([
            'eligible'   => true,
            'nim'        => $nim,
            'custCode'   => $cust,
            'program'    => $program,
            'angsuranKe' => (int) ($chosen->angsuran_ke ?? 0),
            'jumlah'     => $jumlah,
            'status'     => $chosen->status ?? null,
            'dueAt'      => $chosen->due_at ?? ($chosen->jatuh_tempo ?? null),
            'va'         => [
                'cust_code' => $chosen->va_cust_code ?? null,
                'full'      => $chosen->va_full ?? null,
                'briva_no'  => $chosen->va_briva_no ?? null,
            ],
        ], 200);
    }

    /** GET /api/spectate/eligibility/by-cust?cust=... (last-N NIM) */
    public function byCust(Request $req): JsonResponse
    {
        $cust = preg_replace('/\D+/', '', (string) $req->query('cust', ''));
        if ($cust === '') {
            return response()->json(['message' => 'cust is required'], 422);
        }

        $len = strlen($cust);

        // Cari di RPL
        $rpl = DB::table('invoices')
            ->whereRaw('RIGHT(nim, ?) = ?', [$len, $cust])
            ->whereIn('status', ['Belum', 'Menunggu Verifikasi'])
            ->orderBy('angsuran_ke')
            ->first();

        // Cari di Reguler
        $reg = DB::table('invoices_reguler')
            ->whereRaw('RIGHT(nim, ?) = ?', [$len, $cust])
            ->whereIn('status', ['Belum', 'Menunggu Verifikasi'])
            ->orderBy('angsuran_ke')
            ->first();

        $chosen = $this->pickEarliest($rpl, $reg);

        if (!$chosen) {
            return response()->json([
                'custCode' => $cust,
                'eligible' => false,
                'reason'   => 'no active installment',
            ], 200);
        }

        $program = $chosen === $rpl ? 'RPL' : 'Reguler';
        $jumlah  = (int) ($chosen->jumlah ?? $chosen->nominal ?? $chosen->amount ?? 0);

        logger()->info('spectate.eligibility.cust', ['cust' => $cust, 'program' => $program, 'angsuran_ke' => $chosen->angsuran_ke]);

        return response()->json([
            'eligible'   => true,
            'nim'        => (string) ($chosen->nim ?? ''),
            'custCode'   => $cust,
            'program'    => $program,
            'angsuranKe' => (int) ($chosen->angsuran_ke ?? 0),
            'jumlah'     => $jumlah,
            'status'     => $chosen->status ?? null,
            'dueAt'      => $chosen->due_at ?? ($chosen->jatuh_tempo ?? null),
            'va'         => [
                'cust_code' => $chosen->va_cust_code ?? null,
                'full'      => $chosen->va_full ?? null,
                'briva_no'  => $chosen->va_briva_no ?? null,
            ],
        ], 200);
    }

    /** Pilih invoice dengan angsuran_ke paling kecil; jika salah satu null, prioritaskan yang tidak null */
    private function pickEarliest($rpl, $reg)
    {
        if ($rpl && $reg) {
            $akR = $rpl->angsuran_ke; $akG = $reg->angsuran_ke;
            if (is_null($akR) && is_null($akG)) return $rpl;       // arbitrary: prefer RPL
            if (is_null($akR))                 return $reg;
            if (is_null($akG))                 return $rpl;
            return ((int)$akR <= (int)$akG) ? $rpl : $reg;
        }
        return $rpl ?: $reg;
    }
}
