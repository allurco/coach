<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('auto-assigns the authenticated user on creating', function () {
    $contact = Contact::create([
        'label' => 'contador',
        'name' => 'João',
        'email' => 'joao@example.com',
    ]);

    expect($contact->user_id)->toBe($this->user->id);
});

it('slugifies the label so casing/whitespace lookups work', function () {
    Contact::create([
        'label' => 'Meu Contador',
        'email' => 'joao@example.com',
    ]);

    expect(Contact::forUserAndLabel($this->user->id, 'Meu Contador'))->not->toBeNull()
        ->and(Contact::forUserAndLabel($this->user->id, 'meu-contador'))->not->toBeNull()
        ->and(Contact::forUserAndLabel($this->user->id, 'MEU CONTADOR'))->not->toBeNull();
});

it('enforces unique label per user', function () {
    Contact::create(['label' => 'contador', 'email' => 'a@example.com']);

    expect(fn () => Contact::create(['label' => 'contador', 'email' => 'b@example.com']))
        ->toThrow(QueryException::class);
});

it('lets two different users have the same label', function () {
    Contact::create(['label' => 'contador', 'email' => 'a@example.com']);

    $other = User::factory()->create();
    auth()->logout();
    auth()->login($other);

    $second = Contact::create(['label' => 'contador', 'email' => 'b@example.com']);

    expect($second->user_id)->toBe($other->id);
});

it('hides another user’s contacts via the global scope', function () {
    $other = User::factory()->create();
    Contact::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'esposa',
        'email' => 'other@example.com',
    ]);

    expect(Contact::count())->toBe(0)
        ->and(Contact::where('email', 'other@example.com')->first())->toBeNull();
});

it('forUserAndLabel returns null when the user has no matching contact', function () {
    expect(Contact::forUserAndLabel($this->user->id, 'inexistente'))->toBeNull();
});

it('forUserAndLabel does not leak across users', function () {
    $other = User::factory()->create();
    Contact::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'contador',
        'email' => 'other@example.com',
    ]);

    expect(Contact::forUserAndLabel($this->user->id, 'contador'))->toBeNull();
});
