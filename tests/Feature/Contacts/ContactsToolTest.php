<?php

use App\Livewire\ContactsTool;
use App\Models\Contact;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders successfully', function () {
    Livewire::test(ContactsTool::class)->assertOk();
});

it('lists the users contacts ordered by name', function () {
    Contact::create(['name' => 'Zé', 'email' => 'ze@example.com', 'label' => 'partner']);
    Contact::create(['name' => 'Ana', 'email' => 'ana@example.com', 'label' => 'accountant']);

    $component = Livewire::test(ContactsTool::class);
    $names = collect($component->get('contacts'))->pluck('name')->all();

    expect($names)->toBe(['Ana', 'Zé']);
});

it('does not leak contacts from another user', function () {
    $intruder = User::factory()->create();
    Contact::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'label' => 'intruder',
    ]);

    $component = Livewire::test(ContactsTool::class);

    expect($component->get('contacts'))->toBeEmpty();
});

it('creates a new contact via the modal', function () {
    Livewire::test(ContactsTool::class)
        ->call('openNewContact')
        ->set('newName', 'Dr. Castro')
        ->set('newEmail', 'castro@clinic.test')
        ->set('newLabel', 'Doctor')
        ->set('newNotes', 'Family doctor')
        ->call('createContact');

    $contact = Contact::where('email', 'castro@clinic.test')->first();
    expect($contact)->not->toBeNull();
    expect($contact->name)->toBe('Dr. Castro');
    // Label gets slugged on insert (model's `creating` hook).
    expect($contact->label)->toBe('doctor');
    expect($contact->notes)->toBe('Family doctor');
});

it('refuses to create a contact with empty name or email', function () {
    $before = Contact::count();

    Livewire::test(ContactsTool::class)
        ->call('openNewContact')
        ->set('newName', '   ')
        ->set('newEmail', 'has@email.com')
        ->call('createContact');

    expect(Contact::count())->toBe($before);
});

it('updates an existing contacts name, email, label, and notes', function () {
    $contact = Contact::create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'label' => 'old-label',
        'notes' => 'old notes',
    ]);

    Livewire::test(ContactsTool::class)
        ->call('startEdit', $contact->id)
        ->set('editName', 'New Name')
        ->set('editEmail', 'new@example.com')
        ->set('editLabel', 'New Label')
        ->set('editNotes', 'new notes')
        ->call('confirmEdit');

    $fresh = $contact->fresh();
    expect($fresh->name)->toBe('New Name');
    expect($fresh->email)->toBe('new@example.com');
    // Label gets re-slugged on update so the lookup-by-label in
    // ShareViaEmail keeps matching after the rename.
    expect($fresh->label)->toBe('new-label');
    expect($fresh->notes)->toBe('new notes');
});

it('deletes a contact after confirmation', function () {
    $contact = Contact::create([
        'name' => 'To delete',
        'email' => 'delete@example.com',
        'label' => 'to-delete',
    ]);

    Livewire::test(ContactsTool::class)
        ->call('startDelete', $contact->id)
        ->call('confirmDelete');

    expect(Contact::find($contact->id))->toBeNull();
});

it('cancelling delete keeps the contact', function () {
    $contact = Contact::create([
        'name' => 'To keep',
        'email' => 'keep@example.com',
        'label' => 'to-keep',
    ]);

    Livewire::test(ContactsTool::class)
        ->call('startDelete', $contact->id)
        ->call('cancelDelete');

    expect(Contact::find($contact->id))->not->toBeNull();
});

it('handles a missing contact id gracefully on edit/delete', function () {
    // No exception when the id doesn't match anything (e.g. someone
    // double-clicked delete and the row was already gone).
    Livewire::test(ContactsTool::class)
        ->call('startEdit', 99999)
        ->call('startDelete', 99999);

    expect(true)->toBeTrue();
});
