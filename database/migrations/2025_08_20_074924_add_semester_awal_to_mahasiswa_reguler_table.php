<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mahasiswa_reguler', function (Blueprint $table) {
            if (!Schema::hasColumn('mahasiswa_reguler', 'semester_awal')) {
                $table->enum('semester_awal', ['ganjil','genap'])
                      ->nullable()
                      ->after('bulan_mulai'); // boleh dipindah sesuai selera
            }
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_reguler', function (Blueprint $table) {
            if (Schema::hasColumn('mahasiswa_reguler', 'semester_awal')) {
                $table->dropColumn('semester_awal');
            }
        });
    }
};
