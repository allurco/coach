{{-- Workspace slim top bar (prototype / ADR 0007): back to Goals + active
     goal name + label chip, with compact thread actions on the right.
     Tools (Budget, Plan, Contacts) live in the tab bar, not here. --}}
<div class="coach-header">
    <button type="button" class="coach-back-btn"
            wire:click="backToGoals"
            aria-label="{{ __('coach.header.back_to_goals') }}"
            title="{{ __('coach.header.back_to_goals') }}">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </button>

    <div class="coach-header-text">
        <span class="coach-title">{{ $this->activeGoal()['name'] ?? 'Coach' }}</span>
        @if ($this->activeGoal())
            <span class="coach-status-label">{{ $this->activeGoal()['label'] }}</span>
        @endif
    </div>

    @if ($this->activeGoal())
        <div class="coach-header-actions">
            <button type="button" class="coach-icon-btn"
                    wire:click="newConversation"
                    aria-label="{{ __('coach.header.new_thread') }}"
                    title="{{ __('coach.header.new_thread') }}">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button type="button" class="coach-icon-btn"
                    wire:click="toggleHistory"
                    aria-label="{{ __('coach.header.history') }}"
                    title="{{ __('coach.header.history') }}">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
            </button>
        </div>
    @endif
</div>
