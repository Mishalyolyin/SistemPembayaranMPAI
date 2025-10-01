<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mahasiswas') && !Schema::hasColumn('mahasiswas', 'tahun_akademik')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                $table->string('tahun_akademik')->nullable()->after('semester_awal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mahasiswas') && Schema::hasColumn('mahasiswas', 'tahun_akademik')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                $table->dropColumn('tahun_akademik');
            });
        }
    }
};
