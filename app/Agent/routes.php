<?php

use App\Agent\Http\Controllers\CoachWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Agent-owned routes. Loaded by AgentServiceProvider::boot() only when
 * `coach.agent.enabled` is true — a fork that ships without the agent
 * never registers these endpoints.
 */
Route::post('/webhooks/coach-email', [CoachWebhookController::class, 'handle'])
    ->name('coach.webhook');
