<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('platform_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform')->default('custom');
            $table->string('event_type');
            $table->string('direction')->default('inbound');
            $table->string('status')->default('received');
            $table->json('summary')->nullable();
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['merchant_id', 'platform']);
            $table->index(['event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
    }
};
