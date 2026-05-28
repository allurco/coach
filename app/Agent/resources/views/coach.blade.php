<x-filament-panels::page>
{{-- Coach page — orchestrator only. Each major region lives in its own
     partial under coach/ to keep this file scannable. Single Livewire
     root via <div class="coach-root">. --}}
<div class="coach-root">
    <div class="coach-page"
         x-data="{ planOpen: false, sidebarOpen: false }"
         x-effect="document.body.classList.toggle('coach-overlay-locked', planOpen || sidebarOpen)"
         @keydown.escape.window="planOpen = false; sidebarOpen = false">

        <div class="coach-shell">
            @include('agent::coach._sidebar')

            <div class="coach-main">
                @include('agent::coach._tip-banner')
                @include('agent::coach._header')
                @include('agent::coach._chat-thread')
                @include('agent::coach._composer')
            </div>
        </div>

        <livewire:plan-flyout :active-goal-id="$activeGoalId" :as-drawer="true" />
        {{-- Budget flyout + share modal now live inside <livewire:budget-tool />,
             embedded in the header (ADR 0007). --}}
    </div>

    @include('agent::coach._history-panel')
    @include('agent::coach._new-goal-modal')
    @include('agent::coach._share-message-modal')
    @include('agent::coach._footer-script')
</div>
</x-filament-panels::page>
