<?php

namespace Tests\Feature;

use App\Models\MerchantCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_configure_platform_connection(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'bigshop')
            ->assertJsonPath('data.0.priority', true)
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.guide.checklist.0.key', 'domain_configured')
            ->assertJsonFragment(['key' => 'loja_integrada'])
            ->assertJsonFragment(['key' => 'magento'])
            ->assertJsonFragment(['key' => 'opencart']);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/bigshop', [
                'external_store_id' => 'loja-demo-bigshop',
                'api_base_url' => 'https://api.bigshop.test',
                'access_token' => 'token-secreto',
                'webhook_secret' => 'assinatura-secreta',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.external_store_id', 'loja-demo-bigshop')
            ->assertJsonPath('data.status', 'configured')
            ->assertJsonPath('data.has_access_token', true)
            ->assertJsonPath('data.has_webhook_secret', true)
            ->assertJsonMissing(['access_token' => 'token-secreto']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'configured')
            ->assertJsonPath('data.0.connection.has_access_token', true);
    }

    public function test_merchant_can_validate_widget_installation_on_product_page(): void
    {
        $this->seed();
        Http::fake([
            'https://provadorvirtual.online/produto-validacao' => Http::response(
                '<div id="provador-virtual-container"></div><script id="provadorVirtualScript" src="https://provadorvirtual.online/provadorvirtual_v2/widget/v1/provador-virtual.js" data-platform="custom" data-product-id="123" data-sku="PV-123"></script>',
                200
            ),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->loginToken()])
            ->postJson('/api/v1/integrations/custom/validate-install', [
                'url' => 'https://provadorvirtual.online/produto-validacao',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'passed')
            ->assertJsonPath('data.checks.0.status', 'passed')
            ->assertJsonPath('data.checks.3.key', 'script_found')
            ->assertJsonPath('data.checks.3.status', 'passed');

        $this->assertDatabaseHas('integration_events', [
            'platform' => 'custom',
            'event_type' => 'install_validation',
            'status' => 'passed',
        ]);
    }

    public function test_bigshop_contract_can_only_access_bigshop_integration(): void
    {
        $this->seed();
        $this->assertDatabaseHas('merchant_companies', [
            'external_store_id' => 'pv-demo-store',
            'platform' => 'custom',
        ]);

        MerchantCompany::query()
            ->where('external_store_id', 'pv-demo-store')
            ->update(['platform' => 'bigshop']);

        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'bigshop');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/shopify', [
                'external_store_id' => 'loja-travada',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sua empresa contratou o plano BigShop. A integracao disponivel para este contrato e apenas BigShop.');

        $this->withHeaders($headers)
            ->postJson('/api/v1/integrations/shopify/validate-install', [
                'url' => 'https://provadorvirtual.online/produto-validacao',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sua empresa contratou o plano BigShop. A integracao disponivel para este contrato e apenas BigShop.');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/bigshop', [
                'external_store_id' => 'loja-demo-bigshop',
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
