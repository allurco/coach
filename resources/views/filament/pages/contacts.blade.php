<x-filament-panels::page>
    {{-- Thin host for the reusable Contacts Tool. All CRUD lives in the
         component (App\Livewire\ContactsTool); the Workspace embeds the
         same component directly (ADR 0007). --}}
    <livewire:contacts-tool />
</x-filament-panels::page>
