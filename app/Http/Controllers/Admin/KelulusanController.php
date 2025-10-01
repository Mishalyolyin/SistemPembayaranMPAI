<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Models\MahasiswaReguler;
use App\Services\Graduation;
use Illuminate\Support\Facades\Schema;

class KelulusanController extends Controller
{
    /** ✅ RPL: tandai Lulus */
    public function rplLulus(Mahasiswa $m)
    {
        try {
            if (!Graduation::eligibleRPL($m)) {
                return back()->with('error', 'Belum memenuhi syarat kelulusan RPL (cek invoice & masa semester).');
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memeriksa kelayakan: '.$e->getMessage());
        }

        // set status lulus
        $m->status = 'Lulus';
        if (Schema::hasColumn($m->getTable(), 'graduated_at')) {
            $m->graduated_at = now('Asia/Jakarta');
        }
        $m->save();

        return back()->with('success', 'Mahasiswa RPL ditandai Lulus.');
    }

    /** ✅ Reguler: tandai Lulus */
    public function regulerLulus(MahasiswaReguler $m)
    {
        try {
            if (!Graduation::eligibleReg($m)) {
                return back()->with('error', 'Belum memenuhi syarat kelulusan Reguler (cek invoice & masa semester).');
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memeriksa kelayakan: '.$e->getMessage());
        }

        // set status lulus
        $m->status = 'Lulus';
        if (Schema::hasColumn($m->getTable(), 'graduated_at')) {
            $m->graduated_at = now('Asia/Jakarta');
        }
        $m->save();

        return back()->with('success', 'Mahasiswa Reguler ditandai Lulus.');
    }

    /** ❌ Tolak → redirect ke Perpanjangan (RPL) */
    public function rplTolak(Mahasiswa $m)
    {
        return redirect()
            ->route('admin.perpanjangan.rpl.form', $m->id)
            ->with('info', 'Kelulusan ditolak. Silakan tambah semester (RPL).');
    }

    /** ❌ Tolak → redirect ke Perpanjangan (Reguler) */
    public function regulerTolak(MahasiswaReguler $m)
    {
        return redirect()
            ->route('admin.perpanjangan.reguler.form', $m->id)
            ->with('info', 'Kelulusan ditolak. Silakan tambah bulan (Reguler).');
    }
}
