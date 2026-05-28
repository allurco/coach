<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The Contacts tool page — a thin Filament shell around the
 * <livewire:contacts-tool /> component (App\Livewire\ContactsTool),
 * which owns all the CRUD. The component is the reusable surface the
 * Workspace embeds; this page just hosts it under the "Tool Box"
 * navigation group.
 *
 * NOTE: this standalone page is slated for retirement once the Workspace
 * embeds the Tool directly (ADR 0007). Until then it delegates to the
 * component so there is a single source of truth.
 */
class Contacts extends Page
{
    protected string $view = 'filament.pages.contacts';

    protected static ?string $slug = 'contacts';

    protected static ?string $navigationLabel = 'Contacts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Tool Box';

    protected static ?int $navigationSort = 3;

    public function getHeading(): string
    {
        return (string) __('coach.tips.save_contact.title');
    }
}
