<x-filament-panels::page>

    <div class="coach-page"
         x-data="{ planOpen: false, sidebarOpen: false, budgetOpen: false }"
         x-effect="document.body.classList.toggle('coach-overlay-locked', planOpen || sidebarOpen || budgetOpen)"
         @keydown.escape.window="planOpen = false; sidebarOpen = false; budgetOpen = false">
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

                {{-- Tip banner (one nudge max, dismissable, fires on click) --}}
                @if ($this->currentTip())
                    <div class="coach-tip" role="status" aria-live="polite">
                        <button type="button"
                                class="coach-tip-action"
                                wire:click="clickTip('{{ $this->currentTip()->id() }}')">
                            <span class="coach-tip-spark">💡</span>
                            <span class="coach-tip-title">{{ $this->currentTip()->title() }}</span>
                        </button>
                        <button type="button"
                                class="coach-tip-dismiss"
                                wire:click="dismissTip('{{ $this->currentTip()->id() }}')"
                                aria-label="{{ __('coach.tips.dismiss_label') }}">×</button>
                    </div>
                @endif

                {{-- Header --}}
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

                {{-- Thread --}}
                <div class="coach-thread"
                     x-data="{}"
                     x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                     x-effect="$wire.messages; $nextTick(() => $el.scrollTop = $el.scrollHeight)">

                    @if (empty($messages))
                        <div class="msg coach-greeting-msg">
                            <div class="msg-avatar coach">C</div>
                            <div class="msg-body">
                                <div class="msg-name">Coach</div>
                                <div class="msg-content greeting-content">
                                    <p class="greeting-line-1">{{ $this->userFirstName() !== '' ? __('coach.greeting_first', ['name' => $this->userFirstName()]) : __('coach.greeting_first_anon') }}</p>
                                    <p class="greeting-line-2">{{ __('coach.greeting_second') }}</p>
                                </div>

                                @if ($this->isFirstTimer())
                                    <div class="welcome-cards">
                                        <div class="welcome-cards-label">{{ __('coach.welcome.how_label') }}</div>
                                        <div class="welcome-cards-grid">
                                            @foreach (__('coach.welcome.concepts') as $concept)
                                                <div class="welcome-card">
                                                    <span class="welcome-card-icon" aria-hidden="true">{{ $concept['icon'] }}</span>
                                                    <div class="welcome-card-text">
                                                        <div class="welcome-card-title">{{ $concept['title'] }}</div>
                                                        <div class="welcome-card-body">{{ $concept['body'] }}</div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="quick-replies">
                                    @foreach (__($this->suggestionsKey()) as $s)
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
                        @foreach ($messages as $index => $msg)
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
                                            <button type="button"
                                                    class="msg-share-btn"
                                                    wire:click="openShareModal({{ $index }})"
                                                    title="{{ __('coach.share_modal.icon_label') }}"
                                                    aria-label="{{ __('coach.share_modal.icon_label') }}">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                            </button>
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
                                    {{-- Single-line markup is intentional: msg-content carries
                                         white-space: pre-wrap (so streamed LLM text keeps its
                                         own newlines), which would otherwise render the indented
                                         child spans as empty lines and inflate the bubble. --}}
                                    <div class="msg-content" x-data="{}" x-init="$nextTick(() => $el.closest('.coach-thread').scrollTop = $el.closest('.coach-thread').scrollHeight)"><span class="streaming-content" wire:stream="coach-stream"></span><span class="thinking-dots" aria-hidden="true"><span></span><span></span><span></span></span></div>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- Composer --}}
                {{-- Optimistic user-message insert: render the message in the
                     thread the moment Send is clicked, BEFORE the Livewire
                     round-trip. The matching server-rendered .msg arrives
                     ~150-300ms later and a Livewire morph hook removes the
                     optimistic placeholder, so there's no duplicate. --}}
                <form wire:submit="send"
                      @submit="
                          const ta = $el.querySelector('textarea');
                          const text = (ta?.value || '').trim();
                          if (!text) return;
                          const thread = document.querySelector('.coach-thread');
                          if (!thread) return;
                          const time = new Date().toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
                          const div = document.createElement('div');
                          div.className = 'msg msg-optimistic';
                          div.dataset.optimistic = '1';
                          div.innerHTML = `<div class='msg-avatar user'>R</div><div class='msg-body'><div class='msg-name'>Você <span class='time'>${time}</span></div><div class='msg-content'></div></div>`;
                          div.querySelector('.msg-content').textContent = text;
                          thread.appendChild(div);
                          thread.scrollTop = thread.scrollHeight;
                          if (ta) ta.value = '';
                      ">
                    <div class="composer">
                        {{ $this->form }}

                        <div class="composer-actions">
                            <button type="button"
                                    class="composer-attach-btn"
                                    @click="$root.querySelector('.filepond--browser')?.click()"
                                    aria-label="{{ __('coach.composer.attach') }}"
                                    title="{{ __('coach.composer.attach') }}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            </button>
                            <span class="composer-hint">
                                <kbd>↵</kbd> envia · <kbd>shift</kbd>+<kbd>↵</kbd> nova linha
                            </span>
                            <button type="submit"
                                    class="send-btn"
                                    x-bind:disabled="$wire.thinking">
                                @if ($thinking)
                                    <span class="btn-spinner"></span>
                                @else
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                @endif
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
                @forelse ($planActions as $action)
                    <div class="plan-item {{ $action['has_details'] ? 'has-details' : '' }}"
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
                                        <div class="plan-item-badges">
                                            <span class="plan-badge-cat plan-cat-{{ $action['category'] }}">{{ $action['category'] }}</span>
                                            <span class="plan-badge-pri plan-pri-{{ $action['priority'] }}">{{ $action['priority'] }}</span>
                                        </div>
                                        @if ($action['deadline'])
                                            <span class="plan-deadline {{ $action['is_overdue'] ? 'overdue' : ($action['is_due_soon'] ? 'soon' : '') }}">
                                                {{ $action['deadline'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
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

        {{-- Budget flyout drawer (read-only stage 1) --}}
        <div class="plan-overlay"
             x-show="budgetOpen"
             x-transition.opacity.duration.150ms
             @click="budgetOpen = false; $wire.closeBudget()"
             style="display: none;"></div>

        <aside class="plan-drawer budget-drawer"
               :class="budgetOpen ? 'is-open' : ''">
            @if ($budgetData)
                <div class="plan-drawer-header">
                    <div>
                        <div class="plan-drawer-title">{{ __('coach.budget_flyout.title') }}</div>
                        <div class="plan-drawer-sub">{{ __('coach.budget_flyout.subtitle', ['month' => $budgetData['month']]) }}</div>
                    </div>
                    <button type="button" class="plan-close-btn" @click="budgetOpen = false" wire:click="closeBudget">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                <div class="budget-flyout-body">
                    <div class="budget-row budget-row-headline">
                        <div class="budget-row-label">{{ __('coach.budget_flyout.net_income') }}</div>
                        <div class="budget-row-value">R$ {{ number_format($budgetData['net_income'], 2, ',', '.') }}</div>
                    </div>

                    <div class="budget-section">
                        <div class="budget-section-title">{{ __('coach.budget_flyout.fixed_costs') }}</div>
                        @foreach ($budgetData['fixed_costs_breakdown'] as $label => $amount)
                            <div class="budget-line">
                                <span class="budget-line-label">{{ $label }}</span>
                                <span class="budget-line-value">R$ {{ number_format((float) $amount, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                        <div class="budget-line budget-line-subtotal">
                            <span class="budget-line-label">{{ __('coach.budget_flyout.subtotal') }}</span>
                            <span class="budget-line-value">R$ {{ number_format($budgetData['fixed_costs_subtotal'], 2, ',', '.') }}</span>
                        </div>
                        <div class="budget-line budget-line-total">
                            <span class="budget-line-label">{{ __('coach.budget_flyout.total_with_buffer') }}</span>
                            <span class="budget-line-value">R$ {{ number_format($budgetData['fixed_costs_total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="budget-section">
                        <div class="budget-section-title">{{ __('coach.budget_flyout.investments') }}</div>
                        @foreach ($budgetData['investments_breakdown'] as $label => $amount)
                            <div class="budget-line">
                                <span class="budget-line-label">{{ $label }}</span>
                                <span class="budget-line-value">R$ {{ number_format((float) $amount, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                        @if (empty($budgetData['investments_breakdown']))
                            <div class="budget-line budget-line-empty">{{ __('coach.budget_flyout.empty_bucket') }}</div>
                        @endif
                        <div class="budget-line budget-line-total">
                            <span class="budget-line-label">{{ __('coach.budget_flyout.total') }}</span>
                            <span class="budget-line-value">R$ {{ number_format($budgetData['investments_total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="budget-section">
                        <div class="budget-section-title">{{ __('coach.budget_flyout.savings') }}</div>
                        @foreach ($budgetData['savings_breakdown'] as $label => $amount)
                            <div class="budget-line">
                                <span class="budget-line-label">{{ $label }}</span>
                                <span class="budget-line-value">R$ {{ number_format((float) $amount, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                        @if (empty($budgetData['savings_breakdown']))
                            <div class="budget-line budget-line-empty">{{ __('coach.budget_flyout.empty_bucket') }}</div>
                        @endif
                        <div class="budget-line budget-line-total">
                            <span class="budget-line-label">{{ __('coach.budget_flyout.total') }}</span>
                            <span class="budget-line-value">R$ {{ number_format($budgetData['savings_total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="budget-row budget-row-leisure {{ $budgetData['leisure_amount'] < 0 ? 'is-deficit' : '' }}">
                        <div class="budget-row-label">{{ __('coach.budget_flyout.leisure') }}</div>
                        <div class="budget-row-value">R$ {{ number_format($budgetData['leisure_amount'], 2, ',', '.') }}</div>
                    </div>

                    @if ($budgetData['leisure_amount'] < 0)
                        <div class="budget-deficit-warning">
                            {{ __('coach.budget_flyout.deficit_warning', ['amount' => 'R$ '.number_format(abs($budgetData['leisure_amount']), 2, ',', '.')]) }}
                        </div>
                    @endif
                </div>
            @endif
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
                    <button type="button"
                            class="complete-modal-confirm"
                            wire:click="createGoal"
                            wire:loading.attr="disabled"
                            wire:target="createGoal">
                        <span wire:loading.remove wire:target="createGoal">
                            {{ __('coach.new_goal_modal.create') }}
                        </span>
                        <span wire:loading wire:target="createGoal" class="btn-spinner"></span>
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
                    <button type="button"
                            class="complete-modal-confirm complete-modal-confirm--success"
                            wire:click="confirmCompleteAction"
                            wire:loading.attr="disabled"
                            wire:target="confirmCompleteAction">
                        <span wire:loading.remove wire:target="confirmCompleteAction">
                            {{ __('coach.complete_modal.confirm') }}
                        </span>
                        <span wire:loading wire:target="confirmCompleteAction" class="btn-spinner"></span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Share message modal --}}
    @if ($sharingMessageIndex !== null)
        <div class="complete-modal-overlay" wire:click="cancelShare"
             wire:key="share-modal-{{ $sharingMessageIndex }}">
            <div class="complete-modal" @click.stop wire:click.stop style="max-width: 520px;">
                <div class="complete-modal-header">
                    <span class="complete-modal-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    </span>
                    <div>
                        <div class="complete-modal-title">{{ __('coach.share_modal.title') }}</div>
                    </div>
                </div>

                <label class="complete-modal-label" for="shareRecipient">{{ __('coach.share_modal.recipient_label') }}</label>
                <input type="text"
                       id="shareRecipient"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="shareRecipient"
                       placeholder="{{ __('coach.share_modal.recipient_placeholder') }}"
                       autofocus
                       wire:keydown.escape="cancelShare">

                <label class="complete-modal-label" for="shareSubject">{{ __('coach.share_modal.subject_label') }}</label>
                <input type="text"
                       id="shareSubject"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="shareSubject"
                       wire:keydown.escape="cancelShare">

                <label class="complete-modal-label" for="shareBody">{{ __('coach.share_modal.body_label') }}</label>
                <textarea id="shareBody"
                          class="complete-modal-textarea"
                          wire:model="shareBody"
                          rows="6"
                          wire:keydown.escape="cancelShare"></textarea>

                @if ($shareError)
                    <div class="complete-modal-error" role="alert" style="color: #ef4444; font-size: 0.85em; margin-top: 4px;">
                        {{ $shareError }}
                    </div>
                @endif

                <div class="complete-modal-footer">
                    <button type="button" class="complete-modal-cancel" wire:click="cancelShare">
                        {{ __('coach.share_modal.cancel') }}
                    </button>
                    <button type="button"
                            class="complete-modal-confirm"
                            wire:click="confirmShare"
                            wire:loading.attr="disabled"
                            wire:target="confirmShare">
                        <span wire:loading.remove wire:target="confirmShare">
                            {{ __('coach.share_modal.send') }}
                        </span>
                        <span wire:loading wire:target="confirmShare" class="btn-spinner"></span>
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

        // Sweep optimistic user-message placeholders after every Livewire
        // morph. By the time send() has committed, the real .msg is in
        // the rendered thread, so any [data-optimistic] sibling is a
        // stale duplicate and gets removed.
        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', () => {
                document.querySelectorAll('[data-optimistic]').forEach(el => el.remove());
            });
        });
    </script>
</x-filament-panels::page>
