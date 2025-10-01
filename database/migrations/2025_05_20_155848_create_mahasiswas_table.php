<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Default password "12345678" (sudah di-hash)
        $defaultPasswordHash = Hash::make('12345678');

        Schema::create('mahasiswas', function (Blueprint $table) use ($defaultPasswordHash) {
            $table->id();

            // Identitas dasar
            $table->string('nim')->unique();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('alamat')->nullable();
            $table->string('foto')->nullable();

            // Auth
            $table->string('password')->default($defaultPasswordHash);
            $table->rememberToken();

            // Status akademik
            $table->enum('status', ['Aktif', 'Lulus', 'Cuti'])->default('Aktif')->index();

            // Metadata REKAP
            $table->enum('semester_awal', ['ganjil','genap'])->nullable()->index(); // anchor semester
            $table->string('tahun_akademik')->nullable()->index();                  // format: 2024/2025

            // Skema pembayaran
            $table->integer('angsuran')->nullable()->index();   // 4 / 6 / 10
            $table->integer('total_tagihan')->nullable();       // fallback dari settings
            $table->string('bulan_mulai')->nullable();          // "September" / "Februari" / "9" / "2"

            // === Tambahan sesuai REKAP (Upload Mahasiswa) ===
            $table->timestamp('tanggal_upload')->nullable()->index();           // dicatat saat import/upload
            $table->enum('jenis_mahasiswa', ['RPL','Reguler'])->default('RPL')->index(); // tipe mahasiswa

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswas');
    }
};
