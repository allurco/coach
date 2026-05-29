<x-filament-panels::page>
{{-- Coach page — the Workspace shell. With no active Goal it shows the
     Goals start screen; once a Goal is selected it shows that Goal's
     Workspace (chat + tools). State synced to ?goal= (ADR 0007). --}}
<div class="coach-root"
     x-data="{
        sidebarOpen: false,
        moreOpen: false,
        /* Alpine owns the rail's VISUAL open/close so content stays present
           during the hide animation; Livewire's activeTool (content + ?tool=)
           is cleared only AFTER the animation finishes. railOpen is a constant
           in x-data (no server interpolation, so morphs don't re-init it) and
           seeded once from the server in x-init. (ADR 0007) */
        railOpen: false,
        toolOpen(key) {
            if (this.$wire.activeTool === key) { this.toolClose(); return; }
            this.railOpen = true;
            this.moreOpen = false;
            this.$wire.openTool(key);
        },
        toolClose() {
            if (! this.railOpen) return;
            this.railOpen = false;
            setTimeout(() => this.$wire.closeTool(), 320);
        },
     }"
     x-init="railOpen = ($wire.activeTool !== null)"
     x-effect="document.body.classList.toggle('coach-overlay-locked', sidebarOpen || moreOpen)"
     @keydown.escape.window="sidebarOpen = false; moreOpen = false; toolClose()">
    @if ($activeGoalId === null)
        @include('agent::coach._goals-screen')
    @else
        {{-- .is-workspace lets CSS hide Filament's top nav (logo + avatar) in
             the Workspace, where the slim goal bar takes over. The Filament
             top nav stays on the Goals start screen (its home top bar).
             wire:transition shares a view-transition-name with the Goals
             screen so the drill-in slides (native View Transitions, ADR 0007). --}}
        <div class="coach-page is-workspace" wire:transition="coach-screen">

            <div class="coach-shell">
                @include('agent::coach._sidebar')

                <div class="coach-main">
                    {{-- TODO(tips): the in-screen tip banner is hidden pending a
                         new home — it doesn't fit the slim Workspace. Candidate:
                         deliver tips as push notifications (PWA) instead of an
                         in-chat banner. See memory project-tips-need-a-home. --}}
                    @include('agent::coach._header')
                    @include('agent::coach._chat-thread')
                    @include('agent::coach._composer')
                    @include('agent::coach._tab-bar')
                </div>

                {{-- Tool rail: a third column inside the shell on desktop (the
                     chat shrinks to make room); a full-screen overlay on mobile.
                     ADR 0007. --}}
                @include('agent::coach._tool-rail')
            </div>

            @include('agent::coach._more-sheet')
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
