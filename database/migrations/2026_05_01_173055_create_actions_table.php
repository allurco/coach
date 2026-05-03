<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('financeiro');
            $table->string('priority')->default('media');
            $table->string('importance')->default('importante');
            $table->string('difficulty')->default('medio');
            $table->date('deadline')->nullable();
            $table->string('status')->default('pendente');
            $table->timestamp('completed_at')->nullable();
            $table->text('result_notes')->nullable();
            $table->date('snooze_until')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
