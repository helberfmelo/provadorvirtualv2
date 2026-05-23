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
        Schema::create('platform_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform');
            $table->string('external_store_id')->nullable();
            $table->string('api_base_url')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('webhook_secret_encrypted')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'platform']);
            $table->index(['platform', 'external_store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_connections');
    }
};
