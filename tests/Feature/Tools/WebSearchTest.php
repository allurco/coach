<?php

use App\Ai\Tools\WebSearch;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    config([
        'coach.tavily_api_key' => 'fake-tavily-key',
    ]);
    $this->tool = new WebSearch;
});

it('calls Tavily with the query and returns formatted top results', function () {
    Http::fake([
        'api.tavily.com/*' => Http::response([
            'results' => [
                ['title' => 'Selic em 2026', 'url' => 'https://example.com/selic', 'content' => 'Taxa atual 11%', 'score' => 0.9],
                ['title' => 'IPCA mensal', 'url' => 'https://example.com/ipca', 'content' => 'IPCA acumulou 4%', 'score' => 0.85],
            ],
        ], 200),
    ]);

    $result = (string) $this->tool->handle(new Request(['query' => 'selic atual']));

    expect($result)
        ->toContain('Selic em 2026')
        ->toContain('Taxa atual 11%')
        ->toContain('https://example.com/selic')
        ->toContain('IPCA mensal');
});

it('limits results to max_results when more come back', function () {
    Http::fake([
        'api.tavily.com/*' => Http::response([
            'results' => array_map(fn ($i) => [
                'title' => "Result {$i}",
                'url' => "https://example.com/{$i}",
                'content' => "content {$i}",
                'score' => 0.5,
            ], range(1, 10)),
        ], 200),
    ]);

    $result = (string) $this->tool->handle(new Request([
        'query' => 'q',
        'max_results' => 2,
    ]));

    expect($result)->toContain('Result 1')
        ->toContain('Result 2')
        ->not->toContain('Result 3');
});

it('returns an error message when the API key is not configured', function () {
    config(['coach.tavily_api_key' => null]);

    $result = (string) (new WebSearch)->handle(new Request(['query' => 'anything']));

    expect($result)->toContain('not configured');
});

it('returns an error message when the query is empty', function () {
    $result = (string) $this->tool->handle(new Request(['query' => '']));

    expect($result)->toContain('empty');
});

it('returns a graceful message when Tavily errors', function () {
    Http::fake([
        'api.tavily.com/*' => Http::response(['error' => 'rate limit'], 429),
    ]);

    $result = (string) $this->tool->handle(new Request(['query' => 'q']));

    expect($result)->toContain('failed')
        ->and($result)->toContain('429');
});

it('returns a no-results message when Tavily returns empty', function () {
    Http::fake([
        'api.tavily.com/*' => Http::response(['results' => []], 200),
    ]);

    $result = (string) $this->tool->handle(new Request(['query' => 'gibberish-query-no-results']));

    expect($result)->toContain('no results');
});

it('sends the API key in the request body', function () {
    Http::fake([
        'api.tavily.com/*' => Http::response(['results' => []], 200),
    ]);

    $this->tool->handle(new Request(['query' => 'q']));

    Http::assertSent(function ($req) {
        $body = json_decode($req->body(), true);

        return $body['api_key'] === 'fake-tavily-key' && $body['query'] === 'q';
    });
});
