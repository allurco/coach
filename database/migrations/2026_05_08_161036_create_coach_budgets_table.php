<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coach_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot's reference month, e.g. '2026-05'. Allow multiple
            // snapshots per (user, month) — the latest is the canonical one
            // for that month, but we keep history so revisions are visible.
            $table->string('month', 7);

            $table->decimal('net_income', 12, 2);

            // Per-bucket totals + breakdowns. fixed_costs_total INCLUDES the
            // 15% miscellaneous buffer; fixed_costs_subtotal is the raw sum
            // of line items before the buffer.
            $table->decimal('fixed_costs_subtotal', 12, 2)->default(0);
            $table->decimal('fixed_costs_total', 12, 2)->default(0);
            $table->json('fixed_costs_breakdown')->nullable();

            $table->decimal('investments_total', 12, 2)->default(0);
            $table->json('investments_breakdown')->nullable();

            $table->decimal('savings_total', 12, 2)->default(0);
            $table->json('savings_breakdown')->nullable();

            // Calculated: net_income - fixed_costs_total - investments_total
            // - savings_total. Can go negative if buckets exceed income (the
            // tool flags that as a red signal).
            $table->decimal('leisure_amount', 12, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'month']);
            $table->index(['goal_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_budgets');
    }
};
