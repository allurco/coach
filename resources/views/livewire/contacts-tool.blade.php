{{-- Contacts Tool — manage people the user shares things with. The
     chat's ShareViaEmail tool looks up contacts by their label slug, so
     this is the canonical place to add them. Embedded in the Workspace
     via <livewire:contacts-tool /> (ADR 0007). --}}
<div class="contacts-page">
    <div class="contacts-actions">
        <button type="button" class="contacts-new-btn" wire:click="openNewContact">
            + {{ __('coach.tips.save_contact.title') }}
        </button>
    </div>

    @if (empty($contacts))
        <div class="contacts-empty">
            <div class="contacts-empty-icon" aria-hidden="true">
                <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="contacts-empty-copy">
                {{ __('coach.tips.save_contact.prompt') }}
            </div>
        </div>
    @else
        <ul class="contacts-list">
            @foreach ($contacts as $contact)
                <li class="contact-row" wire:key="contact-{{ $contact['id'] }}">
                    <div class="contact-row-main">
                        <div class="contact-row-name">{{ $contact['name'] }}</div>
                        <div class="contact-row-meta">
                            <span class="contact-badge-email">{{ $contact['email'] }}</span>
                            @if ($contact['label'])
                                <span class="contact-badge-label">{{ $contact['label'] }}</span>
                            @endif
                        </div>
                        @if ($contact['notes'])
                            <div class="contact-row-notes">{{ Str::limit($contact['notes'], 120) }}</div>
                        @endif
                    </div>

                    <div class="contact-row-actions">
                        <button type="button" class="contact-action-btn" wire:click="startEdit({{ $contact['id'] }})" title="{{ __('coach.plan.details.expand') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button type="button" class="contact-action-btn contact-delete-btn" wire:click="startDelete({{ $contact['id'] }})" title="delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    {{-- Edit contact modal --}}
    @if ($editingContactId !== null)
        <div class="complete-modal-overlay" wire:click="cancelEdit">
            <div class="complete-modal" @click.stop wire:click.stop style="max-width: 440px;">
                <div class="complete-modal-header">
                    <div>
                        <div class="complete-modal-title">{{ __('coach.plan.details.expand') }}</div>
                    </div>
                </div>

                <label class="complete-modal-label" for="editContactName">{{ __('coach.new_goal_modal.name_label') }}</label>
                <input type="text" id="editContactName"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="editName"
                       autofocus
                       wire:keydown.escape="cancelEdit">

                <label class="complete-modal-label" for="editContactEmail">{{ __('coach.budget_flyout.share_recipient_label') ?? 'Email' }}</label>
                <input type="email" id="editContactEmail"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="editEmail"
                       wire:keydown.escape="cancelEdit">

                <label class="complete-modal-label" for="editContactLabel">{{ __('coach.new_goal_modal.label_label') }}</label>
                <input type="text" id="editContactLabel"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="editLabel"
                       placeholder="accountant, partner, doctor…"
                       wire:keydown.escape="cancelEdit">

                <label class="complete-modal-label" for="editContactNotes">{{ __('coach.complete_modal.label') }}</label>
                <textarea id="editContactNotes"
                          class="complete-modal-textarea"
                          wire:model="editNotes"
                          rows="3"
                          wire:keydown.escape="cancelEdit"></textarea>

                <div class="complete-modal-footer">
                    <button type="button" class="complete-modal-cancel" wire:click="cancelEdit">
                        {{ __('coach.new_goal_modal.cancel') }}
                    </button>
                    <button type="button"
                            class="complete-modal-confirm"
                            wire:click="confirmEdit">
                        {{ __('coach.complete_modal.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- New contact modal --}}
    @if ($newContactOpen)
        <div class="complete-modal-overlay" wire:click="cancelNewContact">
            <div class="complete-modal" @click.stop wire:click.stop style="max-width: 440px;">
                <div class="complete-modal-header">
                    <div>
                        <div class="complete-modal-title">{{ __('coach.tips.save_contact.title') }}</div>
                    </div>
                </div>

                <label class="complete-modal-label" for="newContactName">{{ __('coach.new_goal_modal.name_label') }}</label>
                <input type="text" id="newContactName"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="newName"
                       autofocus
                       wire:keydown.escape="cancelNewContact">

                <label class="complete-modal-label" for="newContactEmail">Email</label>
                <input type="email" id="newContactEmail"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="newEmail"
                       wire:keydown.escape="cancelNewContact">

                <label class="complete-modal-label" for="newContactLabel">{{ __('coach.new_goal_modal.label_label') }}</label>
                <input type="text" id="newContactLabel"
                       class="complete-modal-textarea"
                       style="min-height: 0; height: 38px; padding: 8px 12px;"
                       wire:model="newLabel"
                       placeholder="accountant, partner, doctor…"
                       wire:keydown.escape="cancelNewContact">

                <label class="complete-modal-label" for="newContactNotes">{{ __('coach.complete_modal.label') }}</label>
                <textarea id="newContactNotes"
                          class="complete-modal-textarea"
                          wire:model="newNotes"
                          rows="3"
                          wire:keydown.escape="cancelNewContact"></textarea>

                <div class="complete-modal-footer">
                    <button type="button" class="complete-modal-cancel" wire:click="cancelNewContact">
                        {{ __('coach.new_goal_modal.cancel') }}
                    </button>
                    <button type="button"
                            class="complete-modal-confirm"
                            wire:click="createContact">
                        {{ __('coach.new_goal_modal.create') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete confirmation modal --}}
    @if ($deletingContactId !== null)
        <div class="complete-modal-overlay" wire:click="cancelDelete">
            <div class="complete-modal" @click.stop wire:click.stop style="max-width: 400px;">
                <div class="complete-modal-header">
                    <div>
                        <div class="complete-modal-title">{{ $deletingContactName }}</div>
                        <div class="complete-modal-subtitle">delete?</div>
                    </div>
                </div>

                <div class="complete-modal-footer">
                    <button type="button" class="complete-modal-cancel" wire:click="cancelDelete">
                        {{ __('coach.new_goal_modal.cancel') }}
                    </button>
                    <button type="button"
                            class="complete-modal-confirm complete-modal-confirm--danger"
                            wire:click="confirmDelete">
                        delete
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
