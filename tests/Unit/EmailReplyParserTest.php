<?php

use App\Services\EmailReplyParser;

it('returns the body unchanged when there is no quoted history', function () {
    $body = 'Já paguei a fatura, marca como concluído.';

    expect(EmailReplyParser::extractReply($body))->toBe($body);
});

it('strips Gmail-style English quoted reply', function () {
    $body = "Já paguei a fatura.\n\nOn Mon, Jan 1, 2024 at 10:00 AM Coach <coach@coach.allur.co> wrote:\n> ☀️ Foco do dia\n> Pague a fatura";

    expect(EmailReplyParser::extractReply($body))
        ->toBe('Já paguei a fatura.');
});

it('strips Gmail-style Portuguese quoted reply', function () {
    $body = "Falei com o contador.\n\nEm seg, 1 de jan de 2024 10:00, Coach <coach@coach.allur.co> escreveu:\n> Pendências do dia";

    expect(EmailReplyParser::extractReply($body))
        ->toBe('Falei com o contador.');
});

it('strips Outlook-style "From:" header block', function () {
    $body = "Resposta nova.\n\nFrom: Coach <coach@coach.allur.co>\nSent: Monday\nTo: Rogers\n";

    expect(EmailReplyParser::extractReply($body))
        ->toBe('Resposta nova.');
});

it('strips Portuguese "De:" header block', function () {
    $body = "Resposta nova.\n\nDe: Coach <coach@coach.allur.co>\nEnviado: segunda";

    expect(EmailReplyParser::extractReply($body))
        ->toBe('Resposta nova.');
});

it('strips lines starting with > (manual quote markers)', function () {
    $body = "Minha resposta.\n> linha citada antiga\n> outra linha citada";

    expect(EmailReplyParser::extractReply($body))
        ->toBe('Minha resposta.');
});

it('converts HTML to text and strips blockquote', function () {
    $body = '<p>Já paguei a fatura.</p><blockquote>Mensagem original</blockquote>';

    expect(EmailReplyParser::extractReply($body))
        ->toContain('Já paguei a fatura.')
        ->not->toContain('Mensagem original');
});

it('strips "Begin forwarded message"', function () {
    $body = "Veja isso aqui.\n\nBegin forwarded message:\nFrom: someone\n";

    expect(EmailReplyParser::extractReply($body))
        ->toBe('Veja isso aqui.');
});

it('returns empty string when input has no actual reply (only quoted block)', function () {
    // Realistic email body where the user replied with only whitespace before the quote.
    $body = "  \nOn Mon, Jan 1, 2024 at 10:00 AM Coach wrote:\n> tudo citado";

    expect(trim(EmailReplyParser::extractReply($body)))->toBe('');
});
