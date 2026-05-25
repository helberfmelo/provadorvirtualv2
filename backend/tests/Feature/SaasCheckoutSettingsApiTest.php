<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasCheckoutSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_choose_checkout_payment_provider(): void
    {
        config()->set('services.mercado_pago.access_token', 'APP_USR-live-token');
        config()->set('services.mercado_pago.public_key', 'APP_USR-live-public');
        config()->set('services.pagarme.secret_key', null);
        config()->set('services.pagarme.public_key', null);

        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/checkout-settings')
            ->assertOk()
            ->assertJsonPath('data.payment_provider', 'mercado_pago')
            ->assertJsonPath('data.active_provider_configured', true)
            ->assertJsonPath('data.providers.0.key', 'mercado_pago');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/saas/checkout-settings', [
                'payment_provider' => 'pagarme',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_provider', 'pagarme')
            ->assertJsonPath('data.active_provider_configured', false);

        $this->assertSame('pagarme', SaasSetting::getValue('checkout.payment_provider'));
    }

    public function test_merchant_cannot_manage_checkout_settings(): void
    {
        $merchant = User::query()->create([
            'name' => 'Lojista',
            'email' => 'lojista.checkout@example.com',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$merchant->createToken('test')->plainTextToken])
            ->getJson('/api/v1/saas/checkout-settings')
            ->assertForbidden();
    }

    private function adminToken(): string
    {
        $admin = User::query()->create([
            'name' => 'Admin SaaS',
            'email' => 'admin.checkout@example.com',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        return $admin->createToken('test')->plainTextToken;
    }
}
