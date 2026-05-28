{{-- Goals start screen — the Workspace's home. Pick a Goal to drill into
     its Workspace; "novo" creates one. Reuses the sidebar's conv-item
     styling for the cards (ADR 0007). --}}
<div class="goals-screen" style="max-width: 680px; margin: 0 auto; padding: 28px 20px;">
    <div class="sidebar-header">
        <div class="sidebar-title">{{ __('coach.goals_screen.title') }}</div>
        <button type="button" class="new-chat-btn" wire:click="openNewGoal" title="{{ __('coach.sidebar.new_goal') }}">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            {{ __('coach.sidebar.new') }}
        </button>
    </div>

    <div class="conv-list">
        @forelse ($goals as $goal)
            <button type="button"
                    class="conv-item"
                    wire:key="goal-card-{{ $goal['id'] }}"
                    wire:click="setActiveGoal({{ $goal['id'] }})">
                <div class="conv-item-title">{{ $goal['name'] }}</div>
                <div class="conv-item-time">
                    @if ($goal['last_activity_label'])
                        {{ $goal['last_activity_label'] }}
                    @else
                        {{ __('coach.sidebar.no_activity') }}
                    @endif
                    · {{ $goal['label'] }}
                </div>
            </button>
        @empty
            <div class="conv-empty">{{ __('coach.sidebar.empty') }}</div>
        @endforelse
    </div>
</div>
