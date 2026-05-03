<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('label', 200);
            $table->text('summary');
            $table->json('details')->nullable();
            $table->date('event_date')->nullable();
            $table->foreignId('source_action_id')->nullable()->constrained('actions')->nullOnDelete();
            $table->string('source_conversation_id', 36)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'kind']);
            $table->index(['user_id', 'is_active', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_memories');
    }
};
