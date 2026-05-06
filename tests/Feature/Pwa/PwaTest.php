<?php

it('serves the web app manifest at /manifest.webmanifest', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/manifest+json');

    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest)
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

    $script = file_get_contents(public_path('sw.js'));
    expect($script)->toContain('install');
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
