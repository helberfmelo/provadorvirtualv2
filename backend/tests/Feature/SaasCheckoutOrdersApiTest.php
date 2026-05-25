<?php

namespace Tests\Feature;

use App\Models\CheckoutAcceptance;
use App\Models\CheckoutSession;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasCheckoutOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_checkout_orders_including_failed_attempts(): void
    {
        [$paid, $failed] = $this->createCheckoutSessions();

        $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken()])
            ->getJson('/api/v1/saas/checkout-orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $failed->id)
            ->assertJsonPath('data.0.status', CheckoutSession::STATUS_FAILED)
            ->assertJsonPath('data.0.failure_reason', 'Cartão recusado pela operadora.')
            ->assertJsonPath('data.1.id', $paid->id)
            ->assertJsonPath('data.1.status', CheckoutSession::STATUS_PAID);
    }

    public function test_admin_can_view_full_checkout_order_detail(): void
    {
        [, $failed] = $this->createCheckoutSessions();

        $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken()])
            ->getJson('/api/v1/saas/checkout-orders/'.$failed->id)
            ->assertOk()
            ->assertJsonPath('data.id', $failed->id)
            ->assertJsonPath('data.failure.message', 'Cartão recusado pela operadora.')
            ->assertJsonPath('data.acceptance.ip_address', '127.0.0.1')
            ->assertJsonPath('data.company.document', '11222333000181')
            ->assertJsonPath('data.provider.order_code', 'PV-FAILED');
    }

    public function test_merchant_cannot_view_saas_checkout_orders(): void
    {
        $merchant = User::query()->create([
            'name' => 'Lojista',
            'email' => 'lojista.orders@example.com',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$merchant->createToken('test')->plainTextToken])
            ->getJson('/api/v1/saas/checkout-orders')
            ->assertForbidden();
    }

    private function createCheckoutSessions(): array
    {
        $merchant = Merchant::query()->create([
            'name' => 'Loja Pedidos',
            'slug' => 'loja-pedidos',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Pedidos',
            'legal_name' => 'Loja Pedidos Ltda',
            'document' => '11222333000181',
            'platform' => 'bigshop',
            'status' => 'active',
        ]);
        $user = User::query()->create([
            'name' => 'Cliente Pedidos',
            'email' => 'cliente.orders@example.com',
            'cpf' => '05521345620',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);

        $paid = CheckoutSession::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'public_reference' => 'paid-reference',
            'plan_code' => 'annual',
            'plan_name' => 'Provador Virtual Anual',
            'lead_name' => 'Cliente Pedidos',
            'lead_company' => 'Loja Pedidos',
            'lead_email' => 'cliente.orders@example.com',
            'lead_phone' => '11999990000',
            'amount_cents' => 398886,
            'currency' => 'BRL',
            'provider' => 'mercado_pago',
            'provider_order_code' => 'PV-PAID',
            'provider_order_id' => '998877',
            'provider_charge_id' => '998877',
            'payment_method' => 'pix',
            'status' => CheckoutSession::STATUS_PAID,
            'metadata' => [
                'payment_snapshot' => [
                    'method' => 'pix',
                    'status' => 'approved',
                ],
            ],
            'paid_at' => now(),
        ]);

        $failed = CheckoutSession::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'public_reference' => 'failed-reference',
            'plan_code' => 'monthly',
            'plan_name' => 'Provador Virtual Mensal',
            'lead_name' => 'Cliente Pedidos',
            'lead_company' => 'Loja Pedidos',
            'lead_email' => 'cliente.orders@example.com',
            'lead_phone' => '11999990000',
            'amount_cents' => 38980,
            'currency' => 'BRL',
            'provider' => 'mercado_pago',
            'provider_order_code' => 'PV-FAILED',
            'payment_method' => 'credit_card',
            'status' => CheckoutSession::STATUS_FAILED,
            'metadata' => [
                'failure' => [
                    'message' => 'Cartão recusado pela operadora.',
                    'failed_at' => now()->toISOString(),
                ],
            ],
        ]);

        foreach ([$paid, $failed] as $session) {
            CheckoutAcceptance::query()->create([
                'checkout_session_id' => $session->id,
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company->id,
                'user_id' => $user->id,
                'lead_email' => 'cliente.orders@example.com',
                'company_document' => '11222333000181',
                'terms_version' => '2026-05-25',
                'privacy_version' => '2026-05-25',
                'accepted_terms' => true,
                'accepted_at' => now(),
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'metadata' => [
                    'payment_method' => $session->payment_method,
                ],
            ]);
        }

        return [$paid, $failed];
    }

    private function adminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'Admin Pedidos',
            'email' => 'admin.orders@example.com',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        return $admin->createToken('test')->plainTextToken;
    }
}
