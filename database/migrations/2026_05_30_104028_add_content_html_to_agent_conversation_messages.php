<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache the rendered Markdown + placeholder-expanded HTML alongside the
     * raw assistant content so loading a conversation skips the per-message
     * Str::markdown + PlaceholderRenderer work. Coach::loadConversation acts
     * as a write-through cache: if content_html is null (legacy row or freshly
     * inserted by the laravel/ai SDK), it renders and persists it once.
     */
    public function up(): void
    {
        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->text('content_html')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversation_messages', function (Blueprint $table) {
            $table->dropColumn('content_html');
        });
    }
};
