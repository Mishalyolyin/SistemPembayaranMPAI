<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\BrivaService;

class BrivaBackfillCust extends Command
{
    protected $signature = 'briva:backfill-cust {--dry-run}';
    protected $description = 'Backfill cust_code (mahasiswas) & va_cust_code (invoices & invoices_reguler) = NIM (konstan). TIDAK ubah kolom va_*';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // 1) MAHASISWA
        $mhsUpd = 0;
        $rows = DB::table('mahasiswas')->select('id','nim','cust_code')->orderBy('id')->cursor();
        foreach ($rows as $r) {
            if (empty($r->nim)) continue;
            $desired = BrivaService::makeCustCode($r->nim);
            if ($desired !== ($r->cust_code ?? '')) {
                $mhsUpd++;
                if (!$dry) {
                    DB::table('mahasiswas')->where('id',$r->id)->update([
                        'cust_code' => $desired,
                        'updated_at'=> now(),
                    ]);
                }
            }
        }

        // 2) INVOICES (RPL & Reguler)
        $sync = function (string $table) use ($dry): int {
            if (!Schema::hasTable($table)) return 0;
            $updated = 0;
            $m = DB::table('mahasiswas')->select('nim','cust_code')->pluck('cust_code','nim');
            $rows = DB::table($table)->select('id','nim','va_cust_code')->orderBy('id')->cursor();
            foreach ($rows as $r) {
                if (empty($r->nim)) continue;
                $desired = $m[$r->nim] ?? BrivaService::makeCustCode($r->nim);
                if ($desired !== ($r->va_cust_code ?? '')) {
                    $updated++;
                    if (!$dry) {
                        DB::table($table)->where('id',$r->id)->update([
                            'va_cust_code' => $desired,
                            'updated_at'   => now(),
                        ]);
                    }
                }
            }
            return $updated;
        };

        $invRplUpd = $sync('invoices');
        $invRegUpd = $sync('invoices_reguler');

        $this->info(($dry ? '[DRY RUN] ' : '')."Mahasiswa cust_code updated: {$mhsUpd}");
        $this->info(($dry ? '[DRY RUN] ' : '')."Invoices (RPL) va_cust_code updated: {$invRplUpd}");
        $this->info(($dry ? '[DRY RUN] ' : '')."Invoices (Reguler) va_cust_code updated: {$invRegUpd}");
        $this->line('Note: va_full/va_briva_no TIDAK diubah (nunggu webhook BRI).');
        return self::SUCCESS;
    }
}
