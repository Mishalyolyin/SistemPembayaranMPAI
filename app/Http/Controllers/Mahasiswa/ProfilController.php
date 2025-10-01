<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    /**
     * Tampilkan form edit profil mahasiswa (RPL).
     */
    public function edit()
    {
        $mahasiswa = Auth::guard('mahasiswa')->user();
        return view('mahasiswa.edit-profil', compact('mahasiswa'));
    }

    /**
     * Update profil + (opsional) ganti password & foto.
     *
     * Aturan:
     * - Email/No HP/Alamat: bisa kosong (tetap disimpan sesuai input).
     * - Foto: opsional; kalau diupload -> simpan.
     * - Password: opsional; kalau DIISI -> wajib current_password & confirmed.
     */
    public function update(Request $request)
    {
        $mahasiswa = Auth::guard('mahasiswa')->user();

        // Validasi dasar profil (tanpa paksa isi password/foto)
        $request->validate([
            'email'   => 'nullable|email',
            'no_hp'   => 'nullable|string|max:20',
            'alamat'  => 'nullable|string|max:255',
            'foto'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            // password & current_password divalidasi kondisional di bawah
        ]);

        // === Kalau user MAU ganti password (field password diisi) ===
        if ($request->filled('password')) {
            // Validasi tambahan khusus password
            $request->validate([
                'current_password' => 'required',
                'password'         => 'required|min:6|confirmed',
            ], [
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
            ]);

            // Cek password saat ini
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
        }

        // === Foto profil (opsional) ===
        if ($request->hasFile('foto')) {
            $fotoBaru = $request->file('foto')->store('profil', 'public');
            // Simpan hanya nama file (menjaga kompatibilitas logic lama)
            $mahasiswa->foto = basename($fotoBaru);
        }

        // === Field profil lain ===
        $mahasiswa->email  = $request->email;
        $mahasiswa->no_hp  = $request->no_hp;
        $mahasiswa->alamat = $request->alamat;

        $mahasiswa->save();

        return redirect()
            ->route('mahasiswa.dashboard')
            ->with('success', 'Profil berhasil diperbarui' . ($request->filled('password') ? ' dan password diganti.' : '.'));
    }
}
