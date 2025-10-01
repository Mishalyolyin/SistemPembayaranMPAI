<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotifikasiController extends Controller
{
    public function tandaiDibaca($id)
    {
        $notif = Notification::findOrFail($id);

        if ($notif->mahasiswa_id !== Auth::guard('mahasiswa')->id()) {
            abort(403);
        }

        $notif->dibaca = true;
        $notif->save();

        return back()->with('success', 'Notifikasi ditandai sebagai dibaca.');
    }
}
