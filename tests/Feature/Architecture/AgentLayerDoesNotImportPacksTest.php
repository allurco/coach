<?php

/**
 * Guard the full Agent ⇄ Pack seam: the Agent layer must not import any
 * Domain Pack class directly. Closed in three refactors —
 *  - PlaceholderRegistry (Coach.php, CoachInteraction.php)
 *  - VerbatimOutput marker (CoachInteraction.php constant removed)
 *  - AgentToolRegistry (CoachAgent.php — pack agent tools now register
 *    factories from FinanceServiceProvider, see ADR 0006)
 *
 * If a new file ever appears under app/Agent/ that pulls a pack class,
 * add it here (and ask whether the pack should push instead).
 */
$agentFiles = [
    'app/Agent/Filament/Pages/Coach.php',
    'app/Agent/Services/CoachInteraction.php',
    'app/Agent/Agents/CoachAgent.php',
];

foreach ($agentFiles as $relative) {
    it("{$relative} does not import any Domain Pack", function () use ($relative) {
        $contents = (string) file_get_contents(base_path($relative));

        expect($contents)->not->toMatch('/^use\s+App\\\\Domains\\\\/m');
    });
}
