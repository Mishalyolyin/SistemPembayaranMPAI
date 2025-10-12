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

            // Relasi ke mahasiswa_reguler (cascade aman)
            $table->foreignId('mahasiswa_reguler_id')
                  ->constrained('mahasiswa_reguler') // ganti ke nama tabel yang bener kalau perlu
                  ->onDelete('cascade');

            // Periode tagihan + urutan cicilan
            $table->string('bulan');                                           // contoh: "September 2025"
            $table->unsignedSmallInteger('angsuran_ke')->nullable()->index();  // 1..N (nullable = fleksibel)

            // Nominal selaras controller/model
            $table->unsignedBigInteger('jumlah');

            // Status (kompatibel sama viewmu)
            $table->enum('status', [
                'Belum',
                'Menunggu Verifikasi',
                'Lunas',
                'Lunas (Otomatis)',
                'Terverifikasi',
                'Ditolak',
            ])->default('Belum');

            // Bukti & moderasi
            $table->string('bukti')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable()->index(); // tanpa FK (portabel lintas env)

            // Due date (opsional) — sediakan dua tipe biar fleksibel
            $table->date('jatuh_tempo')->nullable();
            $table->timestamp('due_at')->nullable();

            // === Kolom pembayaran (dipakai job webhook) ===
            $table->unsignedBigInteger('paid_amount')->nullable();  // nominal terbayar (opsional)
            $table->timestamp('paid_at')->nullable();               // waktu settlement

            // ===== Kolom VA (controller biasanya hanya isi va_cust_code) =====
            $table->string('va_cust_code', 32)->nullable()->index(); // CAP: custCode stabil → INDEX biasa
            $table->string('va_briva_no', 20)->nullable();
            $table->string('va_full', 64)->nullable();
            $table->timestamp('va_expired_at')->nullable();
            $table->string('va_journal_seq', 64)->nullable()->unique(); // idempotensi rekonsiliasi

            // ===== Kolom denormalized buat spectate cepat (no JOIN) =====
            $table->string('nim', 32)->nullable()->index();   // cache dari profil reguler
            $table->string('kode', 20)->nullable()->index();  // kalau kamu pakai kode invoice
            $table->string('semester', 12)->nullable();       // 'ganjil' / 'genap'
            $table->string('tahun_akademik', 20)->nullable(); // contoh: '2024/2025'

            // 🔒 Anti-dobel Reguler: satu mahasiswa_reguler tidak boleh punya angsuran_ke sama
            // (NULL tidak dianggap duplikat oleh MySQL — pas buat draft)
            $table->unique(
                ['mahasiswa_reguler_id', 'angsuran_ke'],
                'uniq_reg_mhs_angsuran'
            );

            // Index gabungan buat eligibility/spectate super ngebut
            $table->index(
                ['nim', 'status', 'angsuran_ke'],
                'invoices_reg_nim_status_angsuran_idx'
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices_reguler');
    }
};
