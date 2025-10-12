<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhooks\BriWebhookController;

Route::get('/health', fn () => response()->json(['ok' => true]))->name('api.health');

Route::prefix('webhooks/bri')->group(function () {
    Route::post('/payment', [BriWebhookController::class, 'payment'])
        ->middleware('briva.webhook')
        ->name('webhooks.bri.payment');

    Route::post('/', [BriWebhookController::class, 'payment'])
        ->middleware('briva.webhook')
        ->name('webhooks.bri');

    Route::post('/va-assigned', [BriWebhookController::class, 'vaAssigned'])
        ->middleware('briva.webhook')
        ->name('webhooks.bri.va_assigned');

    Route::post('/ping', fn () => response()->json(['ok' => true]))
        ->middleware('briva.webhook')
        ->name('webhooks.bri.ping');

    // DEBUG ONLY (tanpa middleware) — hapus saat produksi
    Route::post('/payment-nomw', [BriWebhookController::class, 'payment'])
        ->name('webhooks.bri.payment.nomw');
});
