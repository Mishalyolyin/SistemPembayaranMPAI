<?php

namespace App\Http\Controllers\MahasiswaReguler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfilRegulerController extends Controller
{
    /**
     * Tampilkan form edit profil mahasiswa reguler.
     */
    public function edit()
    {
        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();
        // Blade yang kamu pakai: resources/views/mahasiswa/reguler/edit-profil.blade.php
        return view('mahasiswa.reguler.edit-profil', compact('mahasiswa'));
    }

    /**
     * Update data profil mahasiswa reguler.
     * - Password opsional: hanya diproses jika diisi.
     * - Foto opsional: jika diupload, simpan & update.
     */
    public function update(Request $request)
    {
        $mahasiswa = Auth::guard('mahasiswa_reguler')->user();

        // Validasi dasar (tanpa memaksa password/foto)
        $request->validate([
            'email'  => 'nullable|email',
            'no_hp'  => 'nullable|string|max:20',
            'alamat' => 'nullable|string|max:255',
            'foto'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // ===== Password (opsional) =====
        if ($request->filled('password')) {
            // Validasi khusus password saat user mengisinya
            $request->validate([
                'current_password'      => 'required',
                'password'              => 'required|min:6|confirmed',
            ], [
                'password.confirmed'    => 'Konfirmasi password tidak cocok.',
            ]);

            // Verifikasi password saat ini
            if (! Hash::check($request->current_password, $mahasiswa->getAuthPassword())) {
                return back()
                    ->withErrors(['current_password' => 'Password sekarang salah.'])
                    ->withInput();
            }

            // Opsional: cegah password baru sama dengan yang lama
            if (Hash::check($request->password, $mahasiswa->getAuthPassword())) {
                return back()
                    ->withErrors(['password' => 'Password baru tidak boleh sama dengan password lama.'])
                    ->withInput();
            }

            // Set password baru
            $mahasiswa->password = Hash::make($request->password);

            // (Opsional) Amankan sesi lain:
            // Auth::guard('mahasiswa_reguler')->logoutOtherDevices($request->password);
        }

        // ===== Foto (opsional) =====
        if ($request->hasFile('foto')) {
            $fotoBaru = $request->file('foto')->store('profil_reguler', 'public');
            // Kompatibel dengan logic lama: simpan hanya nama file
            $mahasiswa->foto = basename($fotoBaru);
        }

        // ===== Field profil lainnya =====
        $mahasiswa->email  = $request->email;
        $mahasiswa->no_hp  = $request->no_hp;
        $mahasiswa->alamat = $request->alamat;

        $mahasiswa->save();

        return redirect()
            ->route('mahasiswa_reguler.dashboard')
            ->with('success', 'Profil berhasil diperbarui' . ($request->filled('password') ? ' dan password diganti.' : '.'));
    }
}
