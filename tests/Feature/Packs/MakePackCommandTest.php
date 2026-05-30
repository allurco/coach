<?php

use Illuminate\Support\Facades\File;

/**
 * make:pack scaffolds a new Coach Domain Pack end-to-end — ServiceProvider,
 * lang files, focused test, config wiring. See ADRs 0001 (pack identity =
 * Goal.label) and 0006 (packs self-register into core catalogs); the
 * scaffolded ServiceProvider includes commented hooks for every contribution
 * catalog (ToolRegistry, PlaceholderRegistry, TipResolver, AgentToolRegistry).
 *
 * Tests use a unique pack name and clean up so they don't leave artifacts.
 */
const MP_NAME = 'MakePackTestSubject';
const MP_LABEL = 'makepacktestsubject';

beforeEach(function () {
    File::deleteDirectory(base_path('app/Domains/'.MP_NAME));
    if (File::exists(base_path('tests/Feature/Packs/'.MP_NAME.'PackTest.php'))) {
        File::delete(base_path('tests/Feature/Packs/'.MP_NAME.'PackTest.php'));
    }
    File::copy(config_path('coach.php'), config_path('coach.php.bak'));
});

afterEach(function () {
    File::deleteDirectory(base_path('app/Domains/'.MP_NAME));
    if (File::exists(base_path('tests/Feature/Packs/'.MP_NAME.'PackTest.php'))) {
        File::delete(base_path('tests/Feature/Packs/'.MP_NAME.'PackTest.php'));
    }
    if (File::exists(config_path('coach.php.bak'))) {
        File::move(config_path('coach.php.bak'), config_path('coach.php'));
    }
});

it('creates the pack directory, ServiceProvider, lang files, and test', function () {
    $this->artisan('make:pack', ['name' => MP_NAME])->assertSuccessful();

    $packDir = base_path('app/Domains/'.MP_NAME);
    expect(File::isDirectory($packDir))->toBeTrue()
        ->and(File::exists($packDir.'/'.MP_NAME.'ServiceProvider.php'))->toBeTrue()
        ->and(File::exists($packDir.'/lang/pt_BR/'.MP_LABEL.'.php'))->toBeTrue()
        ->and(File::exists($packDir.'/lang/en/'.MP_LABEL.'.php'))->toBeTrue()
        ->and(File::exists(base_path('tests/Feature/Packs/'.MP_NAME.'PackTest.php')))->toBeTrue();
});

it('generates a ServiceProvider that extends DomainPack with the correct namespace, class, and label', function () {
    $this->artisan('make:pack', ['name' => MP_NAME])->assertSuccessful();

    $contents = (string) File::get(base_path('app/Domains/'.MP_NAME.'/'.MP_NAME.'ServiceProvider.php'));

    expect($contents)
        ->toContain('namespace App\Domains\\'.MP_NAME.';')
        ->toContain('class '.MP_NAME.'ServiceProvider extends DomainPack')
        ->toContain("return '".MP_LABEL."';")
        ->toContain('parent::boot();')
        // The stub should show every contribution hook so the developer
        // knows what catalogs exist (ToolRegistry / PlaceholderRegistry /
        // TipResolver / AgentToolRegistry — ADR 0006).
        ->toContain('ToolRegistry::class')
        ->toContain('PlaceholderRegistry::class')
        ->toContain('TipResolver::class')
        ->toContain('AgentToolRegistry::class');
});

it('registers the new pack in config/coach.php enabled_packs', function () {
    $this->artisan('make:pack', ['name' => MP_NAME])->assertSuccessful();

    $config = (string) File::get(config_path('coach.php'));

    expect($config)->toContain('App\\Domains\\'.MP_NAME.'\\'.MP_NAME.'ServiceProvider::class');
});

it('refuses to overwrite an existing pack', function () {
    $this->artisan('make:pack', ['name' => MP_NAME])->assertSuccessful();

    // Second run should fail without touching the existing pack.
    $this->artisan('make:pack', ['name' => MP_NAME])->assertFailed();
});

it('rejects names that are not StudlyCase', function () {
    $this->artisan('make:pack', ['name' => 'fitness'])->assertFailed();
    $this->artisan('make:pack', ['name' => 'My-Pack'])->assertFailed();
    $this->artisan('make:pack', ['name' => '123Pack'])->assertFailed();
    // None of those created anything to clean up.
    expect(File::isDirectory(base_path('app/Domains/fitness')))->toBeFalse();
});

it('generates a pack test that asserts PackRegistry resolution', function () {
    $this->artisan('make:pack', ['name' => MP_NAME])->assertSuccessful();

    $testContents = (string) File::get(base_path('tests/Feature/Packs/'.MP_NAME.'PackTest.php'));

    expect($testContents)
        ->toContain('use App\Domains\\'.MP_NAME.'\\'.MP_NAME.'ServiceProvider;')
        ->toContain("'".MP_LABEL."'")
        ->toContain('PackRegistry');
});
