<?php

it('serves the web app manifest at /manifest.webmanifest', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/manifest+json');

    // Validate the actual HTTP response body, not the on-disk file. Catches
    // routing regressions where the URL might serve a 200 with different
    // content (an error page rendered as 200, a stale cached HTML, etc.).
    $body = $response->streamedContent();
    expect($body)->not->toBeEmpty();

    $manifest = json_decode($body, true);

    expect($manifest)
        ->not->toBeNull()
        ->toHaveKey('name')
        ->toHaveKey('short_name')
        ->toHaveKey('start_url')
        ->toHaveKey('display')
        ->toHaveKey('theme_color')
        ->toHaveKey('background_color')
        ->toHaveKey('icons')
        ->and($manifest['display'])->toBeIn(['standalone', 'fullscreen', 'minimal-ui'])
        ->and($manifest['icons'])->toBeArray()->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        expect($icon)->toHaveKeys(['src', 'sizes', 'type']);
    }
});

it('serves the service worker at /sw.js with the right content type', function () {
    $response = $this->get('/sw.js');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/javascript');

    // Validate the response body actually carries SW lifecycle code rather
    // than (e.g.) an error page slipped past the route.
    expect($response->streamedContent())
        ->toContain('install')
        ->toContain('addEventListener');
});

it('login page exposes the manifest link in the document head', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('rel="manifest"', escape: false)
        ->assertSee('/manifest.webmanifest', escape: false);
});

it('login page sets theme-color meta', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('name="theme-color"', escape: false);
});

it('login page sets apple-mobile-web-app meta tags so iOS treats it as installable', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('name="apple-mobile-web-app-capable"', escape: false)
        ->assertSee('name="apple-mobile-web-app-title"', escape: false);
});

// ===== Graceful failure when files are missing on disk =====

it('returns 404 (not 500) when the manifest file is missing on disk', function () {
    $original = public_path('manifest.webmanifest');
    $stash = $original.'.bak';
    rename($original, $stash);

    try {
        $this->get('/manifest.webmanifest')->assertStatus(404);
    } finally {
        rename($stash, $original);
    }
});

it('returns 404 (not 500) when the service worker file is missing on disk', function () {
    $original = public_path('sw.js');
    $stash = $original.'.bak';
    rename($original, $stash);

    try {
        $this->get('/sw.js')->assertStatus(404);
    } finally {
        rename($stash, $original);
    }
});
