<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // --- helpers untuk cek index unik ---
    private function hasUniqueIndex(string $table, string $column): bool
    {
        $db = DB::getDatabaseName();
        $sql = "
            SELECT 1
            FROM information_schema.statistics
            WHERE table_schema = ?
              AND table_name   = ?
              AND column_name  = ?
              AND non_unique   = 0
            LIMIT 1
        ";
        return (bool) DB::selectOne($sql, [$db, $table, $column]);
    }

    private function ensureUniqueOn(string $table, string $column, string $indexName): void
    {
        if (! $this->hasUniqueIndex($table, $column)) {
            Schema::table($table, function (Blueprint $t) use ($column, $indexName) {
                // bikin unique index kalau belum ada
                $t->unique($column, $indexName);
            });
        }
    }

    public function up(): void
    {
        // ====== TABEL invoices (RPL) ======
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'va_cust_code')) {
                    $table->string('va_cust_code', 32)->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('invoices', 'va_briva_no')) {
                    $table->string('va_briva_no', 8)->nullable()->after('va_cust_code');
                }
                if (!Schema::hasColumn('invoices', 'va_full')) {
                    $table->string('va_full', 64)->nullable()->after('va_briva_no')->index();
                }
                if (!Schema::hasColumn('invoices', 'va_expired_at')) {
                    $table->dateTime('va_expired_at')->nullable()->after('va_full')->index();
                }
                if (!Schema::hasColumn('invoices', 'va_journal_seq')) {
                    $table->string('va_journal_seq', 128)->nullable()->after('va_expired_at'); // unique kita bikin di bawah
                }
                if (!Schema::hasColumn('invoices', 'paid_channel')) {
                    $table->string('paid_channel', 32)->nullable()->after('va_journal_seq');
                }
                if (!Schema::hasColumn('invoices', 'paid_at')) {
                    $table->dateTime('paid_at')->nullable()->after('paid_channel')->index();
                }
                if (!Schema::hasColumn('invoices', 'paid_amount')) {
                    $table->unsignedBigInteger('paid_amount')->nullable()->after('paid_at');
                }
            });

            // pastikan unique idempotensi
            if (Schema::hasColumn('invoices', 'va_journal_seq')) {
                $this->ensureUniqueOn('invoices', 'va_journal_seq', 'invoices_va_journal_seq_unique');
            }
        }

        // ====== TABEL invoices_reguler (REGULER) ======
        if (Schema::hasTable('invoices_reguler')) {
            Schema::table('invoices_reguler', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices_reguler', 'va_cust_code')) {
                    $table->string('va_cust_code', 32)->nullable()->after('status')->index();
                }
                if (!Schema::hasColumn('invoices_reguler', 'va_briva_no')) {
                    $table->string('va_briva_no', 8)->nullable()->after('va_cust_code');
                }
                if (!Schema::hasColumn('invoices_reguler', 'va_full')) {
                    $table->string('va_full', 64)->nullable()->after('va_briva_no')->index();
                }
                if (!Schema::hasColumn('invoices_reguler', 'va_expired_at')) {
                    $table->dateTime('va_expired_at')->nullable()->after('va_full')->index();
                }
                if (!Schema::hasColumn('invoices_reguler', 'va_journal_seq')) {
                    $table->string('va_journal_seq', 128)->nullable()->after('va_expired_at'); // unique kita bikin di bawah
                }
                if (!Schema::hasColumn('invoices_reguler', 'paid_channel')) {
                    $table->string('paid_channel', 32)->nullable()->after('va_journal_seq');
                }
                if (!Schema::hasColumn('invoices_reguler', 'paid_at')) {
                    $table->dateTime('paid_at')->nullable()->after('paid_channel')->index();
                }
                if (!Schema::hasColumn('invoices_reguler', 'paid_amount')) {
                    $table->unsignedBigInteger('paid_amount')->nullable()->after('paid_at');
                }
            });

            // pastikan unique idempotensi
            if (Schema::hasColumn('invoices_reguler', 'va_journal_seq')) {
                $this->ensureUniqueOn('invoices_reguler', 'va_journal_seq', 'invoices_reguler_va_journal_seq_unique');
            }
        }
    }

    public function down(): void
    {
        // Hapus unique index dulu (kalau ada), lalu drop kolom — supaya rollback aman
        if (Schema::hasTable('invoices')) {
            try { DB::statement('ALTER TABLE `invoices` DROP INDEX `invoices_va_journal_seq_unique`'); } catch (\Throwable $e) {}
            Schema::table('invoices', function (Blueprint $table) {
                foreach ([
                    'va_cust_code','va_briva_no','va_full','va_expired_at',
                    'va_journal_seq','paid_channel','paid_at','paid_amount'
                ] as $col) {
                    if (Schema::hasColumn('invoices', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('invoices_reguler')) {
            try { DB::statement('ALTER TABLE `invoices_reguler` DROP INDEX `invoices_reguler_va_journal_seq_unique`'); } catch (\Throwable $e) {}
            Schema::table('invoices_reguler', function (Blueprint $table) {
                foreach ([
                    'va_cust_code','va_briva_no','va_full','va_expired_at',
                    'va_journal_seq','paid_channel','paid_at','paid_amount'
                ] as $col) {
                    if (Schema::hasColumn('invoices_reguler', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
