<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\VerifyBrivaWebhook;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // health: __DIR__.'/../routes/health.php', // opsional
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias middleware untuk webhook BRI
        $middleware->alias([
            'briva.webhook' => VerifyBrivaWebhook::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // biarkan default; tidak perlu custom apa-apa
    })
    ->create();
