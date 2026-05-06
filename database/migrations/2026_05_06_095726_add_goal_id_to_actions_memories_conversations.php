<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add goal_id columns nullable so existing rows survive.
        Schema::table('actions', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->index(['goal_id', 'status']);
        });

        Schema::table('coach_memories', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->index(['goal_id', 'kind']);
        });

        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->index('goal_id');
        });

        // 2. Backfill: every existing user gets a default Goal. Pick the
        //    label from any existing kind='goal' memory they had (oldest one),
        //    falling back to 'general' if none.
        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            $existingGoalLabel = DB::table('coach_memories')
                ->where('user_id', $userId)
                ->where('kind', 'goal')
                ->where('is_active', true)
                ->orderBy('created_at')
                ->value('label');

            $label = $existingGoalLabel ?: 'general';
            $name = match ($label) {
                'finance' => 'Vida financeira',
                'legal' => 'Jurídico/Fiscal',
                'emotional' => 'Saúde emocional',
                'health' => 'Saúde',
                'fitness' => 'Atividade física',
                'learning' => 'Aprendizado',
                default => 'Geral',
            };

            $goalId = DB::table('goals')->insertGetId([
                'user_id' => $userId,
                'label' => $label,
                'name' => $name,
                'sort_order' => 0,
                'is_archived' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Assign existing rows owned by this user to the new goal.
            //    Profile facts (kind='perfil') stay shared (goal_id=null) since
            //    they describe who the person is, not a specific area.
            DB::table('actions')
                ->where('user_id', $userId)
                ->whereNull('goal_id')
                ->update(['goal_id' => $goalId]);

            DB::table('coach_memories')
                ->where('user_id', $userId)
                ->where('kind', '!=', 'perfil')
                ->whereNull('goal_id')
                ->update(['goal_id' => $goalId]);

            DB::table('agent_conversations')
                ->where('user_id', $userId)
                ->whereNull('goal_id')
                ->update(['goal_id' => $goalId]);
        }

        // 4. Enforce NOT NULL on actions only.
        //    - coach_memories.goal_id stays NULLABLE: null means "shared
        //      across all goals" (perfil facts, cross-cutting metas).
        //    - agent_conversations.goal_id stays NULLABLE: rows are created
        //      by the laravel/ai package which we can't easily hook, so we
        //      backfill them post-creation in our service layer.
        Schema::table('actions', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('goal_id');
        });

        Schema::table('coach_memories', function (Blueprint $table) {
            $table->dropIndex(['goal_id', 'kind']);
            $table->dropConstrainedForeignId('goal_id');
        });

        Schema::table('actions', function (Blueprint $table) {
            $table->dropIndex(['goal_id', 'status']);
            $table->dropConstrainedForeignId('goal_id');
        });
    }
};
