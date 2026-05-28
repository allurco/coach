<x-filament-panels::page>
{{-- Coach page — the Workspace shell. With no active Goal it shows the
     Goals start screen; once a Goal is selected it shows that Goal's
     Workspace (chat + tools). State synced to ?goal= (ADR 0007). --}}
<div class="coach-root"
     x-data="{ planOpen: false, sidebarOpen: false }"
     x-effect="document.body.classList.toggle('coach-overlay-locked', planOpen || sidebarOpen)"
     @keydown.escape.window="planOpen = false; sidebarOpen = false">
    @if ($activeGoalId === null)
        @include('agent::coach._goals-screen')
    @else
        <div class="coach-page">

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
        </div>
    @endif

    {{-- Shared across both screens; each is gated by its own state so it
         stays inert on the Goals start screen. --}}
    @include('agent::coach._history-panel')
    @include('agent::coach._new-goal-modal')
    @include('agent::coach._share-message-modal')
    @include('agent::coach._footer-script')
</div>
</x-filament-panels::page>
