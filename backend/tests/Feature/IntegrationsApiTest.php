<?php

namespace Tests\Feature;

use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\ProductVariant;
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
                'feed_url' => 'https://loja.bigshop.test/feed.xml',
                'access_token' => 'token-secreto',
                'webhook_secret' => 'assinatura-secreta',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.external_store_id', 'loja-demo-bigshop')
            ->assertJsonPath('data.feed_url', 'https://loja.bigshop.test/feed.xml')
            ->assertJsonPath('data.feed_format', 'google_xml')
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

    public function test_merchant_can_sync_products_from_xml_feed_connection(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $feedUrl = 'https://store.example/feed.xml';

        Http::fake([
            $feedUrl => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <item>
      <g:id>VAR-1</g:id>
      <g:item_group_id>GROUP-1</g:item_group_id>
      <title>Vestido Festa Azul</title>
      <description>Vestido longo para festa.</description>
      <g:product_type>Full Body</g:product_type>
      <g:brand>Luna</g:brand>
      <link>https://store.example/vestido-festa-azul</link>
      <g:image_link>https://store.example/vestido.jpg</g:image_link>
      <g:gender>female</g:gender>
      <g:age_group>adult</g:age_group>
      <g:color>Azul</g:color>
      <g:size>M</g:size>
      <g:availability>in stock</g:availability>
      <g:price>199.90 BRL</g:price>
    </item>
  </channel>
</rss>
XML, 200),
        ]);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/custom', [
                'feed_url' => $feedUrl,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'configured');

        $this->withHeaders($headers)
            ->postJson('/api/v1/integrations/custom/sync-xml')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_rows', 1)
            ->assertJsonPath('data.imported_rows', 1)
            ->assertJsonPath('data.summary.products', 1)
            ->assertJsonPath('data.summary.variants', 1);

        $this->assertDatabaseHas('platform_connections', [
            'platform' => 'custom',
            'feed_url' => $feedUrl,
            'status' => 'connected',
        ]);
        $this->assertDatabaseHas('products', [
            'external_product_id' => 'GROUP-1',
            'sku' => 'GROUP-1',
            'name' => 'Vestido Festa Azul',
            'gender' => 'female',
            'image_url' => 'https://store.example/vestido.jpg',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'external_variant_id' => 'VAR-1',
            'sku' => 'VAR-1',
            'size_label' => 'M',
            'color' => 'Azul',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('integration_events', [
            'platform' => 'custom',
            'event_type' => 'xml_feed_sync',
            'status' => 'success',
        ]);

        $product = Product::query()->where('external_product_id', 'GROUP-1')->firstOrFail();
        $variant = ProductVariant::query()->where('external_variant_id', 'VAR-1')->firstOrFail();

        $this->assertSame($product->id, $variant->product_id);
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
            ->assertJsonPath('message', 'Sua empresa contratou o plano BigShop. A integração disponível para este contrato é apenas BigShop.');

        $this->withHeaders($headers)
            ->postJson('/api/v1/integrations/shopify/validate-install', [
                'url' => 'https://provadorvirtual.online/produto-validacao',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Sua empresa contratou o plano BigShop. A integração disponível para este contrato é apenas BigShop.');

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
