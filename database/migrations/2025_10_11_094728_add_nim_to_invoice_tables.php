<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    private function addNimColumn(string $table): void {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, 'nim')) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('nim', 64)->nullable()->index();
            });
        }
    }

    public function up(): void {
        // Tambah kolom NIM
        $this->addNimColumn('invoices');

        $regTable = null;
        foreach (['invoices_reguler', 'invoices_regulers'] as $cand) {
            if (Schema::hasTable($cand)) { $regTable = $cand; break; }
        }
        if ($regTable) $this->addNimColumn($regTable);

        // Backfill invoices (RPL)
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices','nim')) {
            if (Schema::hasColumn('invoices','mahasiswa_id') && Schema::hasTable('mahasiswas')) {
                DB::statement("
                    UPDATE invoices i
                    JOIN mahasiswas m ON m.id = i.mahasiswa_id
                    SET i.nim = m.nim
                    WHERE i.nim IS NULL OR i.nim = ''
                ");
            }
        }

        // Backfill invoices reguler
        if ($regTable && Schema::hasColumn($regTable,'nim')) {
            $mregTable = null;
            foreach (['mahasiswa_regulers','mahasiswa_reguler','mahasiswa_reguleres'] as $cand) {
                if (Schema::hasTable($cand)) { $mregTable = $cand; break; }
            }
            $fkCol = null;
            foreach (['mahasiswa_reguler_id','mhs_reguler_id','mahasiswa_id'] as $cand) {
                if (Schema::hasColumn($regTable, $cand)) { $fkCol = $cand; break; }
            }
            if ($mregTable && $fkCol) {
                DB::statement("
                    UPDATE {$regTable} ir
                    JOIN {$mregTable} mr ON mr.id = ir.{$fkCol}
                    SET ir.nim = mr.nim
                    WHERE ir.nim IS NULL OR ir.nim = ''
                ");
            }
        }
    }

    public function down(): void {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices','nim')) {
            Schema::table('invoices', function (Blueprint $t) {
                $t->dropIndex(['nim']); $t->dropColumn('nim');
            });
        }
        foreach (['invoices_reguler','invoices_regulers'] as $cand) {
            if (Schema::hasTable($cand) && Schema::hasColumn($cand,'nim')) {
                Schema::table($cand, function (Blueprint $t) {
                    $t->dropIndex(['nim']); $t->dropColumn('nim');
                });
            }
        }
    }
};
