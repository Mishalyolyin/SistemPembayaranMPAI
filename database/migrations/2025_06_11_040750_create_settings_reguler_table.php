<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_reguler', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // contoh key:
                                               // - total_tagihan_reguler         (default program)
                                               // - reguler:25/26                 (per TA)
                                               // - reguler:25/26:ganjil          (per TA + semester)
            $table->string('value');           // simpan nominal dalam angka atau string "25.000.000"
            $table->timestamps();
        });

        // Seed default: total tagihan reguler = 10.000.000
        // Gunakan updateOrInsert agar aman dari duplikasi jika migrate diulang.
        DB::table('settings_reguler')->updateOrInsert(
            ['key' => 'total_tagihan_reguler'],
            ['value' => '10000000', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_reguler');
    }
};
