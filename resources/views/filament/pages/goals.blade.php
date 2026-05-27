<x-filament-panels::page>
{{-- Goals tool page — manage workspaces beyond what the chat sidebar
     offers (archived view, edit name/label, archive/unarchive, reorder,
     stats per goal). See ADR 0004 for layer-owned Filament UI. --}}
<div class="goals-page">
    <div class="goals-actions">
        <button type="button" class="goals-new-btn" wire:click="openNewGoal">
            + {{ __('coach.sidebar.new_goal') }}
        </button>
    </div>

    {{-- Active goals: reorderable, count badge, edit + archive --}}
    <section class="goals-section">
        <h2 class="goals-section-title">{{ __('coach.plan.filters.in_progress') }}</h2>

        @if (empty($activeGoals))
            <div class="goals-empty">{{ __('coach.sidebar.empty') }}</div>
        @else
            <ul class="goals-list">
                @foreach ($activeGoals as $i => $goal)
                    <li class="goal-row" wire:key="goal-{{ $goal['id'] }}">
                        <div class="goal-row-handle">
                            <button type="button"
                                    class="goal-move-btn"
                                    wire:click="moveGoal({{ $goal['id'] }}, 'up')"
                                    @disabled($i === 0)
                                    title="↑">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                            </button>
                            <button type="button"
                                    class="goal-move-btn"
                                    wire:click="moveGoal({{ $goal['id'] }}, 'down')"
                                    @disabled($i === count($activeGoals) - 1)
                                    title="↓">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                        </div>

                        <span class="goal-dot" style="background-color: {{ $goal['color'] ?? '#94a3b8' }};"></span>

                        <div class="goal-row-main">
                            <div class="goal-row-name">{{ $goal['name'] }}</div>
                            <div class="goal-row-meta">
                                <span class="goal-badge-label">{{ $goal['label_display'] }}</span>
                                @if ($goal['open_count'] > 0)
                                    <span class="goal-badge-count">{{ trans_choice('coach.plan.count', $goal['open_count'], ['count' => $goal['open_count']]) }}</span>
                                @endif
                                @if ($goal['last_conv_at'])
                                    <span class="goal-badge-time">{{ \Carbon\Carbon::parse($goal['last_conv_at'])->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="goal-row-actions">
                            <button type="button" class="goal-action-btn" wire:click="startEdit({{ $goal['id'] }})" title="{{ __('coach.plan.details.expand') }}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button type="button" class="goal-action-btn goal-archive-btn"
                                    wire:click="archive({{ $goal['id'] }})"
                                    @disabled(count($activeGoals) <= 1)
                                    title="archive">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Archived goals — no reorder, only unarchive + edit --}}
    @if (! empty($archivedGoals))
        <section class="goals-section goals-section-archived">
            <h2 class="goals-section-title">{{ __('coach.plan.filters.completed') }}</h2>
            <ul class="goals-list">
                @foreach ($archivedGoals as $goal)
                    <li class="goal-row goal-row-archived" wire:key="goal-{{ $goal['id'] }}">
                        <span class="goal-dot" style="background-color: {{ $goal['color'] ?? '#94a3b8' }};"></span>

                        <div class="goal-row-main">
                            <div class="goal-row-name">{{ $goal['name'] }}</div>
                            <div class="goal-row-meta">
                                <span class="goal-badge-label">{{ $goal['label_display'] }}</span>
                                @if ($goal['open_count'] > 0)
                                    <span class="goal-badge-count">{{ trans_choice('coach.plan.count', $goal['open_count'], ['count' => $goal['open_count']]) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="goal-row-actions">
                            <button type="button" class="goal-action-btn" wire:click="startEdit({{ $goal['id'] }})" title="{{ __('coach.plan.details.expand') }}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button type="button" class="goal-action-btn goal-unarchive-btn"
                                    wire:click="unarchive({{ $goal['id'] }})"
                                    title="unarchive">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8"/><rect x="1" y="3" width="22" height="5"/><polyline points="10 12 12 14 14 12"/></svg>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>

{{-- Edit goal modal --}}
@if ($editingGoalId !== null)
    <div class="complete-modal-overlay" wire:click="cancelEdit">
        <div class="complete-modal" @click.stop wire:click.stop style="max-width: 440px;">
            <div class="complete-modal-header">
                <div>
                    <div class="complete-modal-title">{{ __('coach.plan.details.expand') }}</div>
                </div>
            </div>

            <label class="complete-modal-label" for="editGoalName">{{ __('coach.new_goal_modal.name_label') }}</label>
            <input type="text"
                   id="editGoalName"
                   class="complete-modal-textarea"
                   style="min-height: 0; height: 38px; padding: 8px 12px;"
                   wire:model="editName"
                   autofocus
                   wire:keydown.enter="confirmEdit"
                   wire:keydown.escape="cancelEdit">

            <label class="complete-modal-label" for="editGoalLabel">{{ __('coach.new_goal_modal.label_label') }}</label>
            <select id="editGoalLabel"
                    class="complete-modal-textarea"
                    style="min-height: 0; height: 38px; padding: 0 12px;"
                    wire:model="editLabel">
                @foreach (App\Models\Goal::LABELS as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </select>

            <div class="complete-modal-footer">
                <button type="button" class="complete-modal-cancel" wire:click="cancelEdit">
                    {{ __('coach.new_goal_modal.cancel') }}
                </button>
                <button type="button"
                        class="complete-modal-confirm"
                        wire:click="confirmEdit"
                        wire:loading.attr="disabled"
                        wire:target="confirmEdit">
                    <span wire:loading.remove wire:target="confirmEdit">
                        {{ __('coach.complete_modal.confirm') }}
                    </span>
                    <span wire:loading wire:target="confirmEdit" class="btn-spinner"></span>
                </button>
            </div>
        </div>
    </div>
@endif

{{-- New goal modal (same pattern as the chat sidebar's modal) --}}
@if ($newGoalOpen)
    <div class="complete-modal-overlay" wire:click="cancelNewGoal">
        <div class="complete-modal" @click.stop wire:click.stop style="max-width: 440px;">
            <div class="complete-modal-header">
                <div>
                    <div class="complete-modal-title">{{ __('coach.new_goal_modal.title') }}</div>
                </div>
            </div>

            <label class="complete-modal-label" for="newGoalNameTool">{{ __('coach.new_goal_modal.name_label') }}</label>
            <input type="text"
                   id="newGoalNameTool"
                   class="complete-modal-textarea"
                   style="min-height: 0; height: 38px; padding: 8px 12px;"
                   wire:model="newGoalName"
                   placeholder="{{ __('coach.new_goal_modal.name_placeholder') }}"
                   autofocus
                   wire:keydown.enter="createGoal"
                   wire:keydown.escape="cancelNewGoal">

            <label class="complete-modal-label" for="newGoalLabelTool">{{ __('coach.new_goal_modal.label_label') }}</label>
            <select id="newGoalLabelTool"
                    class="complete-modal-textarea"
                    style="min-height: 0; height: 38px; padding: 0 12px;"
                    wire:model="newGoalLabel">
                @foreach (App\Models\Goal::LABELS as $key => $name)
                    <option value="{{ $key }}">{{ $name }}</option>
                @endforeach
            </select>

            <div class="complete-modal-footer">
                <button type="button" class="complete-modal-cancel" wire:click="cancelNewGoal">
                    {{ __('coach.new_goal_modal.cancel') }}
                </button>
                <button type="button"
                        class="complete-modal-confirm"
                        wire:click="createGoal">
                    {{ __('coach.new_goal_modal.create') }}
                </button>
            </div>
        </div>
    </div>
@endif
</x-filament-panels::page>
