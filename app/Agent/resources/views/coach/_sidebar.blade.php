{{-- Sidebar with chat history. Visible by default on desktop;
     turns into a slide-in drawer on mobile. --}}
<div class="sidebar-overlay"
     x-show="sidebarOpen"
     x-transition.opacity
     @click="sidebarOpen = false"
     style="display: none;"></div>

<aside class="coach-sidebar"
       :class="sidebarOpen ? 'is-open' : ''">
    <div class="sidebar-header">
        <div class="sidebar-title">{{ __('coach.sidebar.title') }}</div>
        <button type="button" class="new-chat-btn"
                wire:click="openNewGoal"
                @click="sidebarOpen = false"
                title="{{ __('coach.sidebar.new_goal') }}">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            {{ __('coach.sidebar.new') }}
        </button>
    </div>

    <div class="conv-list">
        @forelse ($goals as $goal)
            <button type="button"
                    class="conv-item {{ $activeGoalId === $goal['id'] ? 'active' : '' }}"
                    wire:click="setActiveGoal({{ $goal['id'] }})"
                    @click="sidebarOpen = false">
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
            <div class="conv-empty">
                {{ __('coach.sidebar.empty') }}
            </div>
        @endforelse
    </div>
</aside>
