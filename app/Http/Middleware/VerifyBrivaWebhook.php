<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VerifyBrivaWebhook
{
    // Sengaja TANPA return type supaya aman lintas versi (JsonResponse/Response/RedirectResponse).
    public function handle(Request $request, Closure $next)
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        // ===== Config (sinkron dgn .env & config('bri.webhook')) =====
        $sigHeader   = config('bri.webhook.signature_header', 'X-Signature');
        $tsHeader    = config('bri.webhook.timestamp_header', 'X-Timestamp');

        // pakai timestamp_skew kalau ada, fallback ke ts_skew (biar kompat)
        $skew        = (int) (config('bri.webhook.timestamp_skew') ?? config('bri.webhook.ts_skew', 300));

        $secret      = (string) config('bri.webhook.hmac_secret', '');
        $algo        = (string) config('bri.webhook.hash_algo', 'sha256');    // sha256
        $encoding    = (string) config('bri.webhook.encoding', 'base64');     // base64|hex

        $requireBoth = (bool)   config('bri.webhook.require_both', false);
        $bearerConf  = (string) config('bri.webhook.bearer_token', '');

        $devMode     = (bool)   config('bri.webhook.dev_mode', app()->isLocal());
        $acceptUnsignedInDev = (bool) config('bri.webhook.accept_unsigned_in_dev', true);

        try {
            // ===== 1) Ambil header =====
            $signature = (string) $request->headers->get($sigHeader, '');
            $timestamp = (string) $request->headers->get($tsHeader, '');

            $missingHeaders = ($signature === '' || $timestamp === '');
            if ($missingHeaders && !($devMode && $acceptUnsignedInDev && !$requireBoth)) {
                return $this->deny('missing_headers', $requestId);
            }

            // ===== 2) Validasi timestamp (kecuali dev unsigned) =====
            $skipTsCheck = ($devMode && $acceptUnsignedInDev && $missingHeaders && !$requireBoth);
            if (!$skipTsCheck) {
                if ($timestamp === '' || !ctype_digit($timestamp)) {
                    return $this->deny('invalid_timestamp', $requestId);
                }
                // dukung 10/13 digit
                $ts = (strlen($timestamp) === 13) ? (int) floor(((int) $timestamp) / 1000) : (int) $timestamp;
                if ($ts <= 0) {
                    return $this->deny('invalid_timestamp', $requestId);
                }
                if ($skew > 0 && abs(time() - $ts) > $skew) {
                    return $this->deny('timestamp_out_of_skew', $requestId);
                }
            }

            // ===== 3) Validasi Bearer (jika diwajibkan / di-set) =====
            if ($requireBoth || $bearerConf !== '') {
                $auth = (string) $request->headers->get('Authorization', '');
                if (!Str::startsWith($auth, 'Bearer ')) {
                    return $this->deny('bearer_missing', $requestId);
                }
                $token = trim(Str::after($auth, 'Bearer '));
                if ($bearerConf !== '' && !hash_equals($bearerConf, $token)) {
                    return $this->deny('bearer_invalid', $requestId);
                }
            }

            // ===== 4) Validasi HMAC =====
            if ($secret === '') {
                return $this->deny('server_misconfigured', $requestId);
            }

            $raw = (string) $request->getContent(); // RAW body
            if ($raw === '' && !$request->isMethod('GET')) {
                return $this->deny('empty_body', $requestId);
            }

            if (!$skipTsCheck) {
                // pola utama: base64(hmac_sha256("$ts.$raw"))
                $payload  = "{$timestamp}.{$raw}";
                $expected = $this->hmac($payload, $secret, $algo, $encoding);
                $ok = hash_equals($expected, $signature);

                // fallback DEV: izinkan sign atas raw saja (tools lama)
                if (!$ok && $devMode) {
                    $expectedRaw = $this->hmac($raw, $secret, $algo, $encoding);
                    $ok = hash_equals($expectedRaw, $signature);
                }

                if (!$ok) {
                    return $this->deny('signature_mismatch', $requestId);
                }
            }

            $resp = $next($request);
            // Tambahkan header trace
            $resp->headers->set('X-Request-Id', $requestId);
            return $resp;
        } catch (\Throwable $e) {
            // Pastikan TIDAK jadi 500: log & balas 401
            logger()->error('briva.webhook.middleware_exception', [
                'rid' => $requestId,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $body = ['ok' => false, 'error' => 'webhook_middleware_exception', 'request_id' => $requestId];
            if (config('app.debug')) {
                $body['message'] = $e->getMessage();
            }
            return response()->json($body, 401)->withHeaders(['X-Request-Id' => $requestId]);
        }
    }

    private function hmac(string $message, string $secret, string $algo, string $encoding): string
    {
        $raw = hash_hmac($algo, $message, $secret, true);
        return strtolower($encoding) === 'hex' ? bin2hex($raw) : base64_encode($raw);
    }

    private function deny(string $code, string $requestId)
    {
        return response()->json([
            'ok'         => false,
            'error'      => $code,
            'request_id' => $requestId,
        ], 401)->withHeaders(['X-Request-Id' => $requestId]);
    }
}
