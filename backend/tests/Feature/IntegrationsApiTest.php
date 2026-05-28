<?php

namespace Tests\Feature;

use App\Models\IntegrationEvent;
use App\Models\MeasurementTable;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
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

        $tableId = MeasurementTable::query()->value('id');
        $product->update(['measurement_table_id' => $tableId]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/integrations/custom/sync-xml')
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame($tableId, $product->refresh()->measurement_table_id);
    }

    public function test_merchant_can_configure_visual_import_rules(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/bigshop', [
                'external_store_id' => 'store-rules',
                'import_rules' => [
                    'category' => [
                        'enabled' => true,
                        'required' => true,
                        'source_field' => 'categoria',
                        'fallback' => 'Moda feminina',
                    ],
                    'gender' => [
                        'enabled' => true,
                        'required' => true,
                        'source_field' => 'genero',
                        'fallback' => 'female',
                        'aliases' => [
                            'Feminino' => 'female',
                        ],
                    ],
                    'fit_profile' => [
                        'enabled' => true,
                        'required' => true,
                        'source_field' => 'modelagem',
                        'fallback' => 'regular',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.import_rules.category.source_field', 'categoria')
            ->assertJsonPath('data.import_rules.category.fallback', 'Moda feminina')
            ->assertJsonPath('data.import_rules.gender.aliases.Feminino', 'female')
            ->assertJsonPath('data.import_rules.fit_profile.fallback', 'regular')
            ->assertJsonPath('data.import_rules.status.fallback', 'active');

        $connection = PlatformConnection::query()->where('platform', 'bigshop')->firstOrFail();

        $this->assertSame('categoria', $connection->import_rules['category']['source_field']);
        $this->assertTrue($connection->import_rules['category']['required']);
        $this->assertSame('active', $connection->import_rules['status']['fallback']);
    }

    public function test_xml_feed_sync_command_processes_configured_connections(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $feedUrl = 'https://store.example/scheduled-feed.xml';

        Http::fake([
            $feedUrl => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
  <channel>
    <item>
      <g:id>VAR-CRON-1</g:id>
      <g:item_group_id>GROUP-CRON-1</g:item_group_id>
      <title>Vestido Cron Azul</title>
      <g:product_type>Full Body</g:product_type>
      <link>https://store.example/vestido-cron-azul</link>
      <g:gender>female</g:gender>
      <g:color>Azul</g:color>
      <g:size>G</g:size>
      <g:availability>in stock</g:availability>
    </item>
  </channel>
</rss>
XML, 200),
        ]);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/custom', [
                'feed_url' => $feedUrl,
            ])
            ->assertOk();

        $this->artisan('pv:integrations-sync-feeds', ['--limit' => 10])
            ->assertExitCode(0);

        $this->assertDatabaseHas('platform_connections', [
            'platform' => 'custom',
            'feed_url' => $feedUrl,
            'status' => 'connected',
        ]);
        $this->assertDatabaseHas('products', [
            'external_product_id' => 'GROUP-CRON-1',
            'sku' => 'GROUP-CRON-1',
            'name' => 'Vestido Cron Azul',
        ]);
        $this->assertDatabaseHas('product_variants', [
            'external_variant_id' => 'VAR-CRON-1',
            'size_label' => 'G',
            'color' => 'Azul',
            'is_active' => true,
        ]);

        $event = IntegrationEvent::query()
            ->where('platform', 'custom')
            ->where('event_type', 'xml_feed_sync')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('success', $event->status);
        $this->assertSame('scheduled', $event->summary['trigger'] ?? null);
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

    public function test_merchant_can_view_sync_history_with_product_errors(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $company = MerchantCompany::query()->where('external_store_id', 'pv-demo-store')->firstOrFail();

        IntegrationEvent::query()->create([
            'merchant_id' => $company->merchant_id,
            'merchant_company_id' => $company->id,
            'platform' => 'bigshop',
            'event_type' => 'dry_run_import',
            'direction' => 'outbound',
            'status' => 'warning',
            'summary' => [
                'products_read' => 2,
                'products_valid' => 2,
                'grids_read' => 4,
                'grids_joined' => 3,
                'errors_count' => 1,
                'warnings_count' => 2,
            ],
            'payload' => [
                'sample_products' => [
                    [
                        'external_product_id' => 'BS-10',
                        'name' => 'Vestido BigShop',
                        'grid_count' => 2,
                        'sizes' => ['P', 'M'],
                    ],
                ],
                'issues' => [
                    [
                        'severity' => 'error',
                        'code' => 'grid_product_not_found',
                        'product_id' => 'BS-999',
                        'product_name' => null,
                        'grid_id' => 'G999',
                        'message' => 'Grade aponta para produto que nao veio em products.',
                    ],
                ],
            ],
            'occurred_at' => now(),
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/integrations/sync-history')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.with_errors', 1)
            ->assertJsonPath('data.0.title', 'Prévia BigShop')
            ->assertJsonPath('data.0.counters.products', 2)
            ->assertJsonPath('data.0.counters.errors', 1)
            ->assertJsonPath('data.0.sample_products.0.external_product_id', 'BS-10')
            ->assertJsonPath('data.0.issues.0.product_id', 'BS-999')
            ->assertJsonPath('data.0.issues.0.grid_id', 'G999');
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
