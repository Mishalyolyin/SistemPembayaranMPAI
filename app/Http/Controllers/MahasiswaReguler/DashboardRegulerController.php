<?php

namespace App\Http\Controllers\MahasiswaReguler;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\InvoiceReguler;

class DashboardRegulerController extends Controller
{
        public function index()
    {
        $mhs = Auth::guard('mahasiswa_reguler')->user();

        // ambil & urutkan invoice
        $invoices = InvoiceReguler::where('mahasiswa_reguler_id', $mhs->id)->get()
            ->sortBy(function ($i) {
                [$bulan,$tahun] = explode(' ', $i->bulan);
                $urutan = ['Januari','Februari','Maret','April','Mei','Juni',
                        'Juli','Agustus','September','Oktober','November','Desember'];
                return ((int)$tahun * 100) + array_search($bulan,$urutan);
            });

        // ── hitung total di sini, **bukan** di Blade ────────────────
        $totalTagihan = $invoices->sum('jumlah');
        $totalLunas   = $invoices->whereIn(
                            'status',['Lunas','Lunas (Otomatis)']
                        )->sum('jumlah');
        $sisaTagihan  = $totalTagihan - $totalLunas;

        return view('mahasiswa_reguler.dashboard', [
            'mahasiswa'     => $mhs,
            'invoices'      => $invoices,
            'totalTagihan'  => $totalTagihan,
            'totalLunas'    => $totalLunas,
            'sisaTagihan'   => $sisaTagihan,
        ]);
    }
}
