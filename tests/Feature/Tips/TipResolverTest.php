<?php

use App\Models\Goal;
use App\Models\User;
use App\Services\TipResolver;
use App\Tips\Tip;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * A throwaway Tip implementation used to exercise the resolver without
 * depending on the real catalog — keeps these tests focused on the
 * resolver's selection logic rather than any one tip's predicate.
 */
function fakeTip(string $id, int $priority, bool $applies): Tip
{
    return new class($id, $priority, $applies) extends Tip
    {
        public function __construct(
            protected string $tipId,
            protected int $tipPriority,
            protected bool $tipApplies,
        ) {}

        public function id(): string
        {
            return $this->tipId;
        }

        public function priority(): int
        {
            return $this->tipPriority;
        }

        public function applies(User $user, ?Goal $goal): bool
        {
            return $this->tipApplies;
        }

        public function title(): string
        {
            return "Title for {$this->tipId}";
        }

        public function prompt(): string
        {
            return "Prompt for {$this->tipId}";
        }
    };
}

it('returns null when no tips apply', function () {
    $resolver = new TipResolver([
        fakeTip('a', 10, applies: false),
        fakeTip('b', 5, applies: false),
    ]);

    expect($resolver->pick($this->user, null))->toBeNull();
});

it('returns the only applicable tip', function () {
    $resolver = new TipResolver([
        fakeTip('a', 10, applies: false),
        fakeTip('b', 5, applies: true),
    ]);

    $tip = $resolver->pick($this->user, null);

    expect($tip?->id())->toBe('b');
});

it('returns the highest-priority applicable tip', function () {
    $resolver = new TipResolver([
        fakeTip('low', 1, applies: true),
        fakeTip('high', 100, applies: true),
        fakeTip('mid', 50, applies: true),
    ]);

    $tip = $resolver->pick($this->user, null);

    expect($tip?->id())->toBe('high');
});

it('skips dismissed tips', function () {
    $resolver = new TipResolver([
        fakeTip('a', 10, applies: true),
        fakeTip('b', 5, applies: true),
    ]);

    $tip = $resolver->pick($this->user, null, dismissed: ['a']);

    expect($tip?->id())->toBe('b');
});

it('returns null when every applicable tip is dismissed', function () {
    $resolver = new TipResolver([
        fakeTip('a', 10, applies: true),
        fakeTip('b', 5, applies: true),
    ]);

    expect($resolver->pick($this->user, null, dismissed: ['a', 'b']))->toBeNull();
});

it('passes the active goal into applies()', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    $seenGoal = null;
    $tip = new class($seenGoal) extends Tip
    {
        public function __construct(public ?Goal &$seenGoal) {}

        public function id(): string
        {
            return 'inspect';
        }

        public function priority(): int
        {
            return 1;
        }

        public function applies(User $user, ?Goal $goal): bool
        {
            $this->seenGoal = $goal;

            return true;
        }

        public function title(): string
        {
            return 't';
        }

        public function prompt(): string
        {
            return 'p';
        }
    };

    (new TipResolver([$tip]))->pick($this->user, $goal);

    expect($seenGoal?->id)->toBe($goal->id);
});
