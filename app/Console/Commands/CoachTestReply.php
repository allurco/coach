<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CoachReplyProcessor;
use App\Services\EmailReplyParser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('coach:test-reply
    {reply : The reply text}
    {--user= : User ID (defaults to first user)}
    {--subject= : Subject hint to match an existing conversation by title}
    {--conversation= : Conversation ID to continue (overrides subject lookup)}')]
#[Description('Simulates an inbound email reply locally — useful for testing without setting up an inbox/webhook')]
class CoachTestReply extends Command
{
    public function handle(CoachReplyProcessor $processor): int
    {
        $user = $this->option('user')
            ? User::find($this->option('user'))
            : User::first();

        if (! $user) {
            $this->error('Nenhum usuário encontrado.');

            return self::FAILURE;
        }

        $reply = (string) $this->argument('reply');
        $cleaned = EmailReplyParser::extractReply($reply);

        if (trim($cleaned) === '') {
            $this->error('Reply ficou vazio depois de tirar trechos citados.');

            return self::FAILURE;
        }

        $this->info("Processando reply de {$user->email}…");
        $this->line("Texto limpo: {$cleaned}");
        $this->newLine();

        $result = $processor->process(
            user: $user,
            reply: $cleaned,
            conversationId: $this->option('conversation'),
            subjectHint: $this->option('subject'),
        );

        $this->line('--- Resposta do coach ---');
        $this->line($result['response']);
        $this->newLine();
        $this->info("Conversa: {$result['conversation_id']}");

        return self::SUCCESS;
    }
}
