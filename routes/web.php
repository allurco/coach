<?php

use App\Http\Controllers\CoachWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/coach-email', [CoachWebhookController::class, 'handle'])
    ->name('coach.webhook');
