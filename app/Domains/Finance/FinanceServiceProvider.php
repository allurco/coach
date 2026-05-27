<?php

namespace App\Domains\Finance;

use App\Packs\DomainPack;

/**
 * The Finance Domain Pack — Coach's first pack and the reference
 * implementation for the contract defined in CONTEXT.md.
 *
 * Phase 1 (this PR): registers identity only. The pack contributes
 * no models, tools, signals, or UI yet — those move in over the
 * next phases.
 */
class FinanceServiceProvider extends DomainPack
{
    public function label(): string
    {
        return 'finance';
    }
}
