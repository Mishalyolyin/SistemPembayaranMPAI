<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UniversalAuthController extends Controller
{
    /**
     * Form login untuk MAHASISWA (RPL & Reguler berbagi 1 form).
     * Route: GET /login  (name: login)
     */
    public function showLoginForm()
    {
        // Jika sudah login sebagai RPL
        if (Auth::guard('mahasiswa')->check()) {
            return redirect()->route('mahasiswa.dashboard');
        }

        // Jika sudah login sebagai Reguler
        if (Auth::guard('mahasiswa_reguler')->check()) {
            return redirect()->route('reguler.dashboard');
        }

        /**
         * Catatan view:
         * - Sesuaikan dengan file yang kamu punya.
         * - Kamu mengirim file "login.blade.php", jadi pakai view('login').
         *   Jika lokasinya di resources/views/auth/login.blade.php, ganti ke view('auth.login').
         */
        return view('login');
    }

    /**
     * Proses login MAHASISWA (RPL atau Reguler).
     * Route: POST /login  (name: login.submit)
     */
    public function login(Request $request)
    {
        // Pakai satu field "email" di form, tetapi izinkan input berupa email ATAU NIM.
        $data = $request->validate([
            'email'    => ['required','string'], // bisa email atau NIM
            'password' => ['required','string'],
        ], [], [
            'email'    => 'Email/NIM',
            'password' => 'Password',
        ]);

        $identity = trim($data['email']);
        $password = $data['password'];
        $remember = $request->boolean('remember');

        // Deteksi apakah input adalah email valid; jika tidak, anggap NIM.
        $field = filter_var($identity, FILTER_VALIDATE_EMAIL) ? 'email' : 'nim';

        // 1) Coba login sebagai MAHASISWA RPL
        if (Auth::guard('mahasiswa')->attempt([$field => $identity, 'password' => $password], $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('mahasiswa.dashboard'));
        }

        // 2) Coba login sebagai MAHASISWA REGULER
        if (Auth::guard('mahasiswa_reguler')->attempt([$field => $identity, 'password' => $password], $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('reguler.dashboard'));
        }

        // Gagal keduanya
        throw ValidationException::withMessages([
            'email' => 'Email/NIM atau password salah.',
        ])->redirectTo(route('login'));
    }

    /**
     * Logout MAHASISWA (RPL / Reguler).
     * Route: POST /logout  (name: logout)
     */
    public function logout(Request $request)
    {
        // Logout kedua guard mahasiswa jika sedang aktif
        if (Auth::guard('mahasiswa')->check()) {
            Auth::guard('mahasiswa')->logout();
        }
        if (Auth::guard('mahasiswa_reguler')->check()) {
            Auth::guard('mahasiswa_reguler')->logout();
        }

        // Amankan sesi
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda sudah logout.');
    }
}
