<?php

use App\Http\Controllers\CoachWebhookController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/coach-email', [CoachWebhookController::class, 'handle'])
    ->name('coach.webhook');

Route::get('/accept-invite/{token}', [InvitationController::class, 'show'])
    ->name('invitation.show');

Route::post('/accept-invite/{token}', [InvitationController::class, 'accept'])
    ->name('invitation.accept');

// PWA: manifest and service worker served with explicit content types so they
// work behind any web server / CDN configuration.
Route::get('/manifest.webmanifest', function () {
    return response()
        ->file(public_path('manifest.webmanifest'), [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=86400',
        ]);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    return response()
        ->file(public_path('sw.js'), [
            'Content-Type' => 'application/javascript',
            // Service worker scripts should not be cached aggressively so
            // updates roll out on every visit.
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
})->name('pwa.sw');
