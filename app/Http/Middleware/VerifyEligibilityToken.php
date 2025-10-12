<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEligibilityToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil token dari config/ENV
        $token = (string) config('bri.eligibility.bearer_token', env('BRI_ELIGIBILITY_TOKEN', ''));

        if ($token === '') {
            return response()->json(['message' => 'Eligibility token not configured'], 503);
        }

        $auth = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!hash_equals($token, trim($m[1]))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
