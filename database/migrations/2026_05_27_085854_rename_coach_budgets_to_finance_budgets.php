<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename `coach_budgets` → `finance_budgets`.
 *
 * Budget is finance-domain data, not agent-owned state. The original
 * `coach_` prefix conflated the product brand with the data; this
 * rename brings the table name in line with the Finance pack that
 * now owns the model.
 *
 * Reserves the `coach_` prefix for genuinely agent-owned tables
 * (e.g. `coach_memories` — the agent's memory). See ADR 0001.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('coach_budgets', 'finance_budgets');
    }

    public function down(): void
    {
        Schema::rename('finance_budgets', 'coach_budgets');
    }
};
