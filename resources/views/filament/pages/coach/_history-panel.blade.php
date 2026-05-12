{{-- History panel — older conversations of the active goal --}}
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
