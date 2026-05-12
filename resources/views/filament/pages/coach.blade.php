<x-filament-panels::page>
{{-- Coach page — orchestrator only. Each major region lives in its own
     partial under coach/ to keep this file scannable. Single Livewire
     root via <div class="coach-root">. --}}
<div class="coach-root">
    <div class="coach-page"
         x-data="{ planOpen: false, sidebarOpen: false, budgetOpen: false }"
         x-effect="document.body.classList.toggle('coach-overlay-locked', planOpen || sidebarOpen || budgetOpen)"
         @keydown.escape.window="planOpen = false; sidebarOpen = false; budgetOpen = false">

        <div class="coach-shell">
            @include('filament.pages.coach._sidebar')

            <div class="coach-main">
                @include('filament.pages.coach._tip-banner')
                @include('filament.pages.coach._header')
                @include('filament.pages.coach._chat-thread')
                @include('filament.pages.coach._composer')
            </div>
        </div>

        @include('filament.pages.coach._plan-flyout')
        @include('filament.pages.coach._budget-flyout')
    </div>

    @include('filament.pages.coach._history-panel')
    @include('filament.pages.coach._new-goal-modal')
    @include('filament.pages.coach._complete-action-modal')
    @include('filament.pages.coach._share-message-modal')
    @include('filament.pages.coach._budget-share-modal')
    @include('filament.pages.coach._footer-script')
</div>
</x-filament-panels::page>
