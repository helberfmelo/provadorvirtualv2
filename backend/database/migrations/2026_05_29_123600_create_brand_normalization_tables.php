<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('normalized_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'name']);
        });

        Schema::create('merchant_brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('normalized_brand_id')->nullable()->constrained('normalized_brands')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('source')->default('manual');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'merchant_company_id']);
            $table->index(['merchant_id', 'slug']);
            $table->index(['normalized_brand_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_brands');
        Schema::dropIfExists('normalized_brands');
    }
};
