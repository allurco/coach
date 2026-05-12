<?php

namespace App\Filament\Pages\Concerns;

use App\Exceptions\ShareFailedException;
use App\Services\Sharer;
use Filament\Notifications\Notification;

/**
 * State + behavior do modal "Compartilhar este orçamento" que abre
 * de dentro do Budget flyout. Reusa App\Services\Sharer pra dispatch
 * + auto-BCC + rate-limit.
 *
 * Dependências do componente que o usa:
 * - $budgetData (do HasBudgetFlyout) — usado em openBudgetShare pra
 *   pré-preencher subject com o mês.
 */
trait HasBudgetShare
{
    public bool $budgetShareOpen = false;

    public string $budgetShareRecipient = '';

    public string $budgetShareSubject = '';

    public string $budgetShareBody = '';

    public ?string $budgetShareError = null;

    /**
     * Abre o modal. Pré-preenche body com placeholder
     * {{budget:current}} — PlaceholderRenderer expande no send,
     * então mesmo que o user salve fresh snapshot entre abrir e
     * mandar, o destinatário pega o mais recente. Subject inclui o
     * mês pra dar contexto.
     */
    public function openBudgetShare(): void
    {
        if ($this->budgetData === null) {
            return;
        }

        $this->budgetShareOpen = true;
        $this->budgetShareRecipient = '';
        $this->budgetShareSubject = (string) __('coach.budget_flyout.share_subject_default', [
            'month' => (string) ($this->budgetData['month'] ?? ''),
        ]);
        $this->budgetShareBody = (string) __('coach.budget_flyout.share_body_default');
        $this->budgetShareError = null;
    }

    public function cancelBudgetShare(): void
    {
        $this->budgetShareOpen = false;
        $this->budgetShareRecipient = '';
        $this->budgetShareSubject = '';
        $this->budgetShareBody = '';
        $this->budgetShareError = null;
    }

    public function confirmBudgetShare(): void
    {
        if (! $this->budgetShareOpen) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            $this->budgetShareError = (string) __('coach.share.errors.unauthenticated');

            return;
        }

        try {
            $message = app(Sharer::class)->send(
                user: $user,
                to: $this->budgetShareRecipient,
                subject: $this->budgetShareSubject,
                body: $this->budgetShareBody,
            );

            Notification::make()
                ->title($message)
                ->success()
                ->send();

            $this->cancelBudgetShare();
        } catch (ShareFailedException $e) {
            $this->budgetShareError = $e->getMessage();
        }
    }
}
