<?php

use App\Tools\Tool;
use App\Tools\ToolRegistry;

function coreTool(string $key): Tool
{
    return new Tool(key: $key, label: "tool.{$key}", icon: 'heroicon-o-square-3-stack-3d', component: "{$key}-tool", scope: 'core');
}

function packTool(string $key, string $scope, bool $primary = false): Tool
{
    return new Tool(key: $key, label: "tool.{$key}", icon: 'heroicon-o-wallet', component: "{$key}-tool", isPrimary: $primary, scope: $scope);
}

it('appends tools via register() and is chainable', function () {
    $registry = (new ToolRegistry)
        ->register(coreTool('plan'))
        ->register(coreTool('contacts'), packTool('budget', 'finance', primary: true));

    expect($registry)->toBeInstanceOf(ToolRegistry::class)
        ->and(collect($registry->all())->pluck('key')->all())
        ->toEqual(['plan', 'contacts', 'budget']);
});

it('lists only core tools via core()', function () {
    $registry = (new ToolRegistry)->register(
        coreTool('plan'),
        coreTool('contacts'),
        packTool('budget', 'finance', primary: true),
    );

    expect(collect($registry->core())->pluck('key')->all())->toEqual(['plan', 'contacts']);
});

it('returns core tools plus the matching pack tools for a goal label', function () {
    $registry = (new ToolRegistry)->register(
        coreTool('plan'),
        coreTool('contacts'),
        packTool('budget', 'finance', primary: true),
        packTool('workout', 'fitness', primary: true),
    );

    expect(collect($registry->forGoalLabel('finance'))->pluck('key')->all())
        ->toEqual(['plan', 'contacts', 'budget'])
        ->and(collect($registry->forGoalLabel('fitness'))->pluck('key')->all())
        ->toEqual(['plan', 'contacts', 'workout']);
});

it('excludes pack tools scoped to other packs', function () {
    $registry = (new ToolRegistry)->register(
        coreTool('plan'),
        packTool('budget', 'finance', primary: true),
    );

    // A fitness goal sees core tools but never the finance-scoped budget.
    expect(collect($registry->forGoalLabel('fitness'))->pluck('key')->all())
        ->toEqual(['plan'])
        ->not->toContain('budget');
});

it('resolves the primary tool for a goal label', function () {
    $registry = (new ToolRegistry)->register(
        coreTool('plan'),
        packTool('budget', 'finance', primary: true),
    );

    expect($registry->primaryFor('finance')?->key)->toBe('budget');
});

it('returns null primary when the active pack has no primary tool', function () {
    $registry = (new ToolRegistry)->register(
        coreTool('plan'),
        coreTool('contacts'),
    );

    // 'general' goals have no pack primary — only core tools in "More".
    expect($registry->primaryFor('general'))->toBeNull();
});

it('treats core tools as non-primary regardless of label', function () {
    $registry = (new ToolRegistry)->register(coreTool('plan'), coreTool('contacts'));

    expect($registry->primaryFor('finance'))->toBeNull();
});
