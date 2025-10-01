<?php

namespace App\Http\Controllers\MahasiswaReguler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\InvoiceReguler;

class KwitansiRegulerController extends Controller
{
    /**
     * Kwitansi per tagihan (GET langsung) — mirip RPL.
     * Route: GET /reguler/invoices/{invoice}/kwitansi
     */
    public function kwitansi($invoiceId)
    {
        $user = Auth::guard('mahasiswa_reguler')->user();

        // Ambil invoice milik user & sudah LUNAS/Terverifikasi
        $invoice = InvoiceReguler::query()
            ->where(function ($q) use ($user) {
                // dua jalur aman: fk id / nim (bila ada)
                $q->where('mahasiswa_reguler_id', $user->id);
                if (!empty($user->nim)) {
                    $q->orWhere('nim', $user->nim);
                }
            })
            ->where('id', $invoiceId)
            ->whereIn('status', ['Lunas', 'Lunas (Otomatis)', 'Terverifikasi'])
            ->firstOrFail();

        $invoices = collect([$invoice]); // supaya 1 template bisa untuk single/bulk

        $pdf = Pdf::loadView('pdf.kwitansi-reguler', [
            'mahasiswa' => $user,
            'invoices'  => $invoices,
        ])->setPaper('A4', 'portrait');

        $filename = 'kwitansi-'.$user->nim.'-'.$invoice->id.'.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Kwitansi semua tagihan yang LUNAS — mirip RPL.
     * Route: GET /reguler/invoices/kwitansi/bulk
     */
    public function kwitansiBulk()
    {
        $user = Auth::guard('mahasiswa_reguler')->user();

        $invoices = InvoiceReguler::query()
            ->where(function ($q) use ($user) {
                $q->where('mahasiswa_reguler_id', $user->id);
                if (!empty($user->nim)) {
                    $q->orWhere('nim', $user->nim);
                }
            })
            ->whereIn('status', ['Lunas', 'Lunas (Otomatis)', 'Terverifikasi'])
            ->orderBy('bulan')
            ->get();

        abort_if($invoices->isEmpty(), 404, 'Belum ada invoice yang Lunas.');

        $pdf = Pdf::loadView('pdf.kwitansi-reguler', [
            'mahasiswa' => $user,
            'invoices'  => $invoices,
        ])->setPaper('A4', 'portrait');

        $filename = 'kwitansi-semua-lunas-'.$user->nim.'.pdf';
        return $pdf->stream($filename);
    }
}
