{{-- Plano flyout drawer: filters + per-action rows with deadline column + actions --}}
<div class="plan-overlay"
     x-show="planOpen"
     x-transition.opacity.duration.150ms
     @click="planOpen = false"
     style="display: none;"></div>

<aside class="plan-drawer"
       :class="planOpen ? 'is-open' : ''">

    <div class="plan-drawer-header drawer-editorial-header">
        <div>
            <div class="drawer-eyebrow">{{ __('coach.plan_flyout.eyebrow') }}</div>
            <div class="drawer-headline">{{ trans_choice('coach.plan.count', count($planActions), ['count' => count($planActions)]) }}</div>
        </div>
        <button type="button" class="plan-close-btn" @click="planOpen = false">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="plan-filters">
        @foreach (__('coach.plan.filters') as $key => $label)
            <button type="button"
                    class="plan-filter {{ $planFilter === $key ? 'active' : '' }}"
                    wire:click="setPlanFilter('{{ $key }}')">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="plan-list">
        @forelse ($planActions as $action)
            <div class="plan-item {{ $action['has_details'] ? 'has-details' : '' }} {{ $action['is_overdue'] ? 'is-overdue' : '' }}"
                 x-data="{ menu: false, open: false }"
                 @click.away="menu = false">
                <div class="plan-item-row">
                    <div class="plan-item-main"
                         @if ($action['has_details']) @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" tabindex="0" role="button" :aria-expanded="open" style="cursor: pointer;" @endif>
                        @if ($action['has_details'])
                            <span class="plan-item-chevron" :class="open ? 'is-open' : ''" aria-hidden="true">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        @endif
                        <div class="plan-item-main-text">
                            <div class="plan-item-title {{ $action['is_overdue'] ? 'overdue' : '' }}">{{ $action['title'] }}</div>
                            <div class="plan-item-meta">
                                <span class="plan-badge-cat plan-cat-{{ $action['category'] }}">{{ $action['category'] }}</span>
                                <span class="plan-badge-pri plan-pri-{{ $action['priority'] }}">{{ $action['priority'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Deadline in its own grid column so dates align vertically across items regardless of how badges wrap. --}}
                    <div class="plan-item-deadline-col">
                        @if ($action['deadline'])
                            <span class="plan-deadline {{ $action['is_overdue'] ? 'overdue' : ($action['is_due_soon'] ? 'soon' : '') }}">
                                {{ $action['deadline'] }}
                            </span>
                        @endif
                    </div>

                    @if ($action['status'] !== 'concluido')
                        <div class="plan-item-actions" @click.stop>
                            <button type="button" class="plan-action-btn done"
                                    wire:click="startCompleteAction({{ $action['id'] }})"
                                    title="{{ __('coach.plan.mark_done') }}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                            <button type="button" class="plan-action-btn snooze" @click="menu = !menu" title="{{ __('coach.plan.snooze') }}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </button>
                            <div x-show="menu" x-transition class="plan-snooze-menu" style="display: none;">
                                @foreach (__('coach.plan.snooze_options') as $key => $label)
                                    <button type="button" wire:click="snoozeAction({{ $action['id'] }}, '{{ $key }}')" @click="menu = false">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="plan-item-done-mark">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    @endif
                </div>

                @if ($action['has_details'])
                    <div class="plan-item-details" x-show="open" x-transition style="display: none;">
                        @if (! empty($action['description']))
                            <div class="plan-item-detail-row">
                                <div class="plan-item-detail-label">{{ __('coach.plan.details.description') }}</div>
                                <div class="plan-item-detail-value">{{ $action['description'] }}</div>
                            </div>
                        @endif
                        @if (! empty($action['importance']) || ! empty($action['difficulty']))
                            <div class="plan-item-detail-row plan-item-detail-row-inline">
                                @if (! empty($action['importance']))
                                    <div>
                                        <div class="plan-item-detail-label">{{ __('coach.plan.details.importance') }}</div>
                                        <div class="plan-item-detail-value">{{ $action['importance'] }}</div>
                                    </div>
                                @endif
                                @if (! empty($action['difficulty']))
                                    <div>
                                        <div class="plan-item-detail-label">{{ __('coach.plan.details.difficulty') }}</div>
                                        <div class="plan-item-detail-value">{{ $action['difficulty'] }}</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if (! empty($action['snooze_until']))
                            <div class="plan-item-detail-row">
                                <div class="plan-item-detail-value">
                                    {{ __('coach.plan.details.snoozed_until', ['date' => $action['snooze_until']]) }}
                                </div>
                            </div>
                        @endif
                        @if (! empty($action['completed_at']))
                            <div class="plan-item-detail-row">
                                <div class="plan-item-detail-value">
                                    {{ __('coach.plan.details.completed_at', ['date' => $action['completed_at']]) }}
                                </div>
                            </div>
                        @endif
                        @if (! empty($action['result_notes']))
                            <div class="plan-item-detail-row">
                                <div class="plan-item-detail-label">{{ __('coach.plan.details.result_notes') }}</div>
                                <div class="plan-item-detail-value">{{ $action['result_notes'] }}</div>
                            </div>
                        @endif
                        @if (! empty($action['attachments']))
                            <div class="plan-item-detail-row">
                                <div class="plan-item-detail-label">{{ __('coach.plan.details.attachments') }}</div>
                                <ul class="plan-item-attachments">
                                    @foreach ($action['attachments'] as $att)
                                        <li>{{ $att['name'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="plan-empty">
                <div class="plan-empty-icon" aria-hidden="true">
                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M9 11h6"/><path d="M9 15h4"/><path d="M8 4V2.5"/><path d="M16 4V2.5"/></svg>
                </div>
                <div class="plan-empty-copy">
                    {{ __('coach.plan.empty_'.($planFilter !== 'todas' ? $planFilter : 'todas')) }}
                </div>
                @if ($planFilter !== 'todas')
                    <button type="button" wire:click="setPlanFilter('todas')" class="plan-empty-link">{{ __('coach.plan.view_all') }}</button>
                @endif
            </div>
        @endforelse
    </div>
</aside>
