<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Tampilkan form login admin.
     * Route: GET /admin/login  (name: admin.login)
     */
    public function showLoginForm()
    {
        // Jika sudah login sebagai admin, langsung ke dashboard
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        // SESUAIKAN DENGAN FILE YANG ADA:
        // resources/views/admin/login.blade.php
        return view('admin.login');
    }

    /**
     * Proses login admin.
     * Route: POST /admin/login (name: admin.login.submit)
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], [], [
            'email'    => 'Email',
            'password' => 'Password',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::guard('admin')->attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'Email atau password salah.',
        ])->redirectTo(route('admin.login'));
    }

    /**
     * Logout admin.
     * Route: POST /admin/logout (name: admin.logout)
     */
    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with('success', 'Anda sudah logout.');
    }
}
