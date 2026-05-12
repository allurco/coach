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

// Carry-forward do orçamento: dia 28 às 06h, ANTES do reminder das 19h.
// Pra cada user com budget anterior, cria snapshot do próximo mês
// copiando o último — o user só ajusta o que mudou em vez de
// começar do zero. Idempotente: rodar 2x não duplica.
Schedule::command('coach:carry-budget-forward')
    ->monthlyOn(28, '06:00')
    ->timezone('America/Fortaleza')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(env('COACH_NOTIFICATION_EMAIL'));

// Lembrete mensal do Planejador Financeiro: dia 28 às 19h.
// Só pra users com pelo menos um goal ativo de finance.
// Roda DEPOIS do carry-forward, então o user clica o link e já vê
// o snapshot pré-populado pra ajustar.
Schedule::command('coach:monthly-budget-reminder')
    ->monthlyOn(28, '19:00')
    ->timezone('America/Fortaleza')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(env('COACH_NOTIFICATION_EMAIL'));
