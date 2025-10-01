<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ===== invoices (RPL) =====
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                // alasan_tolak
                if (!Schema::hasColumn('invoices', 'alasan_tolak')) {
                    $table->text('alasan_tolak')->nullable();
                }
                // verified_at
                if (!Schema::hasColumn('invoices', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }
                // verified_by (tanpa FK biar aman di semua env; kasih index aja)
                if (!Schema::hasColumn('invoices', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->index();
                }
            });
        }

        // ===== invoices_reguler =====
        if (Schema::hasTable('invoices_reguler')) {
            Schema::table('invoices_reguler', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices_reguler', 'alasan_tolak')) {
                    $table->text('alasan_tolak')->nullable();
                }
                if (!Schema::hasColumn('invoices_reguler', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }
                if (!Schema::hasColumn('invoices_reguler', 'verified_by')) {
                    $table->unsignedBigInteger('verified_by')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'verified_by'))  $table->dropColumn('verified_by');
                if (Schema::hasColumn('invoices', 'verified_at'))  $table->dropColumn('verified_at');
                if (Schema::hasColumn('invoices', 'alasan_tolak')) $table->dropColumn('alasan_tolak');
            });
        }

        if (Schema::hasTable('invoices_reguler')) {
            Schema::table('invoices_reguler', function (Blueprint $table) {
                if (Schema::hasColumn('invoices_reguler', 'verified_by'))  $table->dropColumn('verified_by');
                if (Schema::hasColumn('invoices_reguler', 'verified_at'))  $table->dropColumn('verified_at');
                if (Schema::hasColumn('invoices_reguler', 'alasan_tolak')) $table->dropColumn('alasan_tolak');
            });
        }
    }
};
