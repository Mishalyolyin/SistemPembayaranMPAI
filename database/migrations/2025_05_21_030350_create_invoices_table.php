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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Relasi ke mahasiswas (tetap cascade sesuai logic awal)
            $table->foreignId('mahasiswa_id')
                  ->constrained('mahasiswas')
                  ->onDelete('cascade');

            // Informasi periode tagihan
            $table->string('bulan');

            // Urutan cicilan (1..N) - nullable (biar fleksibel), tetap di-index
            $table->unsignedSmallInteger('angsuran_ke')->nullable()->index();

            // Nominal & status (enum dibiarkan persis seperti semula)
            $table->integer('jumlah');
            $table->enum('status', ['Belum', 'Menunggu Verifikasi', 'Lunas', 'Lunas (Otomatis)', 'Ditolak'])
                  ->default('Belum');

            // Bukti bayar (existing)
            $table->string('bukti')->nullable();

            // Kolom moderasi (existing di versi kamu)
            $table->text('alasan_tolak')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable()->index(); // tanpa FK biar aman di semua env

            // 🔒 Anti-dobel RPL: satu mahasiswa tidak boleh punya invoice dengan angsuran_ke yang sama
            // Note: NULL tetap boleh berulang (sesuai aturan UNIQUE di MySQL)
            $table->unique(['mahasiswa_id', 'angsuran_ke'], 'uniq_rpl_mhs_angsuran');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop unik dulu (aman walau tabel langsung di-drop)
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                try {
                    $table->dropUnique('uniq_rpl_mhs_angsuran');
                } catch (\Throwable $e) {
                    // abaikan jika belum pernah dibuat
                }
            });
        }

        Schema::dropIfExists('invoices');
    }
};
