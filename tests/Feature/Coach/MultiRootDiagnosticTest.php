<?php

use App\Filament\Pages\Coach;
use App\Models\User;
use Livewire\Livewire;

/**
 * Última rodada forense pro multi-root no CI:
 *
 *   - Locale forçado en (replica .env.example do CI)
 *   - 4 estratégias de parse: bare, whitespace-normalized, UTF-8
 *     hint, sem comments — relata quantos roots cada uma vê
 *   - Lista libxml errors caso parser tropece em algo específico
 *
 * Esperado: pelo menos UMA estratégia retorna count=1 (a que
 * identifica a causa exata). Se nenhuma, problema é mais profundo
 * que charset/comments/whitespace.
 */
function countRoots(string $html): int
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_NOERROR);
    libxml_clear_errors();
    $body = $dom->getElementsByTagName('body')->item(0);
    $count = 0;
    if ($body) {
        foreach ($body->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $count++;
            }
        }
    }

    return $count;
}

it('reports root counts across 4 parse strategies', function () {
    config(['app.debug' => false]);
    app()->setLocale('en');

    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(Coach::class);
    $html = (string) $page->html();

    // Strip <script>/<style> exatamente como Livewire faz.
    $base = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
    $base = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $base);

    $strategies = [
        'bare' => $base,
        'no_whitespace' => preg_replace('/\s+/', ' ', $base),
        'utf8_hint' => '<?xml encoding="UTF-8"?>'.$base,
        'no_comments' => preg_replace('/<!--.*?-->/s', '', $base),
    ];

    $results = [];
    foreach ($strategies as $name => $variant) {
        $results[$name] = countRoots($variant);
    }

    $msg = "Root counts: ".json_encode($results)."\n\n"
        ."If any strategy returns 1, that's the parser quirk to neutralize.\n"
        ."If all return 2, the issue is deeper than charset/comments/whitespace.\n\n"
        ."HTML head:\n".substr($base, 0, 3000);

    expect($results['bare'])->toBe(1, $msg);
});
