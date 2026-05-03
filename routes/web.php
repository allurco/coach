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
