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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('measurement_table_id')->nullable()->index();
            $table->string('external_product_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('gender')->nullable();
            $table->string('fit_profile')->nullable();
            $table->string('status')->default('active');
            $table->string('image_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'merchant_company_id']);
            $table->index(['external_product_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
