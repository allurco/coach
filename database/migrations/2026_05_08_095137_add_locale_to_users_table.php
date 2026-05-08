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
        Schema::table('users', function (Blueprint $table) {
            // Stores the user's preferred locale (e.g. 'pt_BR', 'en').
            // Set at invite time by the admin; reused for the invitation
            // email AND applied on every authenticated request so the UI
            // matches the user's language regardless of APP_LOCALE.
            $table->string('locale', 12)->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
