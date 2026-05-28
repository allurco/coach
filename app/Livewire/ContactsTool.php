<?php

namespace App\Livewire;

use App\Models\Contact;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Contacts Tool — manage the people the user shares things with (the
 * accountant, partner, doctor…). The chat's ShareViaEmail Agent Tool
 * looks up contacts by their label slug, so this is where the user
 * adds/edits the contacts the agent can target.
 *
 * A core Tool (Contact is a Core model), so it lives in app/Livewire/
 * per ADR 0005 and is embedded in the Workspace via
 * `<livewire:contacts-tool />`. Registered in the ToolRegistry as the
 * 'contacts' core Tool (ADR 0007).
 */
class ContactsTool extends Component
{
    public array $contacts = [];

    // Edit modal state.
    public ?int $editingContactId = null;

    public string $editName = '';

    public string $editEmail = '';

    public string $editLabel = '';

    public string $editNotes = '';

    // New contact modal state.
    public bool $newContactOpen = false;

    public string $newName = '';

    public string $newEmail = '';

    public string $newLabel = '';

    public string $newNotes = '';

    // Delete confirmation state.
    public ?int $deletingContactId = null;

    public ?string $deletingContactName = null;

    public function mount(): void
    {
        $this->loadContacts();
    }

    public function loadContacts(): void
    {
        $this->contacts = Contact::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'label' => $c->label,
                'notes' => $c->notes,
            ])
            ->all();
    }

    // Edit ----------------------------------------------------------------

    public function startEdit(int $contactId): void
    {
        $contact = Contact::find($contactId);
        if (! $contact) {
            return;
        }
        $this->editingContactId = $contact->id;
        $this->editName = (string) $contact->name;
        $this->editEmail = (string) $contact->email;
        $this->editLabel = (string) $contact->label;
        $this->editNotes = (string) ($contact->notes ?? '');
    }

    public function cancelEdit(): void
    {
        $this->editingContactId = null;
        $this->editName = '';
        $this->editEmail = '';
        $this->editLabel = '';
        $this->editNotes = '';
    }

    public function confirmEdit(): void
    {
        if ($this->editingContactId === null) {
            return;
        }

        $name = trim($this->editName);
        $email = trim($this->editEmail);
        if ($name === '' || $email === '') {
            return;
        }

        // Label is NOT NULL in the schema. The model's `creating` hook
        // slugs the label on insert, but not on update — so we slug here.
        // Falls back to a slug of the name when the user leaves the label
        // blank, matching the friendly default the create path uses.
        $labelInput = trim($this->editLabel) ?: $name;
        $label = Str::slug($labelInput, '-');

        Contact::where('id', $this->editingContactId)->update([
            'name' => $name,
            'email' => $email,
            'label' => $label,
            'notes' => trim($this->editNotes) ?: null,
        ]);

        $this->cancelEdit();
        $this->loadContacts();
    }

    // New contact ----------------------------------------------------------

    public function openNewContact(): void
    {
        $this->newContactOpen = true;
        $this->newName = '';
        $this->newEmail = '';
        $this->newLabel = '';
        $this->newNotes = '';
    }

    public function cancelNewContact(): void
    {
        $this->newContactOpen = false;
    }

    public function createContact(): void
    {
        $name = trim($this->newName);
        $email = trim($this->newEmail);
        if ($name === '' || $email === '') {
            return;
        }

        // Label is NOT NULL in the schema; fall back to the name when the
        // user leaves it blank. The model's `creating` hook will slug it.
        Contact::create([
            'name' => $name,
            'email' => $email,
            'label' => trim($this->newLabel) ?: $name,
            'notes' => trim($this->newNotes) ?: null,
        ]);

        $this->cancelNewContact();
        $this->loadContacts();
    }

    // Delete ---------------------------------------------------------------

    public function startDelete(int $contactId): void
    {
        $contact = Contact::find($contactId);
        if (! $contact) {
            return;
        }
        $this->deletingContactId = $contact->id;
        $this->deletingContactName = $contact->name;
    }

    public function cancelDelete(): void
    {
        $this->deletingContactId = null;
        $this->deletingContactName = null;
    }

    public function confirmDelete(): void
    {
        if ($this->deletingContactId === null) {
            return;
        }

        Contact::where('id', $this->deletingContactId)->delete();

        $this->cancelDelete();
        $this->loadContacts();
    }

    public function render()
    {
        return view('livewire.contacts-tool');
    }
}
