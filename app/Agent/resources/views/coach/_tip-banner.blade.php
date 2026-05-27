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
