{{-- Budget share modal — opens from "Compartilhar" button inside the Budget flyout --}}
@if ($budgetShareOpen)
    <div class="complete-modal-overlay" wire:click="cancelBudgetShare">
        <div class="complete-modal" @click.stop wire:click.stop style="max-width: 520px;">
            <div class="complete-modal-header">
                <span class="complete-modal-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </span>
                <div>
                    <div class="complete-modal-title">{{ __('coach.budget_flyout.share_modal_title') }}</div>
                </div>
            </div>

            <label class="complete-modal-label" for="budgetShareRecipient">{{ __('coach.budget_flyout.share_recipient_label') }}</label>
            <input type="text"
                   id="budgetShareRecipient"
                   class="complete-modal-textarea"
                   style="min-height: 0; height: 38px; padding: 8px 12px;"
                   wire:model="budgetShareRecipient"
                   placeholder="{{ __('coach.budget_flyout.share_recipient_placeholder') }}"
                   autofocus
                   wire:keydown.escape="cancelBudgetShare">

            <label class="complete-modal-label" for="budgetShareSubject">{{ __('coach.budget_flyout.share_subject_label') }}</label>
            <input type="text"
                   id="budgetShareSubject"
                   class="complete-modal-textarea"
                   style="min-height: 0; height: 38px; padding: 8px 12px;"
                   wire:model="budgetShareSubject"
                   wire:keydown.escape="cancelBudgetShare">

            <label class="complete-modal-label" for="budgetShareBody">{{ __('coach.budget_flyout.share_body_label') }}</label>
            <textarea id="budgetShareBody"
                      class="complete-modal-textarea"
                      wire:model="budgetShareBody"
                      rows="8"
                      wire:keydown.escape="cancelBudgetShare"></textarea>

            @if ($budgetShareError)
                <div class="complete-modal-error" role="alert" style="color: #ef4444; font-size: 0.85em; margin-top: 4px;">
                    {{ $budgetShareError }}
                </div>
            @endif

            <div class="complete-modal-footer">
                <button type="button" class="complete-modal-cancel" wire:click="cancelBudgetShare">
                    {{ __('coach.budget_flyout.share_cancel') }}
                </button>
                <button type="button"
                        class="complete-modal-confirm"
                        wire:click="confirmBudgetShare"
                        wire:loading.attr="disabled"
                        wire:target="confirmBudgetShare">
                    <span wire:loading.remove wire:target="confirmBudgetShare">
                        {{ __('coach.budget_flyout.share_send') }}
                    </span>
                    <span wire:loading wire:target="confirmBudgetShare" class="btn-spinner"></span>
                </button>
            </div>
        </div>
    </div>
@endif
