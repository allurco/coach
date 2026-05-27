{{-- Complete-action modal — opens from the ✓ button on each plan-item --}}
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
