<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Specialization key (finance, fitness, legal, emotional, health,
            // learning, or 'general'). Drives prompt specialization layer.
            $table->string('label', 32);

            // User-facing name shown in the goal switcher.
            $table->string('name');

            $table->string('color', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_archived', 'sort_order']);
            $table->index(['user_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
