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
// work behind any web server / CDN configuration. file_exists check + 404
// fallback so a mis-deployment (asset missing on disk) returns a clean
// "not installable" response instead of a 500 stack trace.
//
// We use response() with the file contents (instead of response()->file())
// because BinaryFileResponse re-derives Content-Type from the file's MIME
// guess and overrides any header we pass — Cloudflare then proxies that
// (e.g. application/octet-stream for .webmanifest) and breaks PWA install.
Route::get('/manifest.webmanifest', function () {
    $path = public_path('manifest.webmanifest');
    abort_unless(file_exists($path) && is_readable($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    $path = public_path('sw.js');
    abort_unless(file_exists($path) && is_readable($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/javascript; charset=utf-8',
        // The strongest combo that Cloudflare and browsers both honor:
        // no-store + must-revalidate + max-age=0 makes CF skip the edge
        // cache, otherwise SW updates take 4h+ to propagate.
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Service-Worker-Allowed' => '/',
    ]);
})->name('pwa.sw');
