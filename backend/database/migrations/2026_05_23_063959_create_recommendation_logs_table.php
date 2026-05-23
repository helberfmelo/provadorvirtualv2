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
        Schema::create('recommendation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommendation_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recommended_size')->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->json('input_measurements')->nullable();
            $table->json('score_breakdown')->nullable();
            $table->json('fit_notes')->nullable();
            $table->json('warnings')->nullable();
            $table->string('status')->default('demo');
            $table->timestamps();

            $table->index(['merchant_id', 'product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommendation_logs');
    }
};
