<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add user_id as nullable so existing rows survive the column add.
        Schema::table('actions', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // 2. Backfill: every existing row belongs to the single admin user.
        // The whole app was single-tenant before this migration, so the first
        // user (created by the seeder) is the rightful owner.
        $firstUserId = User::orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('actions')->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }

        // 3. Now enforce NOT NULL — no orphaned rows allowed going forward.
        Schema::table('actions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
