<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ===== RPL (mahasiswas) =====
        if (Schema::hasTable('mahasiswas')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                if (!Schema::hasColumn('mahasiswas', 'graduated_at')) {
                    $table->timestamp('graduated_at')->nullable()->after('updated_at');
                }
                // pastikan kolom tahun_akademik ada
                if (!Schema::hasColumn('mahasiswas', 'tahun_akademik')) {
                    $table->string('tahun_akademik')->nullable()->after('nim');
                }
                // indeks opsional buat performa
                if (Schema::hasColumn('mahasiswas', 'status')) {
                    $table->index('status', 'idx_mhs_status');
                }
            });

            // unique (nim, tahun_akademik)
            try {
                Schema::table('mahasiswas', function (Blueprint $table) {
                    $table->unique(['nim','tahun_akademik'], 'uniq_mhs_nim_ta');
                });
            } catch (\Throwable $e) {
                // abaikan jika sudah ada / data duplikat (bereskan datanya dulu kalau error)
            }
        }

        // ===== Reguler (mahasiswa_reguler) =====
        if (Schema::hasTable('mahasiswa_reguler')) {
            Schema::table('mahasiswa_reguler', function (Blueprint $table) {
                if (!Schema::hasColumn('mahasiswa_reguler', 'graduated_at')) {
                    $table->timestamp('graduated_at')->nullable()->after('updated_at');
                }
                if (!Schema::hasColumn('mahasiswa_reguler', 'tahun_akademik')) {
                    $table->string('tahun_akademik')->nullable()->after('nim');
                }
                if (Schema::hasColumn('mahasiswa_reguler', 'status')) {
                    $table->index('status', 'idx_mhsr_status');
                }
            });

            try {
                Schema::table('mahasiswa_reguler', function (Blueprint $table) {
                    $table->unique(['nim','tahun_akademik'], 'uniq_mhsr_nim_ta');
                });
            } catch (\Throwable $e) {
                // abaikan jika sudah ada / data duplikat
            }
        }

        // ===== Invoices Reguler =====
        if (Schema::hasTable('invoices_reguler')) {
            Schema::table('invoices_reguler', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices_reguler', 'angsuran_ke')) {
                    $table->unsignedInteger('angsuran_ke')->nullable()->after('status');
                }
            });

            try {
                Schema::table('invoices_reguler', function (Blueprint $table) {
                    $table->unique(['mahasiswa_reguler_id','angsuran_ke'], 'uniq_reguler_mhs_angsuran');
                });
            } catch (\Throwable $e) {
                // abaikan jika sudah ada / data duplikat
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mahasiswas')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                if (Schema::hasColumn('mahasiswas', 'graduated_at')) {
                    $table->dropColumn('graduated_at');
                }
                try { $table->dropIndex('idx_mhs_status'); } catch (\Throwable $e) {}
                try { $table->dropUnique('uniq_mhs_nim_ta'); } catch (\Throwable $e) {}
            });
        }

        if (Schema::hasTable('mahasiswa_reguler')) {
            Schema::table('mahasiswa_reguler', function (Blueprint $table) {
                if (Schema::hasColumn('mahasiswa_reguler', 'graduated_at')) {
                    $table->dropColumn('graduated_at');
                }
                try { $table->dropIndex('idx_mhsr_status'); } catch (\Throwable $e) {}
                try { $table->dropUnique('uniq_mhsr_nim_ta'); } catch (\Throwable $e) {}
            });
        }

        if (Schema::hasTable('invoices_reguler')) {
            Schema::table('invoices_reguler', function (Blueprint $table) {
                try { $table->dropUnique('uniq_reguler_mhs_angsuran'); } catch (\Throwable $e) {}
                // kolom angsuran_ke sengaja tidak di-drop agar data tetap aman saat rollback
            });
        }
    }
};
                                                            