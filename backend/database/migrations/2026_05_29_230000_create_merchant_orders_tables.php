<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('merchant_orders')) {
            Schema::create('merchant_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
                $table->string('source')->default('csv');
                $table->string('source_platform')->nullable();
                $table->string('order_reference');
                $table->string('order_reference_hash', 64);
                $table->string('status')->default('paid');
                $table->timestamp('ordered_at')->nullable();
                $table->unsignedInteger('items_count')->default(0);
                $table->unsignedInteger('total_quantity')->default(0);
                $table->unsignedBigInteger('total_amount_cents')->default(0);
                $table->string('currency', 8)->default('BRL');
                $table->boolean('used_virtual_try_on')->default(false);
                $table->unsignedInteger('assisted_items_count')->default(0);
                $table->unsignedBigInteger('assisted_revenue_cents')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['merchant_id', 'merchant_company_id', 'order_reference_hash'], 'merchant_orders_reference_unique');
                $table->index(['merchant_id', 'merchant_company_id', 'ordered_at'], 'merchant_orders_company_date_idx');
                $table->index(['merchant_id', 'merchant_company_id', 'status'], 'merchant_orders_company_status_idx');
            });
        }

        if (! Schema::hasTable('merchant_order_items')) {
            Schema::create('merchant_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('merchant_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('recommendation_log_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('measurement_table_id')->nullable()->constrained()->nullOnDelete();
                $table->string('sku')->nullable();
                $table->string('product_name');
                $table->string('ordered_size')->nullable();
                $table->string('recommended_size')->nullable();
                $table->decimal('recommendation_confidence', 5, 2)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedBigInteger('unit_price_cents')->default(0);
                $table->unsignedBigInteger('line_total_cents')->default(0);
                $table->boolean('used_virtual_try_on')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['merchant_order_id', 'sku'], 'merchant_order_items_order_sku_idx');
                $table->index(['product_id', 'product_variant_id'], 'merchant_order_items_product_variant_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_order_items');
        Schema::dropIfExists('merchant_orders');
    }
};
