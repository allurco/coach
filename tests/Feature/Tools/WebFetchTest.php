<?php

use App\Ai\Tools\WebFetch;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new WebFetch;
});

it('fetches a URL and returns plain text from HTML', function () {
    Http::fake([
        'example.com/*' => Http::response(
            '<html><body><h1>Hello</h1><p>World <b>bold</b> text.</p></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $result = (string) $this->tool->handle(new Request(['url' => 'https://example.com/page']));

    expect($result)
        ->toContain('Hello')
        ->toContain('World')
        ->toContain('bold')
        ->not->toContain('<h1>')
        ->not->toContain('</p>');
});

it('rejects non-http(s) URLs', function () {
    $cases = [
        'file:///etc/passwd',
        'ftp://example.com/x',
        'javascript:alert(1)',
        'gopher://example.com',
    ];

    foreach ($cases as $url) {
        $result = (string) $this->tool->handle(new Request(['url' => $url]));
        expect(preg_match('/only http/i', $result))->toBe(1);
    }
});

it('rejects empty url', function () {
    $result = (string) $this->tool->handle(new Request(['url' => '']));

    expect($result)->toContain('URL is required');
});

it('rejects malformed url', function () {
    $result = (string) $this->tool->handle(new Request(['url' => 'not-a-real-url']));

    expect(preg_match('/invalid|malformed/i', $result))->toBe(1);
});

it('returns a graceful message on 404', function () {
    Http::fake([
        'example.com/*' => Http::response('', 404),
    ]);

    $result = (string) $this->tool->handle(new Request(['url' => 'https://example.com/missing']));

    expect($result)->toContain('404');
});

it('returns a graceful message on connection error', function () {
    Http::fake([
        'example.com/*' => Http::response('', 500),
    ]);

    $result = (string) $this->tool->handle(new Request(['url' => 'https://example.com/x']));

    expect($result)->toContain('500');
});

it('truncates very large content to a reasonable size', function () {
    $bigContent = str_repeat('palavra ', 50_000); // ~400KB
    Http::fake([
        'example.com/*' => Http::response($bigContent, 200, ['Content-Type' => 'text/plain']),
    ]);

    $result = (string) $this->tool->handle(new Request(['url' => 'https://example.com/big']));

    expect(strlen($result))->toBeLessThan(20_000)
        ->and($result)->toContain('truncated');
});

it('handles plain-text content as-is', function () {
    Http::fake([
        'example.com/*' => Http::response('just plain text', 200, ['Content-Type' => 'text/plain']),
    ]);

    $result = (string) $this->tool->handle(new Request(['url' => 'https://example.com/text']));

    expect($result)->toContain('just plain text');
});

it('blocks localhost and private network ranges (SSRF protection)', function () {
    $blocked = [
        'http://localhost/x',
        'http://127.0.0.1/x',
        'http://192.168.1.1/x',
        'http://10.0.0.1/x',
        'http://169.254.169.254/x',  // AWS metadata
    ];

    foreach ($blocked as $url) {
        $result = (string) $this->tool->handle(new Request(['url' => $url]));
        expect(preg_match('/blocked/i', $result))->toBe(1)
            ->and($result)->toContain('private');
    }
});
