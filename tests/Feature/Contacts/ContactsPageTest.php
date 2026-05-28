<?php

use App\Filament\Pages\Contacts;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// The standalone Contacts page is now a thin host that embeds
// <livewire:contacts-tool />. All behaviour is covered in
// ContactsToolTest; here we only assert the host page still renders.
// (The page is slated for retirement in the Workspace slice — ADR 0007.)
it('renders the Contacts host page', function () {
    Livewire::test(Contacts::class)->assertOk();
});
