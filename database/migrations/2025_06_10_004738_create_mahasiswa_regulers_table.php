<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mahasiswa_reguler', function (Blueprint $table) {
            $table->id();

            // Identitas dasar
            $table->string('nim')->unique();          // 1 mahasiswa = 1 NIM
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('alamat')->nullable();
            $table->string('foto')->nullable();

            // VA konstan per mahasiswa (source of truth VA di level mahasiswa)
            $table->string('bank_code', 8)->default('390');   // kode bank BRIVA
            $table->string('cust_code', 32)->nullable();      // NIM numeric / last-N sesuai aturan BRI
            $table->string('va_full', 64)->nullable();        // 390 + cust_code

            // Auth
            $table->string('password');
            $table->rememberToken();                          // aman ditambah, ga ganggu logic

            // Status & pembayaran
            $table->enum('status', ['Aktif', 'Lulus', 'Cuti'])->default('Aktif')->index();
            $table->integer('angsuran')->nullable()->index(); // 4 / 6 / 10 (atau sesuai kebutuhan)
            $table->integer('total_tagihan')->nullable();
            $table->string('bulan_mulai')->nullable();

            $table->timestamps();

            // ===== Constraints VA (uniknya di sini, bukan di invoices) =====
            // BRI pakai cust_code → wajib unik per bank_code
            $table->unique(['bank_code', 'cust_code'], 'uniq_mhsreg_bank_cust');
            // VA full juga unik per mahasiswa (390 + cust_code)
            $table->unique('va_full', 'uniq_mhsreg_va_full');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_reguler');
    }
};
