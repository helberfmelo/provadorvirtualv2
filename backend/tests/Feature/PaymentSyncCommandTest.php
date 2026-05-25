<?php

namespace Tests\Feature;

use App\Models\CheckoutSession;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_sync_command_activates_paid_pending_checkout(): void
    {
        config()->set('services.pagarme.secret_key', 'sk_test_sync');
        config()->set('services.pagarme.base_url', 'https://api.pagar.me/core/v5');

        $merchant = Merchant::query()->create([
            'name' => 'Loja Sync',
            'slug' => 'loja-sync',
            'billing_status' => 'pending_payment',
        ]);

        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Sync Ltda',
            'platform' => 'bigshop',
            'status' => 'pending_payment',
        ]);

        CheckoutSession::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'public_reference' => 'sync-paid-ref',
            'plan_code' => 'annual',
            'plan_name' => 'Plano anual',
            'lead_name' => 'Admin Sync',
            'lead_company' => 'Loja Sync Ltda',
            'lead_email' => 'admin.sync@example.com',
            'amount_cents' => 398886,
            'provider' => 'pagarme',
            'provider_order_code' => 'PV-SYNC-PAID',
            'provider_order_id' => 'or_sync_paid',
            'payment_method' => 'pix',
            'status' => CheckoutSession::STATUS_CHECKOUT_CREATED,
            'last_provider_sync_at' => now()->subMinutes(10),
        ]);

        Http::fake([
            'https://api.pagar.me/core/v5/orders/or_sync_paid' => Http::response([
                'id' => 'or_sync_paid',
                'code' => 'PV-SYNC-PAID',
                'status' => 'paid',
                'charges' => [
                    [
                        'id' => 'ch_sync_paid',
                        'status' => 'paid',
                        'payment_method' => 'pix',
                    ],
                ],
            ]),
        ]);

        $this->artisan('pv:payments-sync', ['--limit' => 10])
            ->assertExitCode(0);

        $this->assertDatabaseHas('checkout_sessions', [
            'public_reference' => 'sync-paid-ref',
            'status' => CheckoutSession::STATUS_PAID,
        ]);
        $this->assertDatabaseHas('merchant_companies', [
            'id' => $company->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('merchants', [
            'id' => $merchant->id,
            'billing_status' => 'active',
        ]);
    }
}
