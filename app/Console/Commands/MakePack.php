<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('make:pack {name : The pack name in StudlyCase (e.g. "Fitness")}')]
#[Description('Scaffold a new Coach Domain Pack — ServiceProvider with all four contribution hooks wired, lang files, smoke test, and config registration.')]
class MakePack extends Command
{
    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->error("Pack name must be StudlyCase letters/digits (e.g. \"Fitness\"). Got: \"{$name}\".");

            return self::FAILURE;
        }

        $label = strtolower($name);
        $packDir = base_path("app/Domains/{$name}");
        $testPath = base_path("tests/Feature/Packs/{$name}PackTest.php");

        if (File::isDirectory($packDir)) {
            $this->error("Pack \"{$name}\" already exists at app/Domains/{$name}/.");

            return self::FAILURE;
        }

        $replacements = ['{{ name }}' => $name, '{{ label }}' => $label];

        // ServiceProvider
        File::ensureDirectoryExists($packDir);
        File::put(
            "{$packDir}/{$name}ServiceProvider.php",
            $this->stub('ServiceProvider.stub', $replacements),
        );

        // Lang files (both locales — CLAUDE.md mandate)
        File::ensureDirectoryExists("{$packDir}/lang/pt_BR");
        File::ensureDirectoryExists("{$packDir}/lang/en");
        $langContent = $this->stub('lang.stub', $replacements);
        File::put("{$packDir}/lang/pt_BR/{$label}.php", $langContent);
        File::put("{$packDir}/lang/en/{$label}.php", $langContent);

        // Smoke test
        File::ensureDirectoryExists(dirname($testPath));
        File::put($testPath, $this->stub('test.stub', $replacements));

        // Config wiring — append the new pack to enabled_packs.
        $this->registerInConfig($name);

        $this->info("Pack \"{$name}\" created.");
        $this->newLine();
        $this->line("  Created: app/Domains/{$name}/{$name}ServiceProvider.php");
        $this->line("  Created: app/Domains/{$name}/lang/{pt_BR,en}/{$label}.php");
        $this->line("  Created: tests/Feature/Packs/{$name}PackTest.php");
        $this->line('  Hooked into: config/coach.php (enabled_packs)');
        $this->newLine();
        $this->line('  Next: add your first Tool, Placeholder, Tip, or AgentTool — every catalog is pre-wired in the ServiceProvider as a commented hook.');

        return self::SUCCESS;
    }

    /**
     * Read a stub file and apply {{ name }} / {{ label }} replacements.
     */
    protected function stub(string $name, array $replacements): string
    {
        $contents = (string) File::get(base_path("stubs/pack/{$name}"));

        return strtr($contents, $replacements);
    }

    /**
     * Append the new pack's FQCN to config/coach.php's enabled_packs array.
     * Uses a regex anchored to the closing bracket of that array so the
     * surrounding config layout stays intact. Fully-qualified — no `use`
     * import edits needed at the top of the file.
     */
    protected function registerInConfig(string $name): void
    {
        $configPath = config_path('coach.php');
        $contents = (string) File::get($configPath);
        $fqcn = "\\App\\Domains\\{$name}\\{$name}ServiceProvider";

        // Match: 'enabled_packs' => [ <anything-not-bracket> ],
        // and inject our line just before the closing ],
        $updated = preg_replace(
            "/('enabled_packs'\s*=>\s*\[)([^\]]*?)(\s*\],)/s",
            "$1$2\n        {$fqcn}::class,$3",
            $contents,
            1,
        );

        if ($updated === null || $updated === $contents) {
            $this->warn('Could not auto-register the pack in config/coach.php — add manually:');
            $this->line("    {$fqcn}::class,");

            return;
        }

        File::put($configPath, $updated);
    }
}
