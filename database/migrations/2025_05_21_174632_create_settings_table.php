<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // contoh:
                                             // - total_tagihan            (fallback global utk modul lain; RPL tidak pakai)
                                             // - total_tagihan_rpl        (default program RPL)
                                             // - rpl:25/26                (per TA)
                                             // - rpl:25/26:ganjil         (per TA + semester)
            $table->string('value');         // boleh "25000000" atau "25.000.000"
            $table->timestamps();
        });

        // Seed global fallback (opsional, tidak dipakai resolver RPL kita)
        DB::table('settings')->updateOrInsert(
            ['key' => 'total_tagihan'],
            ['value' => '15000000', 'created_at' => now(), 'updated_at' => now()]
        );

        // Seed default khusus RPL (ambil dari global kalau ada, kalau tidak 15 jt)
        $global = DB::table('settings')->where('key', 'total_tagihan')->value('value') ?? '15000000';
        DB::table('settings')->updateOrInsert(
            ['key' => 'total_tagihan_rpl'],
            ['value' => $global, 'created_at' => now(), 'updated_at' => now()]
        );

        /**
         * Resolver RPL (sesuai kode RplBilling) akan baca berurutan:
         *   rpl:{TA}:{semester}  →  rpl:{TA}  →  total_tagihan_rpl
         * (TIDAK menggunakan 'total_tagihan' agar tidak bentrok dengan program lain.)
         *
         * Contoh set cohort:
         *   rpl:25/26:ganjil = 25000000
         *   rpl:25/26        = 25000000
         */
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
