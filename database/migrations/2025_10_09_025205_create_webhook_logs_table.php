<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhook_logs')) {
            // Buat tabel lengkap (fresh install)
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Info request
                $table->string('endpoint', 128)->index();   // contoh: /webhooks/bri/payment
                $table->string('method', 10)->default('POST');
                $table->string('ip_address', 45)->nullable();

                // Payload & header
                $table->json('headers')->nullable();
                $table->longText('payload')->nullable();    // simpan raw body (tetap longText biar nggak pecah)

                // Keamanan & status
                $table->string('signature', 255)->nullable();     // simpan HMAC/Bearer (boleh masked)
                $table->boolean('signature_ok')->default(false);  // hasil verifikasi HMAC/Bearer
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->timestamp('processed_at')->nullable()->index(); // waktu selesai diproses oleh worker

                // Catatan pemrosesan
                $table->string('note', 255)->nullable();          // misal: duplicate, amount_mismatch, dll.
                $table->string('resolved_table', 64)->nullable(); // invoices / invoices_reguler / -
                $table->json('meta')->nullable();                 // extra info (nim, expected, dll.)

                $table->timestamps(); // created_at dipakai buat audit
            });
        } else {
            // Patch kolom yang kurang (idempotent)
            Schema::table('webhook_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('webhook_logs', 'method')) {
                    $table->string('method', 10)->default('POST')->after('endpoint');
                }
                if (!Schema::hasColumn('webhook_logs', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('method');
                }
                if (!Schema::hasColumn('webhook_logs', 'headers')) {
                    $table->json('headers')->nullable()->after('ip_address');
                }
                if (!Schema::hasColumn('webhook_logs', 'payload')) {
                    $table->longText('payload')->nullable()->after('headers');
                }
                if (!Schema::hasColumn('webhook_logs', 'signature')) {
                    $table->string('signature', 255)->nullable()->after('payload');
                }
                if (!Schema::hasColumn('webhook_logs', 'signature_ok')) {
                    $table->boolean('signature_ok')->default(false)->after('signature');
                }
                if (!Schema::hasColumn('webhook_logs', 'status_code')) {
                    $table->unsignedSmallInteger('status_code')->nullable()->after('signature_ok');
                }
                if (!Schema::hasColumn('webhook_logs', 'processed_at')) {
                    $table->timestamp('processed_at')->nullable()->after('status_code');
                }
                if (!Schema::hasColumn('webhook_logs', 'note')) {
                    $table->string('note', 255)->nullable()->after('processed_at');
                }
                if (!Schema::hasColumn('webhook_logs', 'resolved_table')) {
                    $table->string('resolved_table', 64)->nullable()->after('note');
                }
                if (!Schema::hasColumn('webhook_logs', 'meta')) {
                    $table->json('meta')->nullable()->after('resolved_table');
                }
            });

            // Pastikan index penting ada
            Schema::table('webhook_logs', function (Blueprint $table) {
                // endpoint sudah index saat create; kalau tabel lama belum, aman di-skip
                if (!Schema::hasColumn('webhook_logs', 'processed_at')) return;
                try { $table->index('processed_at'); } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
