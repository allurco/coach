<?php

use Illuminate\Support\Facades\Schedule;

// Briefing matinal: foco do dia
Schedule::command('coach:morning-ping')
    ->dailyAt('08:00')
    ->timezone('America/Fortaleza')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(env('COACH_NOTIFICATION_EMAIL'));

// Recap semanal: domingo à noite
Schedule::command('coach:weekly-briefing')
    ->weeklyOn(0, '20:00')   // 0 = domingo
    ->timezone('America/Fortaleza')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(env('COACH_NOTIFICATION_EMAIL'));

// Ping proativo de ações paradas: meio-dia, dias úteis
Schedule::command('coach:stuck-check')
    ->weekdays()
    ->dailyAt('12:00')
    ->timezone('America/Fortaleza')
    ->withoutOverlapping()
    ->onOneServer();

// Lembrete mensal do Planejador Financeiro: dia 28 às 19h.
// Só pra users com pelo menos um goal ativo de finance.
Schedule::command('coach:monthly-budget-reminder')
    ->monthlyOn(28, '19:00')
    ->timezone('America/Fortaleza')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(env('COACH_NOTIFICATION_EMAIL'));
