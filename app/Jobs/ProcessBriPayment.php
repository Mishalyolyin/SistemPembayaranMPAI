<?php

namespace App\Jobs;

use Throwable;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;

class ProcessBriPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry & backoff */
    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];

    /** Raw request context */
    protected string $rawBody;
    protected array $headers;
    protected string $endpoint;

    public function __construct(string $rawBody, array $headers, string $endpoint)
    {
        $this->rawBody  = $rawBody;
        $this->headers  = $headers;
        $this->endpoint = $endpoint;
    }

    public function handle(): void
    {
        $now  = Carbon::now();
        $json = json_decode($this->rawBody, true) ?: [];

        // --- Normalisasi payload ---
        $journalSeq = (string) Arr::get($json, 'journalSeq', Arr::get($json, 'journal_seq', ''));
        $custCode   = (string) Arr::get($json, 'custCode',   Arr::get($json, 'cust_code',    ''));
        // preserve leading zero, hapus non-digit
        $custCode   = preg_replace('/\D+/', '', $custCode ?? '');
        $bankCode   = (string) Arr::get($json, 'bankCode',   Arr::get($json, 'bank_code',    ''));
        $brivaNo    = (string) Arr::get($json, 'brivaNo',    Arr::get($json, 'briva_no',     ''));
        $amount     = (int)    Arr::get($json, 'amount',     Arr::get($json, 'nominal',      0));
        $paidAtStr  = (string) Arr::get($json, 'paidAt',     '');

        // --- Audit awal (best-effort, kolom fleksibel) ---
        $logId = null;
        if (Schema::hasTable('webhook_logs')) {
            $headersCol = Schema::hasColumn('webhook_logs', 'headers') ? 'headers'
                         : (Schema::hasColumn('webhook_logs', 'meta') ? 'meta' : null);
            $payloadCol = Schema::hasColumn('webhook_logs', 'payload') ? 'payload'
                         : (Schema::hasColumn('webhook_logs', 'body') ? 'body' : null);

            $logInsert = [
                'endpoint'     => $this->endpoint,
                'signature_ok' => 1,  // diverifikasi di middleware
                'status_code'  => 202,
                'processed_at' => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
            if ($headersCol) $logInsert[$headersCol] = json_encode($this->maskHeaders($this->headers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payloadCol) $logInsert[$payloadCol] = $this->rawBody;

            try {
                $logId = DB::table('webhook_logs')->insertGetId($logInsert);
            } catch (Throwable $e) {
                // non-blocking
            }
        }

        // --- Basic guard ---
        if ($journalSeq === '' || $custCode === '' || $amount <= 0) {
            $this->finalizeLog($logId, 400, ['reason' => 'bad_payload']);
            return;
        }

        // --- Idempotensi global (va_journal_seq sudah dipakai?) ---
        if ($this->journalSeqExists($journalSeq)) {
            $this->finalizeLog($logId, 200, ['duplicate' => true, 'note' => 'already_processed']);
            return;
        }

        // --- Cari invoice aktif (by nim/cust_code/VA) ---
        $invoice = $this->findActiveInvoiceByNimSmart($custCode, $bankCode);
        if (!$invoice) {
            $this->recordUnmatched($journalSeq, $custCode, $amount, $json, 'no_active_invoice');
            $this->finalizeLog($logId, 200, ['unmatched' => true, 'reason' => 'no_active_invoice']);
            return;
        }

        // --- Closed amount check ---
        $expected = (int) ($invoice->jumlah ?? $invoice->nominal ?? $invoice->amount ?? 0);
        if ($expected !== $amount) {
            $this->recordUnmatched($journalSeq, $custCode, $amount, $json, 'amount_mismatch', [
                'expected' => $expected,
            ]);
            $this->finalizeLog($logId, 200, ['unmatched' => true, 'reason' => 'amount_mismatch']);
            return;
        }

        // --- Parse paidAt (fallback now) ---
        try {
            $paidAt = $paidAtStr ? Carbon::parse($paidAtStr) : $now;
        } catch (Throwable $e) {
            $paidAt = $now;
        }

        // --- Pelunasan atomik + handle race pada unique va_journal_seq ---
        try {
            DB::beginTransaction();

            $table = $invoice->getTable();
            $id    = (int) $invoice->id;

            // RPL vs Reguler: status value
            $statusValue = ($table === 'invoices') ? 'Lunas (Otomatis)' : 'Lunas';

            $updates = [
                'status'           => $statusValue,
                'paid_at'          => $paidAt,
                'paid_amount'      => $amount,
                'va_journal_seq'   => $journalSeq,
                'updated_at'       => Carbon::now(),
            ];

            if (Schema::hasColumn($table, 'reconcile_source')) {
                $updates['reconcile_source'] = 'webhook';
            }
            // Jejak VA jika kolom tersedia
            if (Schema::hasColumn($table, 'va_briva_no') && $brivaNo)    $updates['va_briva_no']  = $brivaNo;
            if (Schema::hasColumn($table, 'va_cust_code') && $custCode)  $updates['va_cust_code'] = $custCode;
            if (Schema::hasColumn($table, 'va_full') && $bankCode && $custCode) {
                $updates['va_full'] = $bankCode . $custCode;
            }

            // Buang null saja (biar 0 tidak ikut kebuang)
            $updates = array_filter($updates, static fn($v) => $v !== null);

            DB::table($table)->where('id', $id)->update($updates);

            DB::commit();
        } catch (QueryException $qe) {
            DB::rollBack();
            // unique va_journal_seq → perlakukan sebagai idempotent race
            if (stripos($qe->getMessage(), 'va_journal_seq') !== false) {
                $this->finalizeLog($logId, 200, ['duplicate' => true, 'race' => true]);
                return;
            }
            throw $qe; // biar auto-retry sesuai backoff
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e; // biar auto-retry
        }

        // --- Selesai ---
        $this->finalizeLog($logId, 200, [
            'ok'         => true,
            'nim'        => $custCode,
            'journalSeq' => $journalSeq,
            'table'      => $invoice->getTable(),
            'row_id'     => $invoice->id ?? null,
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[BRI] ProcessBriPayment failed: '.$e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /* ================= Helpers ================= */

    private function maskHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $k => $v) {
            $kl = strtolower($k);
            if (in_array($kl, ['authorization', 'x-bri-signature', 'x-signature'], true)) {
                $masked[$k] = '***masked***';
            } else {
                $masked[$k] = $v;
            }
        }
        return $masked;
    }

    private function finalizeLog(?int $logId, int $status, array $extra = []): void
    {
        if (!$logId || !Schema::hasTable('webhook_logs')) return;

        $respCol = Schema::hasColumn('webhook_logs', 'response') ? 'response'
                 : (Schema::hasColumn('webhook_logs', 'meta') ? 'meta' : null);

        $update = [
            'status_code'  => $status,
            'processed_at' => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ];
        if ($respCol) {
            $update[$respCol] = json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        try {
            DB::table('webhook_logs')->where('id', $logId)->update($update);
        } catch (Throwable $e) {
            // non-blocking
        }
    }

    private function recordUnmatched(string $journalSeq, string $nim, int $amount, array $payload, string $reason, array $extra = []): void
    {
        if (!Schema::hasTable('unmatched_payments')) return;

        // Ambil field opsional dari payload (aman kalau gak ada)
        $bankCode = (string) Arr::get($payload, 'bankCode', Arr::get($payload, 'bank_code', ''));
        $brivaNo  = (string) Arr::get($payload, 'brivaNo',  Arr::get($payload, 'briva_no',  ''));
        $paidAtDb = null;
        try {
            $paidAtStr = (string) Arr::get($payload, 'paidAt', '');
            $paidAtDb  = $paidAtStr ? Carbon::parse($paidAtStr) : null;
        } catch (Throwable $e) {
            $paidAtDb = null;
        }

        // Wajib
        $data = [
            'journal_seq' => $journalSeq,
            'amount'      => $amount,
            'reason'      => $reason,
            'payload'     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ];

        // Tulis HANYA kolom yang memang ADA di tabel kamu
        if (Schema::hasColumn('unmatched_payments', 'nim'))       $data['nim']       = $nim ?: null;
        if (Schema::hasColumn('unmatched_payments', 'cust_code')) $data['cust_code'] = $nim ?: null; // duplikasi nim → cust_code kalau ada
        if (Schema::hasColumn('unmatched_payments', 'bank_code')) $data['bank_code'] = $bankCode ?: null;
        if (Schema::hasColumn('unmatched_payments', 'briva_no'))  $data['briva_no']  = $brivaNo ?: null;
        if (Schema::hasColumn('unmatched_payments', 'paid_at'))   $data['paid_at']   = $paidAtDb;

        try {
            DB::table('unmatched_payments')->insert($data);
        } catch (Throwable $e) {
            // biar ketahuan kalo masih ada mismatch kolom
            Log::warning('[BRI] recordUnmatched insert failed', [
                'error'       => $e->getMessage(),
                'journal_seq' => $journalSeq,
                'reason'      => $reason,
                'cols'        => array_keys($data),
            ]);
        }
    }

    private function journalSeqExists(string $journalSeq): bool
    {
        foreach ($this->candidateInvoiceTables() as $t) {
            if (!Schema::hasTable($t) || !Schema::hasColumn($t, 'va_journal_seq')) continue;
            if (DB::table($t)->where('va_journal_seq', $journalSeq)->exists()) return true;
        }
        return false;
    }

    /**
     * Cari invoice aktif:
     * - Bila kolom 'nim' ada → filter langsung by nim
     * - Bila tidak → join ke tabel mahasiswa utk filter by nim/cust_code/va_full
     */
    private function findActiveInvoiceByNimSmart(string $nim, string $bankCode): ?object
    {
        $statusCandidates = ['Belum', 'Menunggu Verifikasi', 'Menunggu_verifikasi', 'Pending', 'Belum Lunas'];

        // RPL: invoices
        if (Schema::hasTable('invoices')) {
            $q = DB::table('invoices as inv');
            if (Schema::hasColumn('invoices', 'nim')) {
                $q->where('inv.nim', $nim);
            } else {
                $q->join('mahasiswas as m', 'm.id', '=', 'inv.mahasiswa_id')
                  ->where('m.nim', $nim)
                  ->orWhere(function ($qq) use ($nim, $bankCode) {
                      $qq->where('m.cust_code', $nim);
                      if ($bankCode) $qq->orWhere('m.va_full', $bankCode . $nim);
                  });
            }

            $row = $q->whereIn('inv.status', $statusCandidates)
                     ->orderBy('inv.angsuran_ke', 'asc')
                     ->select('inv.*', DB::raw("'invoices' as _table"))
                     ->first();

            if ($row) return $this->asModel($row, 'invoices');
        }

        // Reguler: invoices_reguler
        if (Schema::hasTable('invoices_reguler')) {
            $q = DB::table('invoices_reguler as inv');
            if (Schema::hasColumn('invoices_reguler', 'nim')) {
                $q->where('inv.nim', $nim);
            } else {
                $q->join('mahasiswa_reguler as m', 'm.id', '=', 'inv.mahasiswa_reguler_id')
                  ->where('m.nim', $nim)
                  ->orWhere(function ($qq) use ($nim, $bankCode) {
                      $qq->where('m.cust_code', $nim);
                      if ($bankCode) $qq->orWhere('m.va_full', $bankCode . $nim);
                  });
            }

            $row = $q->whereIn('inv.status', $statusCandidates)
                     ->orderBy('inv.angsuran_ke', 'asc')
                     ->select('inv.*', DB::raw("'invoices_reguler' as _table"))
                     ->first();

            if ($row) return $this->asModel($row, 'invoices_reguler');
        }

        return null;
    }

    private function asModel(object $row, string $table)
    {
        return new class($table, $row)
        {
            public string $table;
            public object $row;

            public function __construct(string $table, object $row)
            {
                $this->table = $table;
                $this->row   = $row;
            }

            public function __get($k)      { return $this->row->$k ?? null; }
            public function __isset($k)    { return isset($this->row->$k); }
            public function getTable()     { return $this->table; }
            public function getKeyName()   { return 'id'; }
            public function __toString()   { return json_encode($this->row); }
        };
    }

    private function candidateInvoiceTables(): array
    {
        // urutan penting; sertakan alias aman
        return [
            'invoices',          // RPL
            'invoices_reguler',  // Reguler
            'invoice_rpls',      // alias kalau ada
            'invoice_regulers',  // alias kalau ada
        ];
    }
}
