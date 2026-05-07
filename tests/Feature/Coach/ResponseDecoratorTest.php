<?php

use App\Filament\Pages\Coach;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->page = new Coach;
});

function decorate(Coach $page, string $text, array $toolActivity = []): string
{
    $ref = new ReflectionMethod($page, 'decorateAssistantResponse');
    $ref->setAccessible(true);

    return $ref->invoke($page, $text, $toolActivity);
}

it('returns clean text when tools were called for narrated actions', function () {
    $text = 'Beleza, criei a ação "Fazer pilates" pro plano.';
    $tools = [['name' => 'CreateAction', 'count' => 1, 'ok' => 1]];

    expect(decorate($this->page, $text, $tools))->toBe($text);
});

it('returns clean text when no action language and no tools', function () {
    $text = 'Boa noite, Rogers. Como posso ajudar?';

    expect(decorate($this->page, $text))->toBe($text);
});

it('flags truncation when response ends with a colon and no tools fired', function () {
    $text = 'Aqui está seu plano atualizado, já com o pilates na lista:';

    $result = decorate($this->page, $text);

    expect($result)->toContain($text)
        ->and($result)->toMatch('/interrompida|truncated|tenta de novo|try again/iu');
});

it('flags creation narrated without CreateAction call', function () {
    $text = 'Fechado! Criei a ação "Fazer pilates" pra você.';
    $tools = [['name' => 'ListActions', 'count' => 1, 'ok' => 1]];

    $result = decorate($this->page, $text, $tools);

    expect($result)->toContain('Criei')
        ->and($result)->toMatch('/n[ãa]o executou|did not run|tenta de novo|try again/iu');
});

it('flags update narrated without UpdateAction call', function () {
    $text = 'Marquei a ação como concluída.';

    $result = decorate($this->page, $text);

    expect($result)->toMatch('/n[ãa]o executou|did not run|tenta de novo|try again/iu');
});

it('does not double-flag when both creation narrated AND tool was called', function () {
    $text = 'Adicionei a ação "Pilates" pro plano.';
    $tools = [['name' => 'CreateAction', 'count' => 1, 'ok' => 1]];

    expect(decorate($this->page, $text, $tools))->toBe($text);
});

it('does not flag a response that ends with a period and no actions', function () {
    $text = 'Beleza. Manda ver.';

    expect(decorate($this->page, $text))->toBe($text);
});

it('does not flag a response that ends with a question mark', function () {
    $text = 'Quer que eu adicione?';

    expect(decorate($this->page, $text))->toBe($text);
});
