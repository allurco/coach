<?php

/**
 * Regression guard for ADR 0006 / the Core→Pack decoupling work.
 *
 * Core must never import a Domain Pack — a fork that disables a pack
 * would otherwise fatal-error on boot. This is scoped to the namespaces
 * already cleaned (Tips, console commands); it widens toward a blanket
 * "no Core/Agent namespace uses App\Domains" ban as the remaining leaks
 * (PlaceholderRenderer, CoachAgent, CoachInteraction) close.
 */
arch('App\Tips does not depend on any Domain Pack')
    ->expect('App\Tips')
    ->not->toUse('App\Domains');

arch('App\Console\Commands does not depend on any Domain Pack')
    ->expect('App\Console\Commands')
    ->not->toUse('App\Domains');
