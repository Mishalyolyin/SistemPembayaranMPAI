<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('mahasiswa_reguler', function (Blueprint $table) {
            $table->string('tahun_akademik')->nullable()->after('nama'); // atau letakkan di mana pun kamu mau
        });
    }

    public function down()
    {
        Schema::table('mahasiswa_reguler', function (Blueprint $table) {
            $table->dropColumn('tahun_akademik');
        });
    }

};
