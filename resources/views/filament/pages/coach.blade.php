<x-filament-panels::page>

    <div class="coach-page"
         x-data="{ planOpen: false, sidebarOpen: false }"
         @keydown.escape.window="planOpen = false; sidebarOpen = false">
        <div class="coach-shell">

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

            {{-- Main --}}
            <div class="coach-main">

                {{-- Header --}}
                <div class="coach-header">
                    <button type="button" class="sidebar-toggle-btn"
                            @click="sidebarOpen = true"
                            aria-label="Abrir conversas">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="15" y2="18"/></svg>
                    </button>

                    <div class="coach-header-text">
                        @php
                            $activeGoal = collect($goals)->firstWhere('id', $activeGoalId);
                        @endphp
                        <div class="coach-title">
                            {{ $activeGoal['name'] ?? 'Coach' }}
                            @if ($conversationId)
                                <span class="pulse-dot"></span>
                            @endif
                        </div>
                        <div class="coach-status">
                            @if ($activeGoal)
                                <span class="coach-status-label">{{ $activeGoal['label'] }}</span>
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

                    <button type="button" class="plan-toggle-btn" @click="planOpen = true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-4"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6"/><path d="M9 16h6"/></svg>
                        Plano
                        @php
                            $pendingCount = collect($planActions)->whereIn('status', ['pendente','em_andamento'])->count();
                        @endphp
                        @if ($pendingCount > 0)
                            <span class="plan-badge">{{ $pendingCount }}</span>
                        @endif
                    </button>
                </div>

                {{-- Thread --}}
                <div class="coach-thread"
                     x-data="{}"
                     x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                     x-effect="$wire.messages; $nextTick(() => $el.scrollTop = $el.scrollHeight)">

                    @if (empty($messages))
                        @php
                            $hasPlan = ! empty($planActions);
                            $suggestionsKey = $hasPlan ? 'coach.suggestions_active' : 'coach.suggestions';
                        @endphp
                        <div class="msg coach-greeting-msg">
                            <div class="msg-avatar coach">C</div>
                            <div class="msg-body">
                                <div class="msg-name">Coach</div>
                                <div class="msg-content greeting-content">
                                    <p class="greeting-line-1">{{ __('coach.greeting_first') }}</p>
                                    <p class="greeting-line-2">{{ __('coach.greeting_second') }}</p>
                                </div>

                                <div class="quick-replies">
                                    @foreach (__($suggestionsKey) as $s)
                                        <button type="button"
                                                class="quick-reply"
                                                data-prompt="{{ $s['prompt'] }}">
                                            {{ $s['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        @foreach ($messages as $msg)
                            @if ($msg['role'] === 'user')
                                <div class="msg">
                                    <div class="msg-avatar user">R</div>
                                    <div class="msg-body">
                                        <div class="msg-name">
                                            Você
                                            <span class="time">{{ $msg['time'] }}</span>
                                        </div>
                                        <div class="msg-content">{{ $msg['content'] }}</div>
                                        @if (! empty($msg['attachments']))
                                            <div class="mt-1">
                                                @foreach ($msg['attachments'] as $name)
                                                    <span class="attach-pill">
                                                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                                        {{ $name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @elseif ($msg['role'] === 'assistant')
                                <div class="msg">
                                    <div class="msg-avatar coach">C</div>
                                    <div class="msg-body">
                                        <div class="msg-name">
                                            Coach
                                            <span class="time">{{ $msg['time'] }}</span>
                                        </div>
                                        <div class="msg-content prose-coach">
                                            {!! $msg['content_html'] ?? e($msg['content']) !!}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="msg">
                                    <div class="msg-avatar" style="background: #ef4444; color: white;">!</div>
                                    <div class="msg-body">
                                        <div class="msg-name" style="color: #ef4444;">Erro</div>
                                        <div class="msg-content" style="color: #ef4444;">{{ $msg['content'] }}</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @if ($thinking)
                            <div class="msg msg-thinking">
                                <div class="msg-avatar coach">C</div>
                                <div class="msg-body">
                                    <div class="msg-name">Coach</div>
                                    <div class="msg-content"
                                         x-data="{}"
                                         x-init="$nextTick(() => $el.closest('.coach-thread').scrollTop = $el.closest('.coach-thread').scrollHeight)">
                                        <span class="streaming-content"
                                              wire:stream="coach-stream"></span>
                                        <span class="thinking-dots" aria-hidden="true">
                                            <span></span><span></span><span></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Composer --}}
                <form wire:submit="send">
                    <div class="composer">
                        {{ $this->form }}

                        <div class="composer-actions">
                            <span class="composer-hint">
                                <kbd>↵</kbd> envia · <kbd>shift</kbd>+<kbd>↵</kbd> nova linha
                            </span>
                            <button type="submit" class="send-btn" {{ $thinking ? 'disabled' : '' }}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                Enviar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Plan flyout drawer --}}
        <div class="plan-overlay"
             x-show="planOpen"
             x-transition.opacity.duration.150ms
             @click="planOpen = false"
             style="display: none;"></div>

        <aside class="plan-drawer"
               :class="planOpen ? 'is-open' : ''">

            <div class="plan-drawer-header">
                <div>
                    <div class="plan-drawer-title">Plano</div>
                    <div class="plan-drawer-sub">{{ trans_choice('coach.plan.count', count($planActions), ['count' => count($planActions)]) }}</div>
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
                @forelse ($planActions as $a)
                    @php
                        $detailFields = [
                            'description',
                            'importance',
                            'difficulty',
                            'snooze_until',
                            'result_notes',
                            'completed_at',
                            'attachments',
                        ];

                        $hasDetails = array_key_exists('has_details', $a)
                            ? (bool) $a['has_details']
                            : collect($detailFields)->contains(fn ($field) => ! empty($a[$field]));
                    @endphp
                    <div class="plan-item {{ $hasDetails ? 'has-details' : '' }}"
                         x-data="{ menu: false, open: false }"
                         @click.away="menu = false">
                        <div class="plan-item-row">
                            <div class="plan-item-main"
                                 @if ($hasDetails) @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" tabindex="0" role="button" :aria-expanded="open" style="cursor: pointer;" @endif>
                                @if ($hasDetails)
                                    <span class="plan-item-chevron" :class="open ? 'is-open' : ''" aria-hidden="true">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                                    </span>
                                @endif
                                <div class="plan-item-main-text">
                                    <div class="plan-item-title {{ $a['is_overdue'] ? 'overdue' : '' }}">{{ $a['title'] }}</div>
                                    <div class="plan-item-meta">
                                        <div class="plan-item-badges">
                                            <span class="plan-badge-cat plan-cat-{{ $a['category'] }}">{{ $a['category'] }}</span>
                                            <span class="plan-badge-pri plan-pri-{{ $a['priority'] }}">{{ $a['priority'] }}</span>
                                        </div>
                                        @if ($a['deadline'])
                                            <span class="plan-deadline {{ $a['is_overdue'] ? 'overdue' : ($a['is_due_soon'] ? 'soon' : '') }}">
                                                {{ $a['deadline'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($a['status'] !== 'concluido')
                                <div class="plan-item-actions" @click.stop>
                                    <button type="button" class="plan-action-btn done"
                                            wire:click="startCompleteAction({{ $a['id'] }})"
                                            title="{{ __('coach.plan.mark_done') }}">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                    <button type="button" class="plan-action-btn snooze" @click="menu = !menu" title="{{ __('coach.plan.snooze') }}">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </button>
                                    <div x-show="menu" x-transition class="plan-snooze-menu" style="display: none;">
                                        @foreach (__('coach.plan.snooze_options') as $key => $label)
                                            <button type="button" wire:click="snoozeAction({{ $a['id'] }}, '{{ $key }}')" @click="menu = false">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="plan-item-done-mark">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                            @endif
                        </div>

                        @if ($hasDetails)
                            <div class="plan-item-details" x-show="open" x-transition style="display: none;">
                                @if (! empty($a['description']))
                                    <div class="plan-item-detail-row">
                                        <div class="plan-item-detail-label">{{ __('coach.plan.details.description') }}</div>
                                        <div class="plan-item-detail-value">{{ $a['description'] }}</div>
                                    </div>
                                @endif
                                @if (! empty($a['importance']) || ! empty($a['difficulty']))
                                    <div class="plan-item-detail-row plan-item-detail-row-inline">
                                        @if (! empty($a['importance']))
                                            <div>
                                                <div class="plan-item-detail-label">{{ __('coach.plan.details.importance') }}</div>
                                                <div class="plan-item-detail-value">{{ $a['importance'] }}</div>
                                            </div>
                                        @endif
                                        @if (! empty($a['difficulty']))
                                            <div>
                                                <div class="plan-item-detail-label">{{ __('coach.plan.details.difficulty') }}</div>
                                                <div class="plan-item-detail-value">{{ $a['difficulty'] }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                @if (! empty($a['snooze_until']))
                                    <div class="plan-item-detail-row">
                                        <div class="plan-item-detail-value">
                                            {{ __('coach.plan.details.snoozed_until', ['date' => $a['snooze_until']]) }}
                                        </div>
                                    </div>
                                @endif
                                @if (! empty($a['completed_at']))
                                    <div class="plan-item-detail-row">
                                        <div class="plan-item-detail-value">
                                            {{ __('coach.plan.details.completed_at', ['date' => $a['completed_at']]) }}
                                        </div>
                                    </div>
                                @endif
                                @if (! empty($a['result_notes']))
                                    <div class="plan-item-detail-row">
                                        <div class="plan-item-detail-label">{{ __('coach.plan.details.result_notes') }}</div>
                                        <div class="plan-item-detail-value">{{ $a['result_notes'] }}</div>
                                    </div>
                                @endif
                                @if (! empty($a['attachments']))
                                    <div class="plan-item-detail-row">
                                        <div class="plan-item-detail-label">{{ __('coach.plan.details.attachments') }}</div>
                                        <ul class="plan-item-attachments">
                                            @foreach ($a['attachments'] as $att)
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
                        {{ __('coach.plan.empty', ['status' => $planFilter !== 'todas' ? $planFilter : '']) }}
                        @if ($planFilter !== 'todas')
                            <button type="button" wire:click="setPlanFilter('todas')" class="plan-empty-link">{{ __('coach.plan.view_all') }}</button>
                        @endif
                    </div>
                @endforelse
            </div>
        </aside>
    </div>

    {{-- History panel: older conversations of the active goal --}}
    @if ($historyOpen)
        <div class="complete-modal-overlay" wire:click="toggleHistory">
            <div class="history-panel" @click.stop wire:click.stop>
                <div class="history-panel-header">
                    <div class="history-panel-title">{{ __('coach.history_panel.title') }}</div>
                    <button type="button" class="plan-close-btn" wire:click="toggleHistory">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="history-panel-list">
                    @forelse ($goalHistory as $conv)
                        <button type="button" class="history-item"
                                wire:click="loadConversation('{{ $conv['id'] }}')"
                                @click="$wire.toggleHistory()">
                            <div class="history-item-title">{{ $conv['title'] }}</div>
                            <div class="history-item-time">{{ $conv['updated_label'] }}</div>
                        </button>
                    @empty
                        <div class="history-empty">{{ __('coach.history_panel.empty') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- New goal modal --}}
    @if ($newGoalOpen)
        <div class="complete-modal-overlay" wire:click="cancelNewGoal">
            <div class="complete-modal" @click.stop wire:click.stop style="max-width: 440px;">
                <div class="complete-modal-header">
                    <div>
                        <div class="complete-modal-title">{{ __('coach.new_goal_modal.title') }}</div>
                    </div>
                </div>

                <label class="complete-modal-label" for="newGoalName">{{ __('coach.new_goal_modal.name_label') }}</label>
                <input type="text"
                       id="newGoalName"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="newGoalName"
                       placeholder="{{ __('coach.new_goal_modal.name_placeholder') }}"
                       autofocus
                       wire:keydown.enter="createGoal"
                       wire:keydown.escape="cancelNewGoal">

                <label class="complete-modal-label" for="newGoalLabel">{{ __('coach.new_goal_modal.label_label') }}</label>
                <select id="newGoalLabel"
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
                    <button type="button" class="complete-modal-confirm" wire:click="createGoal">
                        {{ __('coach.new_goal_modal.create') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($completingActionId !== null)
        <div class="complete-modal-overlay" wire:click="cancelCompleteAction"
             wire:key="complete-modal-{{ $completingActionId }}">
            <div class="complete-modal" @click.stop wire:click.stop>
                <div class="complete-modal-header">
                    <span class="complete-modal-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <div>
                        <div class="complete-modal-title">{{ __('coach.complete_modal.title') }}</div>
                        <div class="complete-modal-subtitle">{{ $completingActionTitle }}</div>
                    </div>
                </div>
                <label class="complete-modal-label" for="completingNotes">
                    {{ __('coach.complete_modal.label') }} <span class="complete-modal-optional">{{ __('coach.complete_modal.optional') }}</span>
                </label>
                <textarea id="completingNotes"
                          class="complete-modal-textarea"
                          wire:model="completingNotes"
                          rows="4"
                          placeholder="{{ __('coach.complete_modal.placeholder') }}"
                          autofocus
                          wire:keydown.escape="cancelCompleteAction"></textarea>
                <div class="complete-modal-footer">
                    <button type="button" class="complete-modal-cancel"
                            wire:click="cancelCompleteAction">
                        {{ __('coach.complete_modal.cancel') }}
                    </button>
                    <button type="button" class="complete-modal-confirm"
                            wire:click="confirmCompleteAction">
                        {{ __('coach.complete_modal.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-prompt]');
            if (btn) {
                const prompt = btn.dataset.prompt;
                const ta = document.querySelector('.composer textarea');
                if (ta) {
                    ta.focus();
                    ta.value = prompt;
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }
        });
    </script>
</x-filament-panels::page>
