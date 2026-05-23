<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('public_reference', 64)->unique();
            $table->string('plan_code', 80);
            $table->string('plan_name');
            $table->string('lead_name');
            $table->string('lead_company');
            $table->string('lead_email');
            $table->string('lead_phone')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('provider', 40)->default('pagarme');
            $table->string('provider_order_code')->nullable()->unique();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_charge_id')->nullable()->index();
            $table->string('payment_method', 40)->default('pix');
            $table->string('status', 40)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_provider_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_event_id')->unique();
            $table->string('event_type', 120);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('checkout_sessions');
    }
};
