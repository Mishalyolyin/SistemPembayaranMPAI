<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBriPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BriWebhookController extends Controller
{
    /**
     * PAYMENT webhook handler (A5)
     * - Basic validation (journalSeq & amount)
     * - Idempotent pre-check (hindari numpuk job duplikat)
     * - Enqueue ProcessBriPayment (raw body + headers + endpoint)
     * - Fast ACK + X-Request-Id
     */
    public function payment(Request $request): JsonResponse
    {
        $requestId = $request->headers->get('X-Request-Id') ?: Str::uuid()->toString();

        // Ambil raw body & decode untuk pre-check ringan
        $raw     = $request->getContent();
        $payload = json_decode($raw, true) ?: [];

        $journalSeq = (string) ($payload['journalSeq'] ?? $payload['journal_seq'] ?? '');
        $amount     = (int)    ($payload['amount']     ?? $payload['nominal']     ?? 0);

        // Validasi minimum
        if ($journalSeq === '' || $amount <= 0) {
            return response()->json([
                'ok'         => false,
                'error'      => 'bad_request',
                'message'    => 'journalSeq/amount missing or invalid',
                'request_id' => $requestId,
            ], 400)->withHeaders(['X-Request-Id' => $requestId]);
        }

        // Idempotent pre-check: kalau sudah pernah dipakai, no-op 200
        if ($this->journalSeqExists($journalSeq)) {
            return response()->json([
                'ok'         => true,
                'duplicate'  => true,
                'request_id' => $requestId,
            ], 200)->withHeaders(['X-Request-Id' => $requestId]);
        }

        // Enqueue job ke queue "payments" (A5)
        ProcessBriPayment::dispatch(
            $raw,
            $request->headers->all(),
            $request->path()
        )->onQueue('payments');

        // Fast ACK
        return response()->json([
            'ok'         => true,
            'enqueued'   => true,
            'request_id' => $requestId,
        ], 200)->withHeaders(['X-Request-Id' => $requestId]);
    }

    /**
     * Cek idempotensi journalSeq di kedua tabel invoices.
     */
    private function journalSeqExists(string $seq): bool
    {
        if ($seq === '') return false;

        $existsRpl = DB::table('invoices')->where('va_journal_seq', $seq)->exists();
        if ($existsRpl) return true;

        $existsReg = DB::table('invoices_reguler')->where('va_journal_seq', $seq)->exists();
        return $existsReg;
    }

    /**
     * (Opsional) VA Assigned webhook – placeholder
     */
    public function vaAssigned(Request $request): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
