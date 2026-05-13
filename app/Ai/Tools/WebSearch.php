<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebSearch implements Tool
{
    public function description(): Stringable|string
    {
        return 'Searches the web to find current information or things you don\'t have in memory '
            .'(e.g. exchange rates, tax rates, recent laws, news, specific recommendations). '
            .'Returns up to 5 results with title, URL and snippet. '
            .'Use when the user asks for something factual and current you\'re not sure about.';
    }

    public function handle(Request $request): Stringable|string
    {
        $apiKey = config('coach.tavily_api_key');

        if (! $apiKey) {
            return 'WebSearch is not configured (missing TAVILY_API_KEY). Tell the user this is unavailable right now.';
        }

        $query = trim((string) ($request['query'] ?? ''));
        if ($query === '') {
            return 'WebSearch query is empty.';
        }

        $maxResults = (int) ($request['max_results'] ?? 5);
        $maxResults = max(1, min($maxResults, 10));

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post('https://api.tavily.com/search', [
                    'api_key' => $apiKey,
                    'query' => $query,
                    'max_results' => $maxResults,
                    'search_depth' => 'basic',
                ]);

            if (! $response->successful()) {
                Log::warning('WebSearch: Tavily error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return "WebSearch failed (status {$response->status()}). Tell the user search is temporarily unavailable.";
            }

            $results = $response->json('results') ?? [];

            if (empty($results)) {
                return "WebSearch returned no results for: {$query}";
            }

            $lines = ["Top {$maxResults} results for: {$query}", ''];

            foreach (array_slice($results, 0, $maxResults) as $i => $r) {
                $n = $i + 1;
                $title = $r['title'] ?? '(no title)';
                $url = $r['url'] ?? '';
                $content = trim((string) ($r['content'] ?? ''));
                $content = mb_substr($content, 0, 400);

                $lines[] = "{$n}. {$title}";
                $lines[] = "   {$url}";
                if ($content !== '') {
                    $lines[] = "   {$content}";
                }
                $lines[] = '';
            }

            return trim(implode("\n", $lines));
        } catch (\Throwable $e) {
            Log::error('WebSearch: exception', ['message' => $e->getMessage()]);

            return 'WebSearch failed (network error). Tell the user search is temporarily unavailable.';
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
            'max_results' => $schema->integer(),
        ];
    }
}
