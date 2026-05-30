<?php

namespace Tests\Feature;

use App\Models\BillingSubscription;
use App\Models\CheckoutSession;
use App\Models\IntegrationChangeRequest;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_summary_includes_plan_company_payment_links_and_commercial_history(): void
    {
        [$token, $subscription, $checkout] = $this->subscriptionFixture();
        $changeRequest = IntegrationChangeRequest::query()->create([
            'merchant_id' => $checkout->merchant_id,
            'merchant_company_id' => $checkout->merchant_company_id,
            'user_id' => $checkout->user_id,
            'from_platform' => 'bigshop',
            'to_platform' => 'shopify',
            'status' => IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
            'terms_version' => 'bigshop-change-2026-05-29',
            'terms_accepted_at' => now()->subDay(),
            'requested_at' => now()->subDay(),
            'payment_link' => 'https://provadorvirtual.online/checkout?upgrade=loja-recorrencia',
            'metadata' => [
                'financial_summary' => [
                    'short_text' => 'Diferença estimada sob revisão comercial.',
                ],
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.company.company.platform_label', 'BigShop')
            ->assertJsonPath('data.company.company.commercial_status', 'bigshop_benefit')
            ->assertJsonPath('data.plan.plan_code', 'monthly')
            ->assertJsonPath('data.plan.billing_cycle_label', 'Mensal')
            ->assertJsonPath('data.subscription.id', $subscription->id)
            ->assertJsonPath('data.payment_links.0.access.type', 'checkout_status')
            ->assertJsonPath('data.commercial_requests.0.id', $changeRequest->id)
            ->assertJsonPath('data.commercial_requests.0.payment_link_available', true)
            ->assertJsonPath('data.commercial_requests.0.payment_link_access.type', 'commercial_request')
            ->assertJsonPath('data.actions.financial_changes_managed_by', 'admin');
    }

    public function test_merchant_can_disable_future_auto_renewal_without_canceling_paid_checkout(): void
    {
        [$token, $subscription, $checkout] = $this->subscriptionFixture();
        config()->set('services.mercado_pago.access_token', 'APP_USR-test-token');
        config()->set('services.mercado_pago.base_url', 'https://api.mercadopago.com');

        Http::fake([
            'https://api.mercadopago.com/preapproval/preapproval_123' => Http::response([
                'id' => 'preapproval_123',
                'status' => 'canceled',
                'external_reference' => $checkout->public_reference,
                'next_payment_date' => now()->addMonth()->toIso8601String(),
                'date_created' => now()->subDay()->toIso8601String(),
                'auto_recurring' => [
                    'frequency' => 1,
                    'frequency_type' => 'months',
                    'transaction_amount' => 489.8,
                    'currency_id' => 'BRL',
                ],
            ]),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription.id', $subscription->id)
            ->assertJsonPath('data.subscription.auto_renewal_enabled', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/billing/subscription/auto-renewal', [
                'auto_renewal_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.auto_renewal_enabled', false)
            ->assertJsonPath('data.status', 'canceled');

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://api.mercadopago.com/preapproval/preapproval_123'
                && data_get($request->data(), 'status') === 'canceled';
        });

        $subscription->refresh();
        $checkout->refresh();

        $this->assertFalse($subscription->auto_renewal_enabled);
        $this->assertNotNull($subscription->cancel_requested_at);
        $this->assertSame(CheckoutSession::STATUS_PAID, $checkout->status);
        $this->assertDatabaseHas('merchants', [
            'id' => $checkout->merchant_id,
            'billing_status' => 'active',
        ]);
    }

    public function test_merchant_can_resolve_checkout_status_link_with_audit(): void
    {
        [$token, $subscription, $checkout] = $this->subscriptionFixture();
        $expectedUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/')
            .'/checkout/sucesso?ref=checkout-recorrencia-ref';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/billing/payment-links/resolve', [
                'type' => 'checkout_status',
                'id' => $checkout->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Ver comprovante da contratação')
            ->assertJsonPath('data.url', $expectedUrl);

        $this->assertDatabaseHas('audit_logs', [
            'merchant_id' => $checkout->merchant_id,
            'merchant_company_id' => $checkout->merchant_company_id,
            'event' => 'billing.payment_link_opened',
            'auditable_type' => $checkout->getMorphClass(),
            'auditable_id' => $checkout->id,
        ]);
    }

    public function test_merchant_can_resolve_commercial_payment_link_with_audit(): void
    {
        [$token, $subscription, $checkout] = $this->subscriptionFixture();
        $changeRequest = IntegrationChangeRequest::query()->create([
            'merchant_id' => $checkout->merchant_id,
            'merchant_company_id' => $checkout->merchant_company_id,
            'user_id' => $checkout->user_id,
            'from_platform' => 'bigshop',
            'to_platform' => 'shopify',
            'status' => IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
            'terms_version' => 'bigshop-change-2026-05-29',
            'terms_accepted_at' => now(),
            'requested_at' => now(),
            'payment_link' => 'https://provadorvirtual.online/checkout?upgrade=loja-recorrencia',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/billing/payment-links/resolve', [
                'type' => 'commercial_request',
                'id' => $changeRequest->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Abrir link comercial')
            ->assertJsonPath('data.url', 'https://provadorvirtual.online/checkout?upgrade=loja-recorrencia');

        $this->assertDatabaseHas('audit_logs', [
            'merchant_id' => $checkout->merchant_id,
            'merchant_company_id' => $checkout->merchant_company_id,
            'event' => 'billing.payment_link_opened',
            'auditable_type' => $changeRequest->getMorphClass(),
            'auditable_id' => $changeRequest->id,
        ]);
    }

    private function subscriptionFixture(): array
    {
        $user = User::query()->create([
            'name' => 'Lojista Recorrencia',
            'email' => 'recorrencia@example.com',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);
        $merchant = Merchant::query()->create([
            'name' => 'Loja Recorrencia',
            'slug' => 'loja-recorrencia',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Recorrencia Ltda',
            'document' => '11222333000181',
            'platform' => 'bigshop',
            'bigshop_discount_active' => true,
            'status' => 'active',
        ]);
        $user->merchants()->attach($merchant->id, ['role' => 'owner', 'is_owner' => true]);

        $checkout = CheckoutSession::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'public_reference' => 'checkout-recorrencia-ref',
            'plan_code' => 'monthly',
            'plan_name' => 'Provador Virtual Mensal',
            'lead_name' => $user->name,
            'lead_company' => $company->name,
            'lead_email' => $user->email,
            'amount_cents' => 48980,
            'provider' => 'mercado_pago',
            'provider_order_code' => 'PV-MP-SUB-TEST',
            'provider_order_id' => 'preapproval_123',
            'payment_method' => 'credit_card',
            'status' => CheckoutSession::STATUS_PAID,
            'paid_at' => now(),
            'metadata' => [
                'plan' => [
                    'billing_cycle' => 'monthly',
                    'interval_months' => 1,
                ],
            ],
        ]);

        $subscription = BillingSubscription::query()->create([
            'checkout_session_id' => $checkout->id,
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'provider' => 'mercado_pago',
            'provider_subscription_id' => 'preapproval_123',
            'plan_code' => 'monthly',
            'billing_cycle' => 'monthly',
            'payment_method' => 'credit_card',
            'status' => 'authorized',
            'auto_renewal_enabled' => true,
            'amount_cents' => 48980,
            'currency' => 'BRL',
            'next_charge_at' => now()->addMonth(),
            'started_at' => now()->subDay(),
        ]);

        $token = $user->createToken('test', [
            'role:merchant',
            'merchant:'.$merchant->id,
            'company:'.$company->id,
        ])->plainTextToken;

        return [$token, $subscription, $checkout];
    }
}
