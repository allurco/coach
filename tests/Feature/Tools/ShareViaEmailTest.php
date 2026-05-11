<?php

use App\Ai\Tools\ShareViaEmail;
use App\Mail\Share;
use App\Models\Action;
use App\Models\Budget;
use App\Models\Contact;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'me@example.com',
    ]);
    $this->actingAs($this->user);
    Mail::fake();
    RateLimiter::clear('share-via-email:'.$this->user->id);
    $this->tool = new ShareViaEmail;
});

it('sends to a literal email address', function () {
    $result = $this->tool->handle(new Request([
        'to' => 'joao@example.com',
        'subject' => 'Plano',
        'body' => 'Segue o plano.',
    ]));

    Mail::assertSent(Share::class, function (Share $mail) {
        return $mail->hasTo('joao@example.com');
    });

    expect((string) $result)->toMatch('/enviado|sent|joao@example\.com/iu');
});

it('resolves a saved contact label to an email', function () {
    Contact::create([
        'label' => 'contador',
        'name' => 'João',
        'email' => 'joao@example.com',
    ]);

    $this->tool->handle(new Request([
        'to' => 'contador',
        'subject' => 'Plano',
        'body' => 'Segue o plano.',
    ]));

    Mail::assertSent(Share::class, function (Share $mail) {
        return $mail->hasTo('joao@example.com');
    });
});

it('handles cc and bcc addresses (literals + labels mixed)', function () {
    Contact::create(['label' => 'esposa', 'email' => 'esposa@example.com']);

    $this->tool->handle(new Request([
        'to' => 'joao@example.com',
        'cc' => ['esposa', 'amigo@example.com'],
        'bcc' => ['arquivo@example.com'],
        'subject' => 'Plano',
        'body' => 'Segue.',
    ]));

    Mail::assertSent(Share::class, function (Share $mail) {
        return $mail->hasTo('joao@example.com')
            && $mail->hasCc('esposa@example.com')
            && $mail->hasCc('amigo@example.com')
            && $mail->hasBcc('arquivo@example.com');
    });
});

it('auto-BCCs the authenticated user so they keep a copy', function () {
    $this->tool->handle(new Request([
        'to' => 'joao@example.com',
        'subject' => 'Plano',
        'body' => 'Segue.',
    ]));

    Mail::assertSent(Share::class, function (Share $mail) {
        return $mail->hasBcc('me@example.com');
    });
});

it('expands {{budget:current}} in the body before sending', function () {
    Budget::create([
        'goal_id' => null,
        'month' => now()->format('Y-m'),
        'net_income' => 7200,
        'fixed_costs_subtotal' => 4000,
        'fixed_costs_total' => 4000,
        'investments_total' => 720,
        'savings_total' => 480,
        'leisure_amount' => 2000,
    ]);

    $this->tool->handle(new Request([
        'to' => 'joao@example.com',
        'subject' => 'Plano',
        'body' => "Olha:\n\n{{budget:current}}",
    ]));

    Mail::assertSent(Share::class, function (Share $mail) {
        return str_contains($mail->body, 'Plano de Gastos')
            && ! str_contains($mail->body, '{{budget:current}}');
    });
});

it('expands {{plan}} in the body before sending', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Action::create(['goal_id' => $goal->id, 'title' => 'Pagar fatura', 'status' => 'pendente']);

    $this->tool->handle(new Request([
        'to' => 'joao@example.com',
        'subject' => 'Plano',
        'body' => "Plano:\n\n{{plan}}",
    ]));

    Mail::assertSent(Share::class, function (Share $mail) {
        return str_contains($mail->body, 'Pagar fatura')
            && ! str_contains($mail->body, '{{plan}}');
    });
});

it('passes through the agent-provided subject', function () {
    $this->tool->handle(new Request([
        'to' => 'joao@example.com',
        'subject' => 'Meu plano de Maio',
        'body' => 'oi',
    ]));

    Mail::assertSent(Share::class, function (Share $mail) {
        return $mail->emailSubject === 'Meu plano de Maio';
    });
});

it('rejects malformed primary email', function () {
    $result = $this->tool->handle(new Request([
        'to' => 'not-an-email',
        'subject' => 'Plano',
        'body' => 'oi',
    ]));

    Mail::assertNothingSent();
    expect((string) $result)->toMatch('/email|inválido|invalid|destinatário|recipient/iu');
});

it('rejects an unknown contact label', function () {
    $result = $this->tool->handle(new Request([
        'to' => 'inexistente',
        'subject' => 'Plano',
        'body' => 'oi',
    ]));

    Mail::assertNothingSent();
    expect((string) $result)->toMatch('/contato|contact|inexistente/iu');
});

it('does not resolve another user’s contact label (multi-tenant)', function () {
    $other = User::factory()->create();
    Contact::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'contador',
        'email' => 'theirs@example.com',
    ]);

    $result = $this->tool->handle(new Request([
        'to' => 'contador',
        'subject' => 'Plano',
        'body' => 'oi',
    ]));

    Mail::assertNothingSent();
    expect((string) $result)->toMatch('/contato|contact|contador/iu');
});

it('rejects an empty body', function () {
    $result = $this->tool->handle(new Request([
        'to' => 'joao@example.com',
        'subject' => 'Plano',
        'body' => '   ',
    ]));

    Mail::assertNothingSent();
    expect((string) $result)->toMatch('/corpo|body|vazio|empty/iu');
});

it('rate-limits the user after 5 sends in an hour', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->tool->handle(new Request([
            'to' => "to{$i}@example.com",
            'subject' => 'Plano',
            'body' => 'oi',
        ]));
    }

    $result = $this->tool->handle(new Request([
        'to' => 'overflow@example.com',
        'subject' => 'Plano',
        'body' => 'oi',
    ]));

    Mail::assertSent(Share::class, 5);
    expect((string) $result)->toMatch('/limit|rate|hora|hour|minut/iu');
});
