{{-- New goal modal — collected from the sidebar "+ novo" button --}}
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
