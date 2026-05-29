<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('measurement_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recommendation_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 50);
            $table->string('client_event_id', 160);
            $table->string('platform', 40)->nullable();
            $table->string('device_type', 20)->default('desktop');
            $table->string('session_key', 80)->nullable();
            $table->string('visit_key', 80)->nullable();
            $table->string('selected_size', 40)->nullable();
            $table->string('product_name', 160)->nullable();
            $table->string('measurement_table_name', 160)->nullable();
            $table->string('brand_label', 120)->nullable();
            $table->string('category_label', 120)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->unique(['merchant_id', 'client_event_id'], 'wue_merchant_client_unique');
            $table->index(['merchant_id', 'merchant_company_id', 'occurred_at'], 'wue_merchant_company_time_idx');
            $table->index(['merchant_id', 'event_name', 'occurred_at'], 'wue_merchant_event_time_idx');
            $table->index(['merchant_id', 'product_id', 'occurred_at'], 'wue_merchant_product_time_idx');
            $table->index(['merchant_id', 'measurement_table_id', 'occurred_at'], 'wue_merchant_table_time_idx');
            $table->index(['merchant_id', 'platform', 'device_type', 'occurred_at'], 'wue_merchant_platform_device_idx');
            $table->index(['merchant_id', 'brand_label', 'occurred_at'], 'wue_merchant_brand_time_idx');
            $table->index(['merchant_id', 'category_label', 'occurred_at'], 'wue_merchant_category_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_usage_events');
    }
};
