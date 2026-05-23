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
        Schema::create('recommendation_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_log_id')->constrained()->cascadeOnDelete();
            $table->boolean('was_helpful')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('selected_size')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_feedbacks');
    }
};
