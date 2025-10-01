<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            if ($request->is('mahasiswa') || $request->is('mahasiswa/*')) {
                return route('login'); // kamu tidak punya mahasiswa.login, jadi pakai 'login'
            }

           if ($request->is('reguler') || $request->is('reguler/*')) {
                return route('reguler.login'); // SESUAI GUARD
            }


            return route('login'); // fallback aman
        }

        return null;
    }

}
