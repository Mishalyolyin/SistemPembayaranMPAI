<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ===== RPL (mahasiswas) =====
        if (Schema::hasTable('mahasiswas')) {
            Schema::table('mahasiswas', function (Blueprint $table) {
                if (!Schema::hasColumn('mahasiswas', 'graduated_at')) {
                    $table->timestamp('graduated_at')->nullable()->after('updated_at');
                }
                if (!Schema::hasColumn('mahasiswas', 'tahun_akademik')) {
                    $table->string('tahun_akademik')->nullable()->after('nim');
                }
                if (Schema::hasColumn('mahasiswas', 'status')) {
                    // index nama custom (boleh ada bareng default; DB cuma simpan satu)
                    $table->index('status', 'idx_mhs_status');
                }
            });

            // unique (nim, tahun_akademik)
            try {
                Schema::table('mahasiswas', function (Blueprint $table) {
                    $table->unique(['nim','tahun_akademik'], 'uniq_mhs_nim_ta');
                });
            } catch (\Throwable $e) {
                // abaikan jika sudah ada / ada duplikasi data
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
                // abaikan jika sudah ada / ada duplikasi data
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
                // abaikan jika sudah ada / ada duplikasi data
            }
        }
    }

    public function down(): void
    {
        // ===== RPL (mahasiswas) =====
        if (Schema::hasTable('mahasiswas')) {
            // pre-check index/unique yg mungkin ada (custom & default)
            $hasIdxCustom   = $this->indexExists('mahasiswas', 'idx_mhs_status');
            $hasIdxDefault  = $this->indexExists('mahasiswas', 'mahasiswas_status_index');
            $hasUnqCustom   = $this->indexExists('mahasiswas', 'uniq_mhs_nim_ta');
            $hasUnqDefault  = $this->indexExists('mahasiswas', 'mahasiswas_nim_tahun_akademik_unique');

            Schema::table('mahasiswas', function (Blueprint $table) use ($hasIdxCustom, $hasIdxDefault, $hasUnqCustom, $hasUnqDefault) {
                if (Schema::hasColumn('mahasiswas', 'graduated_at')) {
                    $table->dropColumn('graduated_at');
                }

                // Drop INDEX status: pilih yang memang ada
                if ($hasIdxCustom) {
                    $table->dropIndex('idx_mhs_status');
                } elseif ($hasIdxDefault) {
                    // drop berdasarkan kolom (biar Laravel tebak nama default)
                    $table->dropIndex(['status']);
                }

                // Drop UNIQUE (nim, tahun_akademik)
                if ($hasUnqCustom) {
                    $table->dropUnique('uniq_mhs_nim_ta');
                } elseif ($hasUnqDefault) {
                    $table->dropUnique(['nim','tahun_akademik']);
                }
            });
        }

        // ===== Reguler (mahasiswa_reguler) =====
        if (Schema::hasTable('mahasiswa_reguler')) {
            $hasIdxCustom   = $this->indexExists('mahasiswa_reguler', 'idx_mhsr_status');
            $hasIdxDefault  = $this->indexExists('mahasiswa_reguler', 'mahasiswa_reguler_status_index');
            $hasUnqCustom   = $this->indexExists('mahasiswa_reguler', 'uniq_mhsr_nim_ta');
            $hasUnqDefault  = $this->indexExists('mahasiswa_reguler', 'mahasiswa_reguler_nim_tahun_akademik_unique');

            Schema::table('mahasiswa_reguler', function (Blueprint $table) use ($hasIdxCustom, $hasIdxDefault, $hasUnqCustom, $hasUnqDefault) {
                if (Schema::hasColumn('mahasiswa_reguler', 'graduated_at')) {
                    $table->dropColumn('graduated_at');
                }

                if ($hasIdxCustom) {
                    $table->dropIndex('idx_mhsr_status');
                } elseif ($hasIdxDefault) {
                    $table->dropIndex(['status']);
                }

                if ($hasUnqCustom) {
                    $table->dropUnique('uniq_mhsr_nim_ta');
                } elseif ($hasUnqDefault) {
                    $table->dropUnique(['nim','tahun_akademik']);
                }
            });
        }

        // ===== Invoices Reguler =====
        if (Schema::hasTable('invoices_reguler')) {
            // FK-safe: pastikan FK punya index sebelum drop UNIQUE gabungan
            if (
                Schema::hasColumn('invoices_reguler', 'mahasiswa_reguler_id') &&
                !$this->indexExists('invoices_reguler', 'invoices_reguler_mahasiswa_reguler_id_index')
            ) {
                Schema::table('invoices_reguler', function (Blueprint $table) {
                    $table->index('mahasiswa_reguler_id', 'invoices_reguler_mahasiswa_reguler_id_index');
                });
            }

            if ($this->indexExists('invoices_reguler', 'uniq_reguler_mhs_angsuran')) {
                Schema::table('invoices_reguler', function (Blueprint $table) {
                    $table->dropUnique('uniq_reguler_mhs_angsuran');
                });
            }

            // kolom angsuran_ke sengaja tidak di-drop
        }
    }

    /** Helper cek index by name (MySQL/MariaDB); fallback false untuk driver lain */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $db = DB::getDatabaseName();
            $row = DB::selectOne(
                "SELECT COUNT(1) AS c
                 FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?
                 LIMIT 1",
                [$db, $table, $indexName]
            );
            return (int)($row->c ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
