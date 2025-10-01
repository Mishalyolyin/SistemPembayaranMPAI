<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mahasiswas') && !Schema::hasColumn('mahasiswas', 'semester_awal')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                $table->enum('semester_awal', ['ganjil', 'genap'])
                      ->nullable()
                      ->after('nama');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mahasiswas') && Schema::hasColumn('mahasiswas', 'semester_awal')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                $table->dropColumn('semester_awal');
            });
        }
    }
};
