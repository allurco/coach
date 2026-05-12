{{-- Budget flyout drawer — hero income + 3 editable bucket sections + lazer punchline + footer actions --}}
<div class="plan-overlay"
     x-show="budgetOpen"
     x-transition.opacity.duration.150ms
     @click="budgetOpen = false; $wire.closeBudget()"
     style="display: none;"></div>

<aside class="plan-drawer budget-drawer"
       :class="budgetOpen ? 'is-open' : ''">
    @if ($budgetData)
        <div class="plan-drawer-header drawer-editorial-header">
            <div>
                <div class="drawer-eyebrow">{{ __('coach.budget_flyout.eyebrow') }}</div>
                <div class="drawer-headline">{{ $this->prettyMonth($budgetData['month']) }}</div>
            </div>
            <button type="button" class="plan-close-btn" @click="budgetOpen = false" wire:click="closeBudget">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="budget-flyout-body">
            {{-- Hero: net income — anchor of every other number below. --}}
            <label class="budget-hero">
                <span class="budget-hero-label">{{ __('coach.budget_flyout.net_income') }}</span>
                <span class="budget-hero-row">
                    <span class="budget-hero-prefix">R$</span>
                    <input type="number"
                           step="0.01"
                           class="budget-hero-input"
                           wire:model.live.debounce.400ms="budgetData.net_income"
                           inputmode="decimal">
                </span>
            </label>

            @php
                $bucketSections = [
                    ['key' => 'fixed_costs', 'title' => 'coach.budget_flyout.fixed_costs', 'show_buffer' => true],
                    ['key' => 'investments', 'title' => 'coach.budget_flyout.investments', 'show_buffer' => false],
                    ['key' => 'savings',     'title' => 'coach.budget_flyout.savings',     'show_buffer' => false],
                ];
            @endphp

            @foreach ($bucketSections as $i => $section)
                @php
                    $linesKey = $section['key'].'_lines';
                    $totalKey = $section['key'].'_total';
                    $status = $this->bucketStatus($section['key'], (float) $budgetData[$totalKey]);
                @endphp
                <section class="budget-section" style="--stagger: {{ $i + 1 }}">
                    <header class="budget-section-head">
                        <h3 class="budget-section-title">{{ __($section['title']) }}</h3>
                        @if ($status['target'] !== '')
                            <span class="budget-section-target {{ $status['in_range'] ? 'is-ok' : 'is-off' }}">
                                {{ $status['pct'] }}% <span class="budget-section-target-divider">/</span> {{ $status['target'] }}
                            </span>
                        @endif
                    </header>

                    @forelse ($budgetData[$linesKey] as $idx => $line)
                        <div class="budget-line" wire:key="{{ $section['key'] }}-{{ $idx }}">
                            <input type="text"
                                   class="budget-line-input budget-line-label-input"
                                   wire:model.live.debounce.400ms="budgetData.{{ $linesKey }}.{{ $idx }}.label"
                                   placeholder="{{ __('coach.budget_flyout.line_label_placeholder') }}">
                            <span class="budget-input-prefix">R$</span>
                            <input type="number"
                                   step="0.01"
                                   class="budget-line-input budget-line-amount-input"
                                   wire:model.live.debounce.400ms="budgetData.{{ $linesKey }}.{{ $idx }}.amount"
                                   inputmode="decimal">
                            <button type="button"
                                    class="budget-line-remove"
                                    wire:click="removeBudgetLine('{{ $section['key'] }}', {{ $idx }})"
                                    title="{{ __('coach.budget_flyout.remove_line') }}"
                                    aria-label="{{ __('coach.budget_flyout.remove_line') }}">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                    @empty
                        <div class="budget-line-empty">{{ __('coach.budget_flyout.empty_bucket') }}</div>
                    @endforelse

                    <button type="button" class="budget-add-line" wire:click="addBudgetLine('{{ $section['key'] }}')">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        {{ __('coach.budget_flyout.add_line') }}
                    </button>

                    @if ($section['show_buffer'])
                        <div class="budget-section-total">
                            <div class="budget-section-total-row">
                                <span class="budget-section-total-label">{{ __('coach.budget_flyout.total') }}</span>
                                <span class="budget-section-total-value">R$ {{ number_format($budgetData['fixed_costs_total'], 2, ',', '.') }}</span>
                            </div>
                            <div class="budget-section-total-note">
                                {{ __('coach.budget_flyout.buffer_note', [
                                    'subtotal' => 'R$ '.number_format($budgetData['fixed_costs_subtotal'], 0, ',', '.'),
                                    'buffer' => 'R$ '.number_format($budgetData['fixed_costs_total'] - $budgetData['fixed_costs_subtotal'], 0, ',', '.'),
                                ]) }}
                            </div>
                        </div>
                    @else
                        <div class="budget-section-total">
                            <div class="budget-section-total-row">
                                <span class="budget-section-total-label">{{ __('coach.budget_flyout.total') }}</span>
                                <span class="budget-section-total-value">R$ {{ number_format($budgetData[$totalKey], 2, ',', '.') }}</span>
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach

            {{-- Lazer — the punchline. Income minus everything else. --}}
            @php $leisureStatus = $this->bucketStatus('leisure', (float) $budgetData['leisure_amount']); @endphp
            <section class="budget-leisure {{ $budgetData['leisure_amount'] < 0 ? 'is-deficit' : 'is-surplus' }}" style="--stagger: 4">
                <div class="budget-leisure-head">
                    <span class="budget-leisure-label">{{ __('coach.budget_flyout.leisure') }}</span>
                    @if ($budgetData['leisure_amount'] >= 0 && $leisureStatus['target'] !== '')
                        <span class="budget-section-target {{ $leisureStatus['in_range'] ? 'is-ok' : 'is-off' }}">
                            {{ $leisureStatus['pct'] }}% <span class="budget-section-target-divider">/</span> {{ $leisureStatus['target'] }}
                        </span>
                    @endif
                </div>
                <div class="budget-leisure-value">R$ {{ number_format($budgetData['leisure_amount'], 2, ',', '.') }}</div>

                @if ($budgetData['leisure_amount'] < 0)
                    <div class="budget-deficit-note">
                        {{ __('coach.budget_flyout.deficit_warning', ['amount' => 'R$ '.number_format(abs($budgetData['leisure_amount']), 2, ',', '.')]) }}
                    </div>
                @endif
            </section>

            <div class="budget-flyout-actions">
                <button type="button"
                        class="budget-share-btn"
                        wire:click="openBudgetShare">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    {{ __('coach.budget_flyout.share') }}
                </button>
                <button type="button"
                        class="budget-save-btn"
                        wire:click="saveBudget"
                        wire:loading.attr="disabled"
                        wire:target="saveBudget">
                    <span wire:loading.remove wire:target="saveBudget">{{ __('coach.budget_flyout.save') }}</span>
                    <span wire:loading wire:target="saveBudget" class="btn-spinner"></span>
                </button>
            </div>
        </div>
    @endif
</aside>
