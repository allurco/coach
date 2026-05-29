{{-- Unified Tool rail — renders the active Tool's component as content,
     driven by the Livewire $activeTool (?tool= synced). Right rail on
     desktop, full-screen swap on mobile (shared .plan-drawer styles).
     ADR 0007. --}}
@php $tool = $activeTool ? collect($this->workspaceTools())->firstWhere('key', $activeTool) : null; @endphp

<div class="plan-overlay tool-rail-overlay"
     :class="railOpen ? 'is-open' : ''"
     x-show="railOpen"
     x-transition.opacity.duration.200ms
     @click="toolClose()"
     style="display:none"></div>

<aside class="plan-drawer tool-rail" :class="railOpen ? 'is-open' : ''">
    {{-- Fixed-width inner panel. On desktop it's anchored to the right edge and
         slides in (translateX) while the grid column grows the chat shrinks —
         the panel reads as a finished surface sliding in, not content squishing
         into a growing column. On mobile it just fills the rail. ADR 0007. --}}
    <div class="tool-rail-inner">
        @if ($tool)
            <div class="tool-rail-bar">
                <span class="tool-rail-title">{{ $tool['label'] }}</span>
                <button type="button" class="plan-close-btn" @click="toolClose()" aria-label="{{ __('coach.tabbar.chat') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Keyed per Tool so switching Tools replaces the node and re-runs
                 the cross-fade (tool-fade) instead of morphing in place. --}}
            <div class="tool-rail-body" wire:key="tool-body-{{ $activeTool }}">
                @switch($activeTool)
                    @case('plan')
                        <livewire:plan-flyout :active-goal-id="$activeGoalId" :as-drawer="false" :show-header="false" :key="'rail-plan-'.$activeGoalId" />
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
    </div>
</aside>
