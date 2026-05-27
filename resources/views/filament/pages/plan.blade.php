<x-filament-panels::page>
{{-- Plan tool page — full-screen view of actions across goals. The
     PlanFlyout Livewire component does the rendering (same one the chat
     sidebar uses, per ADR 0005); this page wraps it with a goal picker
     for the cross-goal filtering case. --}}
<div class="plan-page-shell">
    @if (! empty($goalOptions))
        <div class="plan-page-picker">
            <label for="plan-goal-picker" class="plan-page-picker-label">
                {{ __('coach.sidebar.title') }}
            </label>
            <select id="plan-goal-picker"
                    wire:model.live="selectedGoalId"
                    class="plan-page-picker-select">
                <option value="">— {{ __('coach.plan.filters.all') }} —</option>
                @foreach ($goalOptions as $goal)
                    <option value="{{ $goal['id'] }}">{{ $goal['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <livewire:plan-flyout
        :active-goal-id="$selectedGoalId"
        :show-goal-picker="false"
        :as-drawer="false" />
</div>
</x-filament-panels::page>
