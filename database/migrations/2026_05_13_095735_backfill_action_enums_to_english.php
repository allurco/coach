<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Translates Action enum columns from Portuguese to English to align with
 * the open-source-ready prompts (PR #48 i18n) — and to fix the latent bug
 * where the prompt suggested `priority=high` but the schema only accepted
 * `alta/media/baixa`.
 *
 * Mapping is exhaustive: each column has a fixed set of accepted values,
 * and every PT value maps 1:1 to an EN value. `down()` reverses by the
 * same mapping.
 *
 * No foreign-key cascades or computed columns reference these strings
 * — they're free identifiers in the app layer, so a plain UPDATE per
 * value is safe and atomic on SQLite/MySQL.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    protected array $forward = [
        'status' => [
            'pendente' => 'pending',
            'em_andamento' => 'in_progress',
            'concluido' => 'completed',
            'cancelado' => 'cancelled',
        ],
        'priority' => [
            'alta' => 'high',
            'media' => 'medium',
            'baixa' => 'low',
        ],
        'category' => [
            'financeiro' => 'financial',
            'fiscal' => 'tax',
            'operacional' => 'operational',
            'crescimento' => 'growth',
        ],
        'importance' => [
            'critico' => 'critical',
            'importante' => 'important',
            'rotineiro' => 'routine',
        ],
        'difficulty' => [
            'rapido' => 'quick',
            'medio' => 'medium',
            'pesado' => 'heavy',
        ],
    ];

    public function up(): void
    {
        foreach ($this->forward as $column => $mapping) {
            foreach ($mapping as $pt => $en) {
                DB::table('actions')
                    ->where($column, $pt)
                    ->update([$column => $en]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->forward as $column => $mapping) {
            foreach ($mapping as $pt => $en) {
                DB::table('actions')
                    ->where($column, $en)
                    ->update([$column => $pt]);
            }
        }
    }
};
