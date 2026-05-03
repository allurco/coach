<x-filament-panels::page>

    <div class="coach-page" x-data="{ planOpen: false }" @keydown.escape.window="planOpen = false">
        <div class="coach-shell">

            {{-- Sidebar with chat history --}}
            <aside class="coach-sidebar">
                <div class="sidebar-header">
                    <div class="sidebar-title">Conversas</div>
                    <button type="button" class="new-chat-btn" wire:click="newConversation" title="Nova conversa">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        novo
                    </button>
                </div>

                <div class="conv-list">
                    @forelse ($conversations as $conv)
                        <button type="button"
                                class="conv-item {{ $conversationId === $conv['id'] ? 'active' : '' }}"
                                wire:click="loadConversation('{{ $conv['id'] }}')">
                            <div class="conv-item-title">{{ $conv['title'] }}</div>
                            <div class="conv-item-time">{{ $conv['updated_label'] }}</div>
                        </button>
                    @empty
                        <div class="conv-empty">
                            Suas conversas aparecem aqui depois da primeira mensagem.
                        </div>
                    @endforelse
                </div>
            </aside>

            {{-- Main --}}
            <div class="coach-main">

                {{-- Header --}}
                <div class="coach-header">
                    <div>
                        <div class="coach-title">
                            Coach
                            @if ($conversationId)
                                <span class="pulse-dot"></span>
                            @endif
                        </div>
                        <div class="coach-status">
                            @if ($conversationId)
                                conversa em andamento
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
                            <div class="msg">
                                <div class="msg-avatar coach">C</div>
                                <div class="msg-body">
                                    <div class="msg-name">Coach</div>
                                    <div class="msg-content streaming-content"
                                         wire:stream="coach-stream"
                                         x-data="{}"
                                         x-init="$nextTick(() => $el.closest('.coach-thread').scrollTop = $el.closest('.coach-thread').scrollHeight)"></div>
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
                    <div class="plan-drawer-sub">{{ count($planActions) }} {{ $planFilter }}</div>
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
                    <div class="plan-item" x-data="{ menu: false }" @click.away="menu = false">
                        <div class="plan-item-main">
                            <div class="plan-item-title {{ $a['is_overdue'] ? 'overdue' : '' }}">{{ $a['title'] }}</div>
                            <div class="plan-item-meta">
                                <span class="plan-badge-cat plan-cat-{{ $a['category'] }}">{{ $a['category'] }}</span>
                                <span class="plan-badge-pri plan-pri-{{ $a['priority'] }}">{{ $a['priority'] }}</span>
                                @if ($a['deadline'])
                                    <span class="plan-deadline {{ $a['is_overdue'] ? 'overdue' : ($a['is_due_soon'] ? 'soon' : '') }}">
                                        {{ $a['deadline'] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if ($a['status'] !== 'concluido')
                            <div class="plan-item-actions">
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
