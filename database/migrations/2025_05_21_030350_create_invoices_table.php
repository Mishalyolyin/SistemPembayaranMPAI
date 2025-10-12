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

            // Relasi utama (RPL): satu mahasiswa punya banyak invoice
            $table->foreignId('mahasiswa_id')
                  ->constrained('mahasiswas')
                  ->onDelete('cascade');

            // Informasi periode tagihan
            $table->string('bulan'); // bebas format: '01', 'Jan', '2025-01', dll.

            // Urutan cicilan (1..N) — boleh null waktu draft → tetap di-index
            $table->unsignedSmallInteger('angsuran_ke')->nullable()->index();

            // Nominal: standarisasi pakai 'jumlah'
            $table->unsignedBigInteger('jumlah');

            // Status lifecycle
            $table->enum('status', ['Belum', 'Menunggu Verifikasi', 'Lunas', 'Lunas (Otomatis)', 'Ditolak'])
                  ->default('Belum');

            // Bukti & moderasi
            $table->string('bukti')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable()->index(); // tanpa FK biar portable

            // === Kolom pembayaran (dipakai job webhook) ===
            $table->unsignedBigInteger('paid_amount')->nullable();  // nominal terbayar (opsional)
            $table->timestamp('paid_at')->nullable();               // waktu pelunasan/settlement

            // === Kolom VA (controller umumnya hanya isi va_cust_code) ===
            // CAP: 1 mahasiswa = 1 custCode stabil → index biasa, TIDAK unique
            $table->string('va_cust_code', 32)->nullable()->index();
            $table->string('va_briva_no', 20)->nullable();  // no BRIVA (opsional)
            $table->string('va_full', 64)->nullable();      // gabungan kalau dipakai (opsional)
            $table->timestamp('va_expired_at')->nullable();
            // idempotensi settlement/rekon (boleh banyak NULL; Unique aman)
            $table->string('va_journal_seq', 64)->nullable()->unique();

            // === Kolom opsional/denormalized untuk read-path cepat (spectate) ===
            $table->string('nim', 32)->nullable()->index();         // denorm dari mahasiswas.nim
            $table->string('kode', 20)->nullable()->index();        // jika kamu generate kode invoice
            $table->string('semester', 12)->nullable();             // 'ganjil' / 'genap' (opsional)
            $table->string('tahun_akademik', 20)->nullable();       // contoh: '2024/2025'
            $table->date('jatuh_tempo')->nullable();                // due-date jika pakai tipe DATE
            $table->timestamp('due_at')->nullable();                // fallback tipe TIMESTAMP (biar kompatibel)

            // 🔒 Anti-dobel RPL: satu mahasiswa tidak boleh punya angsuran_ke yang sama
            // (NULL tidak dianggap duplikat oleh MySQL — ini memang kita inginkan saat draft)
            $table->unique(['mahasiswa_id', 'angsuran_ke'], 'uniq_rpl_mhs_angsuran');

            // Index gabungan buat query eligibility/spectate super ngebut
            $table->index(['nim', 'status', 'angsuran_ke'], 'invoices_nim_status_angsuran_idx');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
