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
            ->assertJsonPath('data.theme.presentation_mode', 'drawer')
            ->assertJsonPath('data.platform_guide.key', 'custom')
            ->assertJsonPath('data.platform_guide.guide.placement_label', 'Página de produto')
            ->assertJsonPath('data.platform_guides.0.key', 'bigshop');

        $this->assertStringContainsString('provador-virtual.js', $response->json('data.snippet'));
        $this->assertStringContainsString('data-merchant-id', $response->json('data.snippet'));
        $this->assertStringContainsString('data-platform="custom"', $response->json('data.snippet'));

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
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.platform_guide.key', 'bigshop');

        $bigShopSnippet = $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->json('data.snippet');

        $this->assertStringContainsString('presentation_mode', $bigShopSnippet);
        $this->assertStringContainsString('data-platform="bigshop"', $bigShopSnippet);
        $this->assertStringContainsString('BIGSHOP_PRODUCT_ID', $bigShopSnippet);

        $shopifyResponse = $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'shopify',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'shopify')
            ->assertJsonPath('data.platform_guide.key', 'shopify');

        $this->assertStringContainsString('{{ product.id }}', $shopifyResponse->json('data.snippet'));
        $this->assertStringContainsString('Template Liquid de produto', $shopifyResponse->json('data.platform_guide.guide.placement_label'));
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
