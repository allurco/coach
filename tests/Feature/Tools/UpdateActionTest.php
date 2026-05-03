<?php

use App\Ai\Tools\UpdateAction;
use App\Models\Action;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new UpdateAction;
    $this->action = Action::create([
        'title' => 'Pagar fatura',
        'status' => 'pendente',
    ]);
});

it('returns a not-found message when id does not exist', function () {
    $result = $this->tool->handle(new Request(['id' => 99999]));

    expect($result)->toContain('não encontrada');
});

it('updates status to em_andamento', function () {
    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'em_andamento']));

    expect($this->action->fresh()->status)->toBe('em_andamento')
        ->and($this->action->fresh()->completed_at)->toBeNull();
});

it('sets completed_at when status changes to concluido', function () {
    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'concluido']));

    $fresh = $this->action->fresh();
    expect($fresh->status)->toBe('concluido')
        ->and($fresh->completed_at)->not->toBeNull();
});

it('clears completed_at when status changes from concluido back to pendente', function () {
    $this->action->update(['status' => 'concluido', 'completed_at' => now()]);

    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'pendente']));

    $fresh = $this->action->fresh();
    expect($fresh->status)->toBe('pendente')
        ->and($fresh->completed_at)->toBeNull();
});

it('clears completed_at when status changes from concluido to cancelado', function () {
    $this->action->update(['status' => 'concluido', 'completed_at' => now()]);

    $this->tool->handle(new Request(['id' => $this->action->id, 'status' => 'cancelado']));

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
        'status' => 'concluido',
        'result_notes' => 'feito',
    ]));

    expect($result)
        ->toContain("Ação #{$this->action->id}")
        ->toContain('status → concluido')
        ->toContain('notas adicionadas');
});
