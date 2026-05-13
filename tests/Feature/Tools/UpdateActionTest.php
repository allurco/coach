<?php

use App\Ai\Tools\UpdateAction;
use App\Models\Action;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->tool = new UpdateAction;
    $this->action = Action::create([
        'title' => 'Pagar fatura',
        'status' => 'pending',
    ]);
});

it('returns a not-found message when id does not exist', function () {
    $result = $this->tool->handle(new Request(['id' => 99999]));

    expect($result)->toContain('not found');
});

it('updates status to em_andamento', function () {
    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'in_progress']));

    expect($this->action->fresh()->status)->toBe('in_progress')
        ->and($this->action->fresh()->completed_at)->toBeNull();
});

it('sets completed_at when status changes to concluido', function () {
    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'completed']));

    $fresh = $this->action->fresh();
    expect($fresh->status)->toBe('completed')
        ->and($fresh->completed_at)->not->toBeNull();
});

it('clears completed_at when status changes from concluido back to pendente', function () {
    $this->action->update(['status' => 'completed', 'completed_at' => now()]);

    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'pending']));

    $fresh = $this->action->fresh();
    expect($fresh->status)->toBe('pending')
        ->and($fresh->completed_at)->toBeNull();
});

it('clears completed_at when status changes from concluido to cancelado', function () {
    $this->action->update(['status' => 'completed', 'completed_at' => now()]);

    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'cancelled']));

    expect($this->action->fresh()->completed_at)->toBeNull();
});

it('saves result_notes', function () {
    $this->tool->handle(new Request([
        'id' => $this->action->id,
        'result_notes' => 'Paguei via Pix da reserva XP',
    ]));

    expect($this->action->fresh()->result_notes)->toBe('Paguei via Pix da reserva XP');
});

it('updates deadline using relative shorthand', function () {
    $this->tool->handle(new Request([
        'id' => $this->action->id,
        'deadline' => '1w',
    ]));

    expect($this->action->fresh()->deadline->toDateString())
        ->toBe(now()->addWeek()->toDateString());
});

it('updates snooze_until using relative shorthand', function () {
    $this->tool->handle(new Request([
        'id' => $this->action->id,
        'snooze_until' => '3d',
    ]));

    expect($this->action->fresh()->snooze_until->toDateString())
        ->toBe(now()->addDays(3)->toDateString());
});

it('reports the changes back', function () {
    $result = $this->tool->handle(new Request([
        'id' => $this->action->id,
        'status' => 'completed',
        'result_notes' => 'feito',
    ]));

    expect($result)
        ->toContain("Action #{$this->action->id}")
        ->toContain('status → completed')
        ->toContain('notes added');
});
