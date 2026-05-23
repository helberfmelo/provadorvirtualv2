<?php

namespace Tests\Feature;

use App\Models\IntegrationEvent;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\User;
use App\Models\WidgetInstall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BigShopActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bigshop_can_activate_store_with_signed_payload(): void
    {
        config(['services.bigshop.activation_secret' => 'secret-one-click']);

        $payload = [
            'store_id' => 'store-one-click',
            'store_name' => 'Loja Um Clique',
            'store_url' => 'https://lojaumclique.com.br/produtos',
            'merchant' => [
                'email' => 'owner@lojaumclique.com.br',
                'name' => 'Loja Um Clique',
            ],
            'api_base_url' => 'https://api.bigshop.test',
            'access_token' => 'token-one-click',
            'webhook_secret' => 'webhook-one-click',
        ];

        $this->callSignedActivation($payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'configured')
            ->assertJsonPath('data.widget_url', url('/widget/v1/provador-virtual.js'));

        $user = User::query()->where('email', 'owner@lojaumclique.com.br')->firstOrFail();
        $this->assertTrue($user->merchants()->where('slug', 'bigshop-store-one-click')->exists());

        $this->assertDatabaseHas('merchant_companies', [
            'external_store_id' => 'store-one-click',
            'platform' => 'bigshop',
            'domain' => 'lojaumclique.com.br',
        ]);

        $company = MerchantCompany::query()->where('external_store_id', 'store-one-click')->firstOrFail();
        $this->assertSame('bigshop', WidgetInstall::query()->where('merchant_company_id', $company->id)->value('platform'));
        $this->assertSame('configured', PlatformConnection::query()->where('merchant_company_id', $company->id)->value('status'));
        $this->assertSame(1, IntegrationEvent::query()->where('event_type', 'one_click_activation')->count());
    }

    public function test_bigshop_activation_rejects_invalid_signature(): void
    {
        config(['services.bigshop.activation_secret' => 'secret-one-click']);

        $this->withHeaders([
            'X-BigShop-Timestamp' => (string) now()->timestamp,
            'X-BigShop-Signature' => 'sha256=invalid',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/public/bigshop/activate', [
            'store_id' => 'bad-store',
            'store_name' => 'Bad Store',
            'merchant' => ['email' => 'bad@example.com'],
        ])->assertUnauthorized();
    }

    public function test_bigshop_activation_requires_configured_secret(): void
    {
        config(['services.bigshop.activation_secret' => null]);

        $this->postJson('/api/v1/public/bigshop/activate', [
            'store_id' => 'store-one-click',
            'store_name' => 'Loja Um Clique',
            'merchant' => ['email' => 'owner@lojaumclique.com.br'],
        ])->assertServiceUnavailable();
    }

    private function callSignedActivation(array $payload)
    {
        $timestamp = (string) now()->timestamp;
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'secret-one-click');

        return $this->withHeaders([
            'X-BigShop-Timestamp' => $timestamp,
            'X-BigShop-Signature' => 'sha256='.$signature,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/public/bigshop/activate', $payload);
    }
}
