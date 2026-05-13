<?php

use App\Filament\Pages\Coach;
use App\Models\Action;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('exposes detail fields for a pending action', function () {
    Action::create([
        'title' => 'Quitar Itaú',
        'description' => 'Ligar 4004-4828 e pedir saldo devedor',
        'category' => 'financial',
        'priority' => 'high',
        'importance' => 'critical',
        'difficulty' => 'medium',
        'status' => 'pending',
    ]);

    $page = new Coach;
    $page->loadPlan();

    expect($page->planActions)->toHaveCount(1);

    $row = $page->planActions[0];

    expect($row)
        ->toHaveKey('description', 'Ligar 4004-4828 e pedir saldo devedor')
        ->toHaveKey('importance', 'critical')
        ->toHaveKey('difficulty', 'medium')
        ->toHaveKey('snooze_until', null)
        ->toHaveKey('result_notes', null)
        ->toHaveKey('completed_at', null)
        ->toHaveKey('attachments');

    expect($row['attachments'])->toBeArray()->toBeEmpty();
});

it('formats snooze_until and completed_at as date strings', function () {
    Action::create([
        'title' => 'Snoozed task',
        'status' => 'pending',
        'snooze_until' => '2026-06-15',
    ]);

    Action::create([
        'title' => 'Done task',
        'status' => 'completed',
        'completed_at' => '2026-04-10 09:30:00',
        'result_notes' => 'Pago via Pix',
    ]);

    $page = new Coach;
    $page->planFilter = 'all';
    $page->loadPlan();

    $snoozed = collect($page->planActions)->firstWhere('title', 'Snoozed task');
    $done = collect($page->planActions)->firstWhere('title', 'Done task');

    expect($snoozed['snooze_until'])->toBe('15/06/2026');
    expect($done['completed_at'])->toBe('10/04/2026');
    expect($done['result_notes'])->toBe('Pago via Pix');
});

it('exposes attachment metadata as a list', function () {
    Action::create([
        'title' => 'Action with files',
        'status' => 'pending',
        'attachments' => [
            'coach-uploads/fatura-itau.pdf',
            'coach-uploads/comprovante.png',
        ],
    ]);

    $page = new Coach;
    $page->loadPlan();

    $row = $page->planActions[0];

    expect($row['attachments'])->toBeArray()->toHaveCount(2);
    expect($row['attachments'][0])->toHaveKey('path', 'coach-uploads/fatura-itau.pdf');
    expect($row['attachments'][0])->toHaveKey('name', 'fatura-itau.pdf');
    expect($row['attachments'][1])->toHaveKey('name', 'comprovante.png');
});

it('handles null attachments without crashing', function () {
    Action::create([
        'title' => 'No attachments',
        'status' => 'pending',
        'attachments' => null,
    ]);

    $page = new Coach;
    $page->loadPlan();

    expect($page->planActions[0]['attachments'])->toBeArray()->toBeEmpty();
});

it('flags has_details true when description, snooze, attachments or completion data is present', function () {
    Action::create([
        'title' => 'With description',
        'description' => 'Some text',
        'status' => 'pending',
    ]);
    Action::create([
        'title' => 'With completed_at',
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    Action::create([
        'title' => 'With attachments',
        'status' => 'pending',
        'attachments' => ['coach-uploads/file.pdf'],
    ]);

    $page = new Coach;
    $page->planFilter = 'all';
    $page->loadPlan();

    foreach ($page->planActions as $row) {
        expect($row['has_details'])->toBeTrue();
    }
});

it('flags has_details true even for a bare action because importance/difficulty have model defaults', function () {
    // Documents the existing behavior: Action defaults importance='important'
    // and difficulty='medium', so every action carries enough metadata to show
    // the details panel. If those defaults ever go away, this test will catch
    // the visual side-effect.
    Action::create([
        'title' => 'Bare action',
        'status' => 'pending',
    ]);

    $page = new Coach;
    $page->loadPlan();

    expect($page->planActions[0]['has_details'])->toBeTrue();
});

it('does not leak details from another user\'s action', function () {
    $intruder = User::factory()->create();

    Action::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'title' => 'Intruder secret',
        'description' => 'top secret',
        'status' => 'pending',
    ]);

    $page = new Coach;
    $page->loadPlan();

    expect($page->planActions)->toBeEmpty();
});
