<?php

/**
 * Guard the seam closed by the PlaceholderRegistry refactor: the
 * Agent layer's user-facing chat surfaces (the Filament Coach page
 * and CoachInteraction) must not import any Domain Pack directly.
 *
 * CoachAgent.php is intentionally NOT covered here — its remaining
 * BudgetSnapshot import is the agent-tool-registration leak that a
 * sibling refactor closes via a tool contribution registry.
 */
it('Coach Filament page does not import any Domain Pack', function () {
    $path = base_path('app/Agent/Filament/Pages/Coach.php');
    $contents = (string) file_get_contents($path);

    expect($contents)->not->toMatch('/^use\s+App\\\\Domains\\\\/m');
});

it('CoachInteraction does not import any Domain Pack', function () {
    $path = base_path('app/Agent/Services/CoachInteraction.php');
    $contents = (string) file_get_contents($path);

    expect($contents)->not->toMatch('/^use\s+App\\\\Domains\\\\/m');
});
