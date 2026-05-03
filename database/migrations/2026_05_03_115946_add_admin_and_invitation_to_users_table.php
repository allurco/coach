<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Allow nullable password so invited users exist without one until
            // they accept the invitation and choose a password.
            $table->string('password')->nullable()->change();

            $table->boolean('is_admin')->default(false)->after('password');
            $table->string('invitation_token', 64)->nullable()->unique()->after('is_admin');
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
            $table->timestamp('accepted_invitation_at')->nullable()->after('invited_at');
        });

        // Promote ALL existing users to admin: pre-multi-tenant they were
        // hand-provisioned (via seeder/tinker), so they all retain user
        // management access after the migration. New invited users start
        // non-admin by default.
        DB::table('users')->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'invitation_token',
                'invited_at',
                'accepted_invitation_at',
            ]);
        });
    }
};
