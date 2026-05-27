{{-- Share-message modal — opens from the share icon on each Coach response --}}
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
