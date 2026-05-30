<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained('merchant_companies')->nullOnDelete();
            $table->string('source', 32)->default('import');
            $table->string('source_platform', 32)->nullable();
            $table->string('return_reference', 120);
            $table->string('return_reference_hash', 64);
            $table->string('order_reference', 120)->nullable();
            $table->string('order_reference_hash', 64)->nullable();
            $table->string('status', 32)->default('returned');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('total_quantity')->default(0);
            $table->unsignedInteger('refund_amount_cents')->default(0);
            $table->boolean('used_virtual_try_on')->default(false);
            $table->unsignedInteger('assisted_items_count')->default(0);
            $table->unsignedInteger('assisted_refund_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'merchant_company_id', 'return_reference_hash'], 'pv_merchant_returns_unique');
            $table->index(['merchant_id', 'merchant_company_id', 'processed_at'], 'pv_merchant_returns_processed_idx');
        });

        Schema::create('merchant_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_return_id')->constrained('merchant_returns')->cascadeOnDelete();
            $table->foreignId('merchant_order_id')->nullable()->constrained('merchant_orders')->nullOnDelete();
            $table->foreignId('merchant_order_item_id')->nullable()->constrained('merchant_order_items')->nullOnDelete();
            $table->foreignId('recommendation_log_id')->nullable()->constrained('recommendation_logs')->nullOnDelete();
            $table->foreignId('measurement_table_id')->nullable()->constrained('measurement_tables')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('sku', 120)->nullable();
            $table->string('product_name', 180);
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('ordered_size', 60)->nullable();
            $table->string('ideal_size', 60)->nullable();
            $table->string('returned_size', 60)->nullable();
            $table->string('exchanged_to_size', 60)->nullable();
            $table->string('return_reason', 32)->default('unknown');
            $table->string('status', 32)->default('returned');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('refund_amount_cents')->default(0);
            $table->boolean('used_virtual_try_on')->default(false);
            $table->decimal('recommendation_confidence', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_return_id', 'status'], 'pv_merchant_return_items_status_idx');
            $table->index(['merchant_return_id', 'return_reason'], 'pv_merchant_return_items_reason_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_return_items');
        Schema::dropIfExists('merchant_returns');
    }
};
