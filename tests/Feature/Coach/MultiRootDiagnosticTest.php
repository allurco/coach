<?php

use App\Filament\Pages\Coach;
use App\Models\User;
use Livewire\Livewire;

/**
 * Diagnóstico do MultipleRootElementsDetectedException que aparece
 * só no CI — local mostra 1 root via DOMDocument. Esse teste:
 *
 *   1. Desliga app.debug pra evitar que o próprio check do Livewire
 *      lance a exceção antes de a gente conseguir inspecionar o HTML.
 *   2. Renderiza via Livewire::test (mesmo pipeline que falhou no CI).
 *   3. Reaplica a contagem exata do Livewire (DOMDocument > body >
 *      childNodes XML_ELEMENT_NODE).
 *   4. Se contar > 1, falha mostrando os tagNames + classes dos
 *      filhos e os primeiros 4000 chars do HTML cleaned.
 *
 * Local roda verde. Se CI continuar com multi-root, o output da
 * mensagem dessa expect vai dizer exatamente quais elementos estão
 * no topo — aí dá pra remover a fonte real (ou marcar como falso
 * positivo se for ruído inevitável do renderer).
 */
it('reports exactly one root element from Coach::class rendered HTML', function () {
    config(['app.debug' => false]); // desliga o early-throw do Livewire

    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(Coach::class);
    $html = (string) $page->html();

    // Replica a mesma lógica do
    // vendor/livewire/livewire/src/Features/SupportMultipleRootElementDetection
    // /SupportMultipleRootElementDetection.php — strip script + style, parse,
    // count XML_ELEMENT_NODE children of body.
    $cleaned = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
    $cleaned = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $cleaned);

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($cleaned, LIBXML_NOERROR);
    libxml_clear_errors();

    $body = $dom->getElementsByTagName('body')->item(0);

    $rootSummaries = [];
    foreach ($body->childNodes as $child) {
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }
        $class = method_exists($child, 'getAttribute') ? $child->getAttribute('class') : '';
        $rootSummaries[] = sprintf('<%s class="%s">', $child->nodeName, $class);
    }

    $count = count($rootSummaries);

    if ($count !== 1) {
        // Mostra os filhos do <body> + começo do HTML pra diagnóstico
        // em CI. Truncado pra mensagem caber no relatório.
        $snippet = substr($cleaned, 0, 4000);
        $msg = sprintf(
            "Root element count = %d (esperava 1).\nFilhos do <body>:\n%s\n\nHTML head:\n%s",
            $count,
            implode("\n", $rootSummaries),
            $snippet,
        );
        expect($count)->toBe(1, $msg);
    }

    expect($count)->toBe(1);
});
