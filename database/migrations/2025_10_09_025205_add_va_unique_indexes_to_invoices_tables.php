<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->fix('invoices');
        $this->fix('invoices_reguler'); // sesuaikan kalau nama tabel reguler berbeda
    }

    public function down(): void
    {
        foreach (['invoices','invoices_reguler'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Pre-check nama index agar drop aman (cuma dieksekusi kalau beneran ada)
            $hasIdxVaFull   = $this->indexExists($table, "{$table}_va_full_index");
            $hasUnqJournal  = $this->indexExists($table, "{$table}_va_journal_seq_unique");
            $hasUnqNimAng   = $this->indexExists($table, "{$table}_nim_angsuran_ke_unique");

            Schema::table($table, function (Blueprint $t) use ($table, $hasIdxVaFull, $hasUnqJournal, $hasUnqNimAng) {
                // Drop INDEX va_full (biasa)
                if ($hasIdxVaFull) {
                    try { $t->dropIndex("{$table}_va_full_index"); } catch (\Throwable $e) {}
                }
                // Drop UNIQUE va_journal_seq
                if ($hasUnqJournal) {
                    try { $t->dropUnique("{$table}_va_journal_seq_unique"); } catch (\Throwable $e) {}
                }
                // Drop UNIQUE (nim, angsuran_ke) kalau memang dibuat
                if ($hasUnqNimAng) {
                    try { $t->dropUnique("{$table}_nim_angsuran_ke_unique"); } catch (\Throwable $e) {}
                }
            });
        }

        // NOTE: kolom yang pernah ditambah TIDAK di-drop supaya backward-compatible.
    }

    private function fix(string $table): void
    {
        if (!Schema::hasTable($table)) return;

        // 1) Tambah kolom-kolom VA & paid* kalau belum ada (idempotent)
        Schema::table($table, function (Blueprint $t) use ($table) {
            if (!Schema::hasColumn($table, 'va_cust_code')) {
                $t->string('va_cust_code', 32)->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn($table, 'va_briva_no')) {
                $t->string('va_briva_no', 8)->nullable()->after('va_cust_code');
            }
            if (!Schema::hasColumn($table, 'va_full')) {
                $t->string('va_full', 64)->nullable()->after('va_briva_no');
            }
            if (!Schema::hasColumn($table, 'va_expired_at')) {
                $t->dateTime('va_expired_at')->nullable()->after('va_full')->index();
            }
            if (!Schema::hasColumn($table, 'va_journal_seq')) {
                $t->string('va_journal_seq', 64)->nullable()->after('va_expired_at');
            }
            if (!Schema::hasColumn($table, 'paid_at')) {
                $t->dateTime('paid_at')->nullable()->after('va_journal_seq')->index();
            }
            if (!Schema::hasColumn($table, 'paid_amount')) {
                $t->unsignedBigInteger('paid_amount')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn($table, 'paid_channel')) {
                $t->string('paid_channel', 64)->nullable()->after('paid_amount');
            }
            if (!Schema::hasColumn($table, 'reconcile_source')) {
                $t->string('reconcile_source', 16)->nullable()->after('paid_channel'); // webhook/pull/manual
            }
        });

        // 2) Kalau dulu pernah ada UNIQUE di va_full, lepas (jadi index biasa)
        //    Coba by-name dan by-columns (untuk nama default Laravel)
        if ($this->indexExists($table, "{$table}_va_full_unique")) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                try { $t->dropUnique("{$table}_va_full_unique"); } catch (\Throwable $e) {}
            });
        }
        try {
            Schema::table($table, function (Blueprint $t) {
                // dropUnique by columns → Laravel akan tebak nama index default
                $t->dropUnique(['va_full']);
            });
        } catch (\Throwable $e) {
            // ignore: kalau tidak ada, lanjut
        }

        // 3) Pasang INDEX biasa untuk va_full (karena VA konstan dipakai lintas angsuran)
        if (!$this->indexExists($table, "{$table}_va_full_index")) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                try { $t->index('va_full', "{$table}_va_full_index"); } catch (\Throwable $e) {}
            });
        }

        // 4) Idempotensi: va_journal_seq harus UNIQUE
        if (!$this->indexExists($table, "{$table}_va_journal_seq_unique")) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                try { $t->unique('va_journal_seq', "{$table}_va_journal_seq_unique"); } catch (\Throwable $e) {}
            });
        }

        // 5) (Opsional, recommended) Cegah duplikasi angsuran ke-n per NIM
        if (Schema::hasColumn($table, 'nim') && Schema::hasColumn($table, 'angsuran_ke')) {
            if (!$this->indexExists($table, "{$table}_nim_angsuran_ke_unique")) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    try { $t->unique(['nim', 'angsuran_ke'], "{$table}_nim_angsuran_ke_unique"); } catch (\Throwable $e) {}
                });
            }
        }
    }

    /** Cek keberadaan index by name (MySQL/MariaDB); fallback false untuk driver lain */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $db = DB::getDatabaseName();
            $row = DB::selectOne(
                "SELECT COUNT(1) AS c
                 FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?
                 LIMIT 1",
                [$db, $table, $indexName]
            );
            return (int)($row->c ?? 0) > 0;
        } catch (\Throwable $e) {
            // untuk SQLite/pgsql, anggap tidak ada (biar tidak error)
            return false;
        }
    }
};
