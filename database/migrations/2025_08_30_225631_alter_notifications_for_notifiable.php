<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) return;

        Schema::table('notifications', function (Blueprint $table) {
            // Kolom standar Notifiable (tambah hanya jika belum ada)
            if (!Schema::hasColumn('notifications', 'notifiable_type')) {
                $table->string('notifiable_type')->nullable()->after('id');
            }
            if (!Schema::hasColumn('notifications', 'notifiable_id')) {
                $table->unsignedBigInteger('notifiable_id')->nullable()->after('notifiable_type');
            }
            if (!Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->nullable()->after('notifiable_id');
            }
            if (!Schema::hasColumn('notifications', 'data')) {
                $table->json('data')->nullable()->after('type');
            }
            if (!Schema::hasColumn('notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('data');
            }
        });

        // Index komposit untuk performa query Notifiable (buat kalau belum ada)
        $exists = DB::selectOne("
            SELECT COUNT(1) AS c
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'notifications'
              AND index_name = 'notifications_notifiable_index'
        ");
        if (($exists->c ?? 0) == 0) {
            DB::statement("CREATE INDEX notifications_notifiable_index
                           ON notifications (notifiable_type, notifiable_id)");
        }

        // Backfill dari skema lamamu -> skema Notifiable
        // Asumsi notifikasi milik Mahasiswa RPL
        DB::table('notifications')
            ->whereNull('notifiable_type')
            ->update(['notifiable_type' => 'App\\Models\\Mahasiswa']);

        if (Schema::hasColumn('notifications', 'mahasiswa_id')) {
            DB::statement("
                UPDATE notifications
                   SET notifiable_id = mahasiswa_id
                 WHERE notifiable_id IS NULL
                   AND mahasiswa_id IS NOT NULL
            ");
        }

        if (Schema::hasColumn('notifications', 'dibaca')) {
            DB::table('notifications')
                ->whereNull('read_at')
                ->where('dibaca', true)
                ->update(['read_at' => now()]);
        }

        // NOTE:
        // Saya TIDAK menghapus kolom lama (mahasiswa_id, dibaca) agar kode lama tidak putus.
        // Kalau nanti sudah aman, kamu boleh drop kolom itu di migration terpisah.
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications')) return;

        // Hapus index jika ada
        $exists = DB::selectOne("
            SELECT COUNT(1) AS c
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'notifications'
              AND index_name = 'notifications_notifiable_index'
        ");
        if (($exists->c ?? 0) > 0) {
            DB::statement("DROP INDEX notifications_notifiable_index ON notifications");
        }

        // Hapus kolom-kolom yang baru ditambahkan (biarkan kolom lama tetap ada)
        Schema::table('notifications', function (Blueprint $table) {
            foreach (['read_at','data','type','notifiable_id','notifiable_type'] as $col) {
                if (Schema::hasColumn('notifications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
