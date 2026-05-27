<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill vestigial Action.category values to `general`.
 *
 * Before Phase 5, Action::CATEGORIES enumerated `financial`, `tax`,
 * `operational`, `growth` — leftover PJ-business categories from the
 * project's original framing. Phase 5 shrinks the enum to `general`
 * only; pack-specific categories will come back through a future
 * pack contribution mechanism.
 *
 * `operational` and `growth` are PJ-business artefacts that don't
 * correspond to any current or planned Domain Pack — backfill them
 * to `general` so they're not orphans.
 *
 * `financial` and `tax` rows are LEFT UNTOUCHED. They map to the
 * Finance pack (already shipped) and a future Legal pack respectively,
 * and represent meaningful user categorization. The DB column has no
 * enum constraint, so they continue to work even though they're not
 * in Action::CATEGORIES today.
 *
 * down() restores `general` rows to `operational` — best-effort only,
 * since we can't recover the lost distinction between `operational`
 * and `growth`. Acceptable: this migration is forward-only in practice.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('actions')
            ->whereIn('category', ['operational', 'growth'])
            ->update(['category' => 'general']);
    }

    public function down(): void
    {
        DB::table('actions')
            ->where('category', 'general')
            ->update(['category' => 'operational']);
    }
};
