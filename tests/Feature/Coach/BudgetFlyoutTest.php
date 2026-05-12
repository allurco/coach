<?php

use App\Filament\Pages\Coach;
use App\Mail\Share;
use App\Models\Budget;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function makeFlyoutBudget(array $overrides = []): Budget
{
    return Budget::create(array_merge([
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 7200,
        'fixed_costs_subtotal' => 3000,
        'fixed_costs_total' => 3450,
        'fixed_costs_breakdown' => ['Aluguel' => 1800, 'Mercado' => 1200],
        'investments_total' => 720,
        'investments_breakdown' => ['Aposentadoria' => 720],
        'savings_total' => 480,
        'savings_breakdown' => ['Emergência' => 480],
        'leisure_amount' => 2550,
    ], $overrides));
}

// hasBudget() — drives whether the header button renders --------------------

it('hasBudget returns false for a brand new user', function () {
    $page = new Coach;

    expect($page->hasBudget())->toBeFalse();
});

it('hasBudget returns true when the user has at least one budget', function () {
    makeFlyoutBudget();
    $page = new Coach;

    expect($page->hasBudget())->toBeTrue();
});

it('hasBudget does not pick up another user\'s budget (multi-tenant)', function () {
    $intruder = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 9999,
        'fixed_costs_subtotal' => 0,
        'fixed_costs_total' => 0,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 9999,
    ]);

    $page = new Coach;

    expect($page->hasBudget())->toBeFalse();
});

// openBudget / closeBudget --------------------------------------------------

it('openBudget loads the current budget into the flyout state', function () {
    $budget = makeFlyoutBudget();
    $page = new Coach;

    $page->openBudget();

    expect($page->budgetOpen)->toBeTrue()
        ->and($page->budgetData)->not->toBeNull()
        ->and($page->budgetData['id'])->toBe($budget->id)
        ->and($page->budgetData['month'])->toBe('2026-06')
        ->and((float) $page->budgetData['net_income'])->toBe(7200.0)
        ->and((float) $page->budgetData['leisure_amount'])->toBe(2550.0);
});

it('openBudget exposes line arrays for all three editable buckets (indexed shape so wire:model can bind cells)', function () {
    makeFlyoutBudget();
    $page = new Coach;

    $page->openBudget();

    expect($page->budgetData['fixed_costs_lines'])->toBe([
        ['label' => 'Aluguel', 'amount' => 1800.0],
        ['label' => 'Mercado', 'amount' => 1200.0],
    ])
        ->and($page->budgetData['investments_lines'])->toBe([['label' => 'Aposentadoria', 'amount' => 720.0]])
        ->and($page->budgetData['savings_lines'])->toBe([['label' => 'Emergência', 'amount' => 480.0]]);
});

// addBudgetLine / removeBudgetLine — Stage 2 editing -----------------------

it('addBudgetLine appends an empty line to a bucket', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();

    $page->addBudgetLine('fixed_costs');

    expect($page->budgetData['fixed_costs_lines'])->toHaveCount(3)
        ->and(end($page->budgetData['fixed_costs_lines']))->toBe(['label' => '', 'amount' => 0.0]);
});

it('addBudgetLine suggests 10% of net_income for investments', function () {
    makeFlyoutBudget(['net_income' => 8000]);
    $page = new Coach;
    $page->openBudget();

    $page->addBudgetLine('investments');

    $added = end($page->budgetData['investments_lines']);
    expect($added['amount'])->toBe(800.0); // 10% of 8000
});

it('addBudgetLine suggests 7% of net_income for savings (midpoint of 5-10% target)', function () {
    makeFlyoutBudget(['net_income' => 10000]);
    $page = new Coach;
    $page->openBudget();

    $page->addBudgetLine('savings');

    $added = end($page->budgetData['savings_lines']);
    expect($added['amount'])->toBe(700.0); // 7% of 10000
});

it('addBudgetLine ignores invalid bucket names', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $before = $page->budgetData['fixed_costs_lines'];

    $page->addBudgetLine('not_a_bucket');

    expect($page->budgetData['fixed_costs_lines'])->toBe($before);
});

it('removeBudgetLine drops the line at the given index', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();

    $page->removeBudgetLine('fixed_costs', 0); // remove Aluguel

    expect($page->budgetData['fixed_costs_lines'])->toBe([
        ['label' => 'Mercado', 'amount' => 1200.0],
    ]);
});

it('removeBudgetLine on out-of-range index is a no-op', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $before = $page->budgetData['fixed_costs_lines'];

    $page->removeBudgetLine('fixed_costs', 99);

    expect($page->budgetData['fixed_costs_lines'])->toBe($before);
});

// recalcBudget — derived fields stay in sync -------------------------------

it('recalcBudget recomputes subtotal, total with buffer, and leisure', function () {
    makeFlyoutBudget(['net_income' => 7000]);
    $page = new Coach;
    $page->openBudget();

    // Simulate the user editing the Aluguel line up to 2000.
    $page->budgetData['fixed_costs_lines'][0]['amount'] = 2000;
    $page->recalcBudget();

    // 2000 + 1200 = 3200 subtotal × 1.15 = 3680 total
    // leisure = 7000 - 3680 - 720 - 480 = 2120
    expect($page->budgetData['fixed_costs_subtotal'])->toBe(3200.0)
        ->and($page->budgetData['fixed_costs_total'])->toBe(3680.0)
        ->and($page->budgetData['leisure_amount'])->toBe(2120.0);
});

it('recalcBudget treats blank-label lines as still counted (recalc on amount only)', function () {
    // recalc is a UX hook: as the user types a value, totals move. Empty
    // labels still count toward the total — the rule that drops blank
    // labels only kicks in on save.
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();

    $page->addBudgetLine('investments'); // adds line with 10% suggestion (720)
    $page->recalcBudget();

    // Original investments_total was 720; new line adds 720 → 1440 total.
    expect($page->budgetData['investments_total'])->toBe(1440.0);
});

// saveBudget — persists a new snapshot -------------------------------------

it('saveBudget persists a new Budget row preserving history', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();

    $page->budgetData['fixed_costs_lines'][0]['amount'] = 2000; // bump Aluguel
    $page->recalcBudget();
    $page->saveBudget();

    expect(Budget::count())->toBe(2); // original + new snapshot

    $newest = Budget::orderByDesc('id')->first();
    expect((float) $newest->fixed_costs_subtotal)->toBe(3200.0)
        ->and((float) $newest->fixed_costs_total)->toBe(3680.0)
        ->and($newest->fixed_costs_breakdown)->toEqual(['Aluguel' => 2000, 'Mercado' => 1200]);
});

it('saveBudget filters out empty-label or zero-amount lines on persist', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();

    $page->budgetData['fixed_costs_lines'][] = ['label' => '', 'amount' => 500];     // empty label
    $page->budgetData['fixed_costs_lines'][] = ['label' => 'Zero', 'amount' => 0];   // zero amount
    $page->saveBudget();

    $newest = Budget::orderByDesc('id')->first();
    expect($newest->fixed_costs_breakdown)->toEqual(['Aluguel' => 1800, 'Mercado' => 1200]);
});

it('saveBudget refreshes budgetData.id to point at the new snapshot', function () {
    $original = makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();

    expect($page->budgetData['id'])->toBe($original->id);

    $page->saveBudget();

    $newest = Budget::orderByDesc('id')->first();
    expect($page->budgetData['id'])->toBe($newest->id)
        ->and($newest->id)->not->toBe($original->id);
});

it('saveBudget keeps multi-tenant scoping (intruder cannot save into another user)', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $page->saveBudget();

    $intruder = User::factory()->create();
    $intruderBudgets = Budget::withoutGlobalScope('owner')
        ->where('user_id', $intruder->id)
        ->count();

    expect($intruderBudgets)->toBe(0);
});

// Stage 3 — share the budget by email --------------------------------------

it('openBudgetShare pre-fills subject + body with the current snapshot placeholder', function () {
    makeFlyoutBudget(['month' => '2026-06']);
    $page = new Coach;
    $page->openBudget();

    $page->openBudgetShare();

    expect($page->budgetShareOpen)->toBeTrue()
        ->and($page->budgetShareSubject)->toContain('2026-06')
        ->and($page->budgetShareBody)->toContain('{{budget:current}}')
        ->and($page->budgetShareRecipient)->toBe('')
        ->and($page->budgetShareError)->toBeNull();
});

it('openBudgetShare no-ops when the flyout is not open (no budgetData)', function () {
    $page = new Coach;

    $page->openBudgetShare();

    expect($page->budgetShareOpen)->toBeFalse();
});

it('cancelBudgetShare wipes the share state', function () {
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $page->openBudgetShare();
    $page->budgetShareRecipient = 'ana@example.com';
    $page->budgetShareError = 'something';

    $page->cancelBudgetShare();

    expect($page->budgetShareOpen)->toBeFalse()
        ->and($page->budgetShareRecipient)->toBe('')
        ->and($page->budgetShareSubject)->toBe('')
        ->and($page->budgetShareBody)->toBe('')
        ->and($page->budgetShareError)->toBeNull();
});

it('confirmBudgetShare sends the Share mailable to a literal email and closes the share modal', function () {
    Mail::fake();
    RateLimiter::clear('share-via-email:'.$this->user->id);
    $this->user->update(['email' => 'me@example.com', 'name' => 'Rogers']);

    makeFlyoutBudget(['month' => '2026-06']);
    $page = new Coach;
    $page->openBudget();
    $page->openBudgetShare();
    $page->budgetShareRecipient = 'ana@example.com';

    $page->confirmBudgetShare();

    Mail::assertSent(Share::class, function (Share $mail) {
        return $mail->hasTo('ana@example.com')
            && str_contains($mail->emailSubject, '2026-06')
            && $mail->senderName === 'Rogers'
            // Body had {{budget:current}} expanded by PlaceholderRenderer.
            && str_contains($mail->body, 'Custos Fixos');
    });

    expect($page->budgetShareOpen)->toBeFalse();
});

it('confirmBudgetShare resolves a saved Contact label to its email', function () {
    Mail::fake();
    RateLimiter::clear('share-via-email:'.$this->user->id);

    Contact::create([
        'label' => 'contador',
        'name' => 'João',
        'email' => 'joao@example.com',
    ]);
    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $page->openBudgetShare();
    $page->budgetShareRecipient = 'contador';

    $page->confirmBudgetShare();

    Mail::assertSent(Share::class, fn (Share $mail) => $mail->hasTo('joao@example.com'));
});

it('confirmBudgetShare auto-bccs the authenticated user', function () {
    Mail::fake();
    RateLimiter::clear('share-via-email:'.$this->user->id);
    $this->user->update(['email' => 'me@example.com']);

    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $page->openBudgetShare();
    $page->budgetShareRecipient = 'ana@example.com';

    $page->confirmBudgetShare();

    Mail::assertSent(Share::class, fn (Share $mail) => $mail->hasBcc('me@example.com'));
});

it('confirmBudgetShare surfaces an error and keeps the modal open on bad recipient', function () {
    Mail::fake();

    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $page->openBudgetShare();
    $page->budgetShareRecipient = 'nope-not-an-email-nor-a-label';

    $page->confirmBudgetShare();

    Mail::assertNothingSent();
    expect($page->budgetShareOpen)->toBeTrue()
        ->and($page->budgetShareError)->not->toBeNull();
});

it('confirmBudgetShare does not resolve another user\'s contact label', function () {
    Mail::fake();
    RateLimiter::clear('share-via-email:'.$this->user->id);

    $intruder = User::factory()->create();
    Contact::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'label' => 'contador',
        'name' => 'Outro',
        'email' => 'outro@example.com',
    ]);

    makeFlyoutBudget();
    $page = new Coach;
    $page->openBudget();
    $page->openBudgetShare();
    $page->budgetShareRecipient = 'contador';

    $page->confirmBudgetShare();

    Mail::assertNothingSent();
    expect($page->budgetShareError)->not->toBeNull();
});

it('openBudget is a no-op when the user has no budget', function () {
    $page = new Coach;

    $page->openBudget();

    expect($page->budgetOpen)->toBeFalse()
        ->and($page->budgetData)->toBeNull();
});

it('closeBudget clears the flyout state', function () {
    makeFlyoutBudget();
    $page = new Coach;

    $page->openBudget();
    $page->closeBudget();

    expect($page->budgetOpen)->toBeFalse()
        ->and($page->budgetData)->toBeNull();
});

it('openBudget never returns another user\'s budget (multi-tenant)', function () {
    $intruder = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 9999,
        'fixed_costs_subtotal' => 0,
        'fixed_costs_total' => 0,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 9999,
    ]);

    $page = new Coach;
    $page->openBudget();

    expect($page->budgetOpen)->toBeFalse()
        ->and($page->budgetData)->toBeNull();
});
