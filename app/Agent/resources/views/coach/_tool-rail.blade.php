{{-- Unified Tool rail — renders the active Tool's component as content,
     driven by the Livewire $activeTool (?tool= synced). Right rail on
     desktop, full-screen swap on mobile (shared .plan-drawer styles).
     ADR 0007. --}}
@php $tool = $activeTool ? collect($this->workspaceTools())->firstWhere('key', $activeTool) : null; @endphp

<div class="plan-overlay tool-rail-overlay {{ $activeTool ? 'is-open' : '' }}"
     @if (! $activeTool) style="display:none" @endif
     wire:click="closeTool"></div>

<aside class="plan-drawer tool-rail {{ $activeTool ? 'is-open' : '' }}">
    @if ($tool)
        <div class="tool-rail-bar">
            <span class="tool-rail-title">{{ $tool['label'] }}</span>
            <button type="button" class="plan-close-btn" wire:click="closeTool" aria-label="{{ __('coach.tabbar.chat') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="tool-rail-body">
            @switch($activeTool)
                @case('plan')
                    <livewire:plan-flyout :active-goal-id="$activeGoalId" :as-drawer="false" :key="'rail-plan-'.$activeGoalId" />
                    @break
                @case('contacts')
                    <livewire:contacts-tool :key="'rail-contacts'" />
                    @break
                @case('budget')
                    <livewire:budget-tool :key="'rail-budget-'.$activeGoalId" />
                    @break
            @endswitch
        </div>
    @endif
</aside>
