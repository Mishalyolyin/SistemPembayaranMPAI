<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmokeQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Job meta opsional */
    public array $meta;

    /** Retry & timeout (opsional) */
    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(array $meta = [])
    {
        $this->meta = $meta + [
            'request_id' => (string) Str::uuid(),
            'queued_at'  => now()->toIso8601String(),
        ];
    }

    public function handle(): void
    {
        // Bukti eksekusi — simple log aja biar ga ganggu DB
        Log::info('SmokeQueueJob fired ✅', $this->meta);
        // kamu bisa ganti ini jadi insert ke tabel apa pun kalau mau jejak di DB
        // DB::table('webhook_logs')->insert([...]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SmokeQueueJob failed ❌', [
            'error' => $e->getMessage(),
            'meta'  => $this->meta,
        ]);
    }
}
