<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checkout_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('provider_subscription_id', 120)->nullable();
            $table->string('provider_payment_id', 120)->nullable();
            $table->string('plan_code', 80);
            $table->string('billing_cycle', 40);
            $table->string('payment_method', 40)->default('credit_card');
            $table->string('status', 40)->default('pending')->index();
            $table->boolean('auto_renewal_enabled')->default(true);
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_provider_sync_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_subscription_id'], 'billing_subs_provider_subscription_unique');
            $table->index(['merchant_id', 'merchant_company_id', 'status'], 'billing_subs_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
