<?php

namespace App\Http\Controllers\MahasiswaReguler;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotifikasiRegulerController extends Controller
{
    /**
     * Menandai notifikasi mahasiswa reguler sebagai dibaca.
     *
     * @param int $id ID notifikasi
     * @return \Illuminate\Http\RedirectResponse
     */
    public function tandaiDibaca($id)
    {
        $notif = Notification::findOrFail($id);

        // Ambil ID mahasiswa reguler dari guard
        $mahasiswaId = Auth::guard('mahasiswa_reguler')->id();

        // Cek apakah notifikasi milik mahasiswa yang sedang login
        if (!$mahasiswaId || $notif->mahasiswa_id !== $mahasiswaId) {
            abort(403, 'Akses ditolak.');
        }

        $notif->dibaca = true;
        $notif->save();

        return back()->with('success', 'Notifikasi berhasil ditandai sebagai dibaca.');
    }

}
