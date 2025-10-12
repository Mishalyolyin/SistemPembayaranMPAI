<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $defaultPasswordHash = Hash::make('12345678');

        Schema::create('mahasiswas', function (Blueprint $table) use ($defaultPasswordHash) {
            $table->id();

            // Identitas
            $table->string('nim')->unique();
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('alamat')->nullable();
            $table->string('foto')->nullable();

            // VA (TANPA ->after())
            $table->string('bank_code', 8)->default('390');
            $table->string('cust_code', 32)->nullable();
            $table->string('va_full', 64)->nullable();

            // Auth
            $table->string('password')->default($defaultPasswordHash);
            $table->rememberToken();

            // Akademik & rekap
            $table->enum('status', ['Aktif','Lulus','Cuti'])->default('Aktif')->index();
            $table->enum('semester_awal', ['ganjil','genap'])->nullable()->index();
            $table->string('tahun_akademik')->nullable()->index();
            $table->integer('angsuran')->nullable()->index();
            $table->integer('total_tagihan')->nullable();
            $table->string('bulan_mulai')->nullable();
            $table->timestamp('tanggal_upload')->nullable()->index();
            $table->enum('jenis_mahasiswa', ['RPL','Reguler'])->default('RPL')->index();

            $table->timestamps();

            // Unique VA
            $table->unique(['bank_code','cust_code'], 'uniq_mahasiswas_bank_cust');
            $table->unique('va_full', 'uniq_mahasiswas_va_full');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('mahasiswas');
        Schema::enableForeignKeyConstraints();
    }
};
