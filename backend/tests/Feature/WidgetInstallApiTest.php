<?php

namespace Tests\Feature;

use App\Models\MerchantCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetInstallApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_install_requires_authentication_without_redirect_error(): void
    {
        $this->seed();

        $this->get('/api/v1/widget-install')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_merchant_can_view_and_update_widget_install(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->assertJsonPath('data.public_key', 'pv_demo_luna')
            ->assertJsonPath('data.platform', 'custom')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.theme.presentation_mode', 'drawer');

        $this->assertStringContainsString('provador-virtual.js', $response->json('data.snippet'));
        $this->assertStringContainsString('data-merchant-id', $response->json('data.snippet'));

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'bigshop',
                'allowed_domains' => ['ProvadorVirtual.Online', 'localhost', 'localhost'],
                'theme' => [
                    'primary' => '#101820',
                    'secondary' => '#ff4d5e',
                    'accent' => '#17a398',
                    'presentation_mode' => 'modal',
                ],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.allowed_domains.0', 'provadorvirtual.online')
            ->assertJsonPath('data.allowed_domains.1', 'localhost')
            ->assertJsonPath('data.theme.primary', '#101820')
            ->assertJsonPath('data.theme.presentation_mode', 'modal')
            ->assertJsonPath('data.is_active', false);

        $this->assertStringContainsString('presentation_mode', $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->json('data.snippet'));
    }

    public function test_bigshop_contract_keeps_widget_platform_locked(): void
    {
        $this->seed();

        MerchantCompany::query()
            ->where('external_store_id', 'pv-demo-store')
            ->update(['platform' => 'bigshop']);

        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.company.platform', 'bigshop');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'shopify',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sua empresa contratou o plano BigShop. O widget pode ser instalado apenas na BigShop.');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'bigshop',
                'allowed_domains' => ['provadorvirtual.online'],
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop');
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
