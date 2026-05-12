<?php

namespace App\Filament\Pages\Concerns;

use App\Exceptions\ShareFailedException;
use App\Services\Sharer;
use Filament\Notifications\Notification;

/**
 * State + behavior do modal de "Compartilhar essa mensagem" que abre
 * pelo ícone de share no header de cada bolha de resposta do Coach.
 *
 * State separado do HasBudgetShare — os dois modals podem coexistir
 * sem colidir state (mesma classe, escopos isolados por prefixo).
 *
 * Dependências:
 * - $this->messages (do componente principal) — usado em
 *   openShareModal pra ler o conteúdo da bolha.
 * - App\Services\Sharer (rate-limit + auto-BCC + recipient resolver).
 */
trait HasShareMessageModal
{
    public ?int $sharingMessageIndex = null;

    public string $shareRecipient = '';

    public string $shareSubject = '';

    public string $shareBody = '';

    public ?string $shareError = null;

    /**
     * Abre o modal pré-preenchido com o conteúdo da mensagem no
     * índice passado. Silently no-op pra índices fora do range e
     * pra mensagens user (compartilhar a própria pergunta não tem
     * sentido — o destinatário receberia a pergunta, não a resposta).
     */
    public function openShareModal(int $messageIndex): void
    {
        if (! isset($this->messages[$messageIndex])) {
            return;
        }

        $msg = $this->messages[$messageIndex];
        if (($msg['role'] ?? null) !== 'assistant') {
            return;
        }

        $this->sharingMessageIndex = $messageIndex;
        $this->shareRecipient = '';
        $this->shareSubject = (string) __('coach.share_modal.default_subject', [
            'date' => now()->format('d/m/Y'),
        ]);
        $this->shareBody = (string) ($msg['content'] ?? '');
        $this->shareError = null;
    }

    public function cancelShare(): void
    {
        $this->sharingMessageIndex = null;
        $this->shareRecipient = '';
        $this->shareSubject = '';
        $this->shareBody = '';
        $this->shareError = null;
    }

    public function confirmShare(): void
    {
        if ($this->sharingMessageIndex === null) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            $this->shareError = (string) __('coach.share.errors.unauthenticated');

            return;
        }

        try {
            $message = app(Sharer::class)->send(
                user: $user,
                to: $this->shareRecipient,
                subject: $this->shareSubject,
                body: $this->shareBody,
            );

            Notification::make()
                ->title($message)
                ->success()
                ->send();

            $this->cancelShare();
        } catch (ShareFailedException $e) {
            $this->shareError = $e->getMessage();
        }
    }
}
