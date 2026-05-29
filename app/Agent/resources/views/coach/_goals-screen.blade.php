{{-- Goals start screen — the Workspace's home. Pick a Goal to drill into
     its Workspace; "nova" creates one. Proper goal cards (ADR 0007 /
     design pass). --}}
<div class="goals-screen">
    <header class="goals-head">
        <h1 class="goals-title">{{ __('coach.goals_screen.title') }}</h1>
        <button type="button" class="goals-new-btn" wire:click="openNewGoal" title="{{ __('coach.sidebar.new_goal') }}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            {{ __('coach.sidebar.new') }}
        </button>
    </header>

    <div class="goals-list">
        @forelse ($goals as $goal)
            <button type="button"
                    class="goal-card"
                    wire:key="goal-card-{{ $goal['id'] }}"
                    wire:click="setActiveGoal({{ $goal['id'] }})">
                <span class="goal-card-dot goal-dot--{{ $goal['label'] }}" aria-hidden="true"></span>
                <span class="goal-card-text">
                    <span class="goal-card-name">{{ $goal['name'] }}</span>
                    <span class="goal-card-meta">
                        @if ($goal['last_activity_label'])
                            {{ $goal['last_activity_label'] }}
                        @else
                            {{ __('coach.sidebar.no_activity') }}
                        @endif
                        <span class="goal-card-label">{{ $goal['label'] }}</span>
                    </span>
                </span>
                <svg class="goal-card-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        @empty
            <div class="goals-empty">{{ __('coach.sidebar.empty') }}</div>
        @endforelse
    </div>
</div>
