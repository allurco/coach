{{-- Coach header — sidebar toggle, active goal title + meta, Budget + Plano buttons --}}
<div class="coach-header">
    <button type="button" class="sidebar-toggle-btn"
            @click="sidebarOpen = true"
            aria-label="Abrir conversas">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="15" y2="18"/></svg>
    </button>

    <div class="coach-header-text">
        <div class="coach-title">
            {{ $this->activeGoal()['name'] ?? 'Coach' }}
        </div>
        <div class="coach-status">
            @if ($this->activeGoal())
                <span class="coach-status-label">{{ $this->activeGoal()['label'] }}</span>
                <button type="button" class="coach-mini-btn"
                        wire:click="newConversation"
                        title="{{ __('coach.header.new_thread') }}">
                    + {{ __('coach.header.new_thread') }}
                </button>
                <button type="button" class="coach-mini-btn"
                        wire:click="toggleHistory"
                        title="{{ __('coach.header.history') }}">
                    {{ __('coach.header.history') }}
                </button>
            @else
                pronto pra conversar
            @endif
        </div>
    </div>

    @if ($this->hasBudget())
        <button type="button" class="plan-toggle-btn budget-toggle-btn"
                wire:click="openBudget"
                @click="budgetOpen = true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span class="budget-toggle-label">{{ __('coach.budget_flyout.toggle') }}</span>
        </button>
    @endif

    <button type="button" class="plan-toggle-btn" @click="planOpen = true">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-4"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6"/><path d="M9 16h6"/></svg>
        Plano
        @if ($this->pendingPlanCount() > 0)
            <span class="plan-badge">{{ $this->pendingPlanCount() }}</span>
        @endif
    </button>
</div>
