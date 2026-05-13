<?php

use App\Models\Action;

it('flags a pendente action with past deadline as overdue', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'pending',
        'deadline' => now()->subDay(),
    ]);

    expect($action->isOverdue())->toBeTrue();
});

it('does not flag concluido action as overdue even with past deadline', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'completed',
        'deadline' => now()->subDay(),
    ]);

    expect($action->isOverdue())->toBeFalse();
});

it('does not flag overdue when deadline is null', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'pending',
        'deadline' => null,
    ]);

    expect($action->isOverdue())->toBeFalse();
});

it('flags as due soon when deadline is within next 3 days', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'pending',
        'deadline' => now()->addDays(2),
    ]);

    expect($action->isDueSoon())->toBeTrue();
});

it('does not flag as due soon when deadline is past', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'pending',
        'deadline' => now()->subDay(),
    ]);

    expect($action->isDueSoon())->toBeFalse();
});

it('does not flag as due soon when deadline is far in the future', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'pending',
        'deadline' => now()->addMonth(),
    ]);

    expect($action->isDueSoon())->toBeFalse();
});

it('does not flag concluido action as due soon', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'completed',
        'deadline' => now()->addDay(),
    ]);

    expect($action->isDueSoon())->toBeFalse();
});

it('respects custom days param for due soon', function () {
    $action = new Action([
        'title' => 'X',
        'status' => 'pending',
        'deadline' => now()->addDays(6),
    ]);

    expect($action->isDueSoon(3))->toBeFalse()
        ->and($action->isDueSoon(7))->toBeTrue();
});
