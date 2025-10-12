<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices_reguler', function (Blueprint $table) {
            $table->id();

            // Relasi ke mahasiswa_reguler (cascade = aman kalau data induk dihapus)
            $table->foreignId('mahasiswa_reguler_id')
                  ->constrained('mahasiswa_reguler')
                  ->onDelete('cascade');

            // Periode tagihan + urutan cicilan
            $table->string('bulan');                                        // contoh: "September 2025"
            $table->unsignedSmallInteger('angsuran_ke')->nullable()->index(); // urutan 1..N (baru)

            // Nominal & status
            $table->unsignedBigInteger('amount');

            $table->enum('status', ['Belum', 'Menunggu Verifikasi', 'Lunas', 'Ditolak'])
                  ->default('Belum');

            // Bukti pembayaran
            $table->string('bukti')->nullable();

            // Moderation (untuk proses verifikasi/penolakan)
            $table->text('alasan_tolak')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable()->index(); // tanpa FK biar fleksibel lintas env

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices_reguler');
    }
};
