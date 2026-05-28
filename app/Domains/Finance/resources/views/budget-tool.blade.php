{{-- Budget Tool — the header toggle button plus the flyout drawer and
     share modal (teleported to <body> so they stack above the app).
     Open/close is local Alpine state; data is hydrated by openBudget().
     display:contents keeps the toggle button in the header's flex row. --}}
<div x-data="{ budgetOpen: false }"
     x-effect="document.body.classList.toggle('coach-overlay-locked', budgetOpen)"
     @keydown.escape.window="budgetOpen = false"
     style="display: contents;">

    @if ($this->hasBudget())
        <button type="button" class="plan-toggle-btn budget-toggle-btn"
                wire:click="openBudget"
                @click="budgetOpen = true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span class="budget-toggle-label">{{ __('finance::budget_flyout.toggle') }}</span>
        </button>
    @endif

    <template x-teleport="body">
        <div>
            @include('finance::_budget-flyout')
            @include('finance::_budget-share-modal')
        </div>
    </template>
</div>
