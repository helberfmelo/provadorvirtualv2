<?php

namespace Tests\Feature;

use App\Models\IntegrationEvent;
use App\Models\MeasurementTable;
use App\Models\MerchantCompany;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BigShopIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_probe_bigshop_connection(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $this->configureBigShop($headers);

        Http::fake([
            'https://api.bigshop.test/v3/getEndPoints*' => Http::response([
                'products',
                'grids',
                'categories',
            ]),
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/integrations/bigshop/probe')
            ->assertOk()
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.http_status', 200)
            ->assertJsonPath('data.endpoints_count', 3);

        Http::assertSent(fn ($request): bool => $request->hasHeader('x-api', 'token-bigshop')
            && $request->hasHeader('store-id', 'store-123'));

        $this->assertDatabaseHas('platform_connections', [
            'platform' => 'bigshop',
            'status' => 'connected',
        ]);
        $this->assertDatabaseHas('integration_events', [
            'platform' => 'bigshop',
            'event_type' => 'probe',
            'status' => 'success',
        ]);
    }

    public function test_merchant_can_sync_bigshop_products_variants_and_measurement_table(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $this->configureBigShop($headers);

        Http::fake([
            'https://api.bigshop.test/v3/products*' => Http::response([
                'data' => [
                    [
                        'id' => 'BS-10',
                        'nome' => 'Vestido BigShop',
                        'sku' => 'BS-VEST',
                        'categoria' => 'Vestidos',
                        'genero' => 'feminino',
                        'grades' => [
                            ['id' => 'BS-10-P', 'tamanho' => 'P', 'sku' => 'BS-VEST-P', 'preco' => '209.90', 'estoque' => 4],
                            ['id' => 'BS-10-M', 'tamanho' => 'M', 'sku' => 'BS-VEST-M', 'preco' => '209.90', 'estoque' => 7],
                        ],
                        'tabela_de_medidas' => [
                            'nome' => 'Vestido BigShop Medidas',
                            'rows' => [
                                ['tamanho' => 'P', 'busto_min' => 84, 'busto_max' => 90, 'cintura_min' => 66, 'cintura_max' => 72],
                                ['tamanho' => 'M', 'busto_min' => 90, 'busto_max' => 96, 'cintura_min' => 72, 'cintura_max' => 78],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/integrations/bigshop/sync')
            ->assertOk()
            ->assertJsonPath('data.products_synced', 1)
            ->assertJsonPath('data.variants_synced', 2)
            ->assertJsonPath('data.measurement_tables_synced', 1);

        $product = Product::query()->where('external_product_id', 'BS-10')->with('variants')->firstOrFail();
        $this->assertSame('Vestido BigShop', $product->name);
        $this->assertCount(2, $product->variants);

        $table = MeasurementTable::query()->where('name', 'Vestido BigShop Medidas')->with('rows')->firstOrFail();
        $this->assertCount(2, $table->rows);

        $this->assertSame(1, IntegrationEvent::query()->where('event_type', 'sync_products')->count());
    }

    public function test_merchant_can_monitor_bigshop_one_click_activations(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $company = MerchantCompany::query()->where('external_store_id', 'pv-demo-store')->firstOrFail();

        IntegrationEvent::query()->create([
            'merchant_id' => $company->merchant_id,
            'merchant_company_id' => $company->id,
            'platform' => 'bigshop',
            'event_type' => 'one_click_activation',
            'direction' => 'inbound',
            'status' => 'success',
            'summary' => [
                'contract_version' => '2026-05-23',
                'store_id' => 'pv-demo-store',
                'store_domain' => 'provadorvirtual.online',
                'has_access_token' => true,
                'widget_public_key' => 'pv_demo_luna',
            ],
            'occurred_at' => now(),
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/integrations/bigshop/activations')
            ->assertOk()
            ->assertJsonPath('data.0.store_id', 'pv-demo-store')
            ->assertJsonPath('data.0.contract_version', '2026-05-23')
            ->assertJsonPath('data.0.has_access_token', true);
    }

    private function configureBigShop(array $headers): void
    {
        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/bigshop', [
                'external_store_id' => 'store-123',
                'api_base_url' => 'https://api.bigshop.test',
                'access_token' => 'token-bigshop',
            ])
            ->assertOk();
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
