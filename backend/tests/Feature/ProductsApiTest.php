<?php

namespace Tests\Feature;

use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_manage_products_and_variants(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $tableId = $this->withHeaders($headers)
            ->getJson('/api/v1/measurement-tables')
            ->assertOk()
            ->json('data.0.id');

        $productId = $this->withHeaders($headers)
            ->postJson('/api/v1/products', [
                'name' => 'Camisa Linho Teste',
                'sku' => 'LINHO-BASE',
                'category' => 'Camisas',
                'gender' => 'unisex',
                'fit_profile' => 'regular',
                'measurement_table_id' => $tableId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Camisa Linho Teste')
            ->assertJsonPath('data.slug', 'camisa-linho-teste')
            ->json('data.id');

        $variantId = $this->withHeaders($headers)
            ->postJson("/api/v1/products/{$productId}/variants", [
                'sku' => 'LINHO-M',
                'size_label' => 'M',
                'color' => 'Natural',
                'price' => 219.9,
                'stock_quantity' => 7,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'LINHO-M')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/products/{$productId}/variants/{$variantId}", [
                'stock_quantity' => 6,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 6)
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)
            ->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.measurement_table.id', $tableId)
            ->assertJsonPath('data.variants.0.sku', 'LINHO-M');

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/products/{$productId}/variants/{$variantId}")
            ->assertOk();

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/products/{$productId}")
            ->assertOk();
    }

    public function test_merchant_can_link_measurement_table_to_selected_products_in_bulk(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $tableId = $this->withHeaders($headers)
            ->getJson('/api/v1/measurement-tables')
            ->assertOk()
            ->json('data.0.id');

        $firstProductId = $this->withHeaders($headers)
            ->postJson('/api/v1/products', [
                'name' => 'Produto em lote um',
                'sku' => 'LOTE-1',
                'category' => 'Camisas',
            ])
            ->assertCreated()
            ->assertJsonPath('data.measurement_table', null)
            ->json('data.id');

        $secondProductId = $this->withHeaders($headers)
            ->postJson('/api/v1/products', [
                'name' => 'Produto em lote dois',
                'sku' => 'LOTE-2',
                'category' => 'Camisas',
            ])
            ->assertCreated()
            ->assertJsonPath('data.measurement_table', null)
            ->json('data.id');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/products/bulk-measurement-table', [
                'product_ids' => [$firstProductId, $secondProductId],
                'measurement_table_id' => $tableId,
            ])
            ->assertOk()
            ->assertJsonPath('summary.requested', 2)
            ->assertJsonPath('summary.updated', 2)
            ->assertJsonPath('data.0.measurement_table.id', $tableId)
            ->assertJsonPath('data.1.measurement_table.id', $tableId);

        $this->assertDatabaseHas('products', [
            'id' => $firstProductId,
            'measurement_table_id' => $tableId,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $secondProductId,
            'measurement_table_id' => $tableId,
        ]);
    }

    public function test_merchant_can_filter_paginated_operational_product_list(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $merchant = Merchant::query()->where('slug', 'provador-virtual-demo')->firstOrFail();
        $company = MerchantCompany::query()->where('merchant_id', $merchant->id)->firstOrFail();
        $table = MeasurementTable::query()->where('merchant_id', $merchant->id)->firstOrFail();

        $this->createOperationalProduct($merchant, $company, $table, [
            'name' => 'S130 Camisa pronta Zak',
            'slug' => 's130-camisa-pronta-zak',
            'sku' => 'S130-READY',
            'category' => 'Camisas',
            'gender' => 'male',
            'fit_profile' => 'regular',
            'metadata' => [
                'brand' => 'Zak',
                'age_group' => 'adult',
                'last_imported_at' => now()->toISOString(),
            ],
        ], ['P', 'M']);

        $this->createOperationalProduct($merchant, $company, null, [
            'name' => 'S130 Blusa sem tabela',
            'slug' => 's130-blusa-sem-tabela',
            'sku' => 'S130-NOTABLE',
            'category' => 'Blusas',
            'gender' => 'female',
            'fit_profile' => 'slim',
            'metadata' => [
                'brand' => 'Bella',
                'age_group' => 'adult',
                'source' => 'manual',
            ],
        ], ['PP']);

        $this->createOperationalProduct($merchant, $company, $table, [
            'name' => 'S130 Calca erro sync Zak',
            'slug' => 's130-calca-erro-sync-zak',
            'sku' => 'S130-SYNC',
            'category' => 'Calcas',
            'gender' => 'male',
            'fit_profile' => 'regular',
            'metadata' => [
                'brand' => 'Zak',
                'age_group' => 'kids',
                'bigshop_last_sync_at' => now()->toISOString(),
                'sync_error' => 'Tabela externa indisponivel',
            ],
        ], ['38']);

        $this->createOperationalProduct($merchant, $company, $table, [
            'name' => 'S130 Produto desativado',
            'slug' => 's130-produto-desativado',
            'sku' => 'S130-OFF',
            'category' => 'Acessorios',
            'gender' => 'unisex',
            'fit_profile' => 'regular',
            'status' => 'inactive',
            'metadata' => [
                'brand' => 'Archive',
                'age_group' => 'adult',
                'source' => 'manual',
            ],
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/products?search=S130&brand=Zak&readiness=ready&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.tabs.all', 2)
            ->assertJsonPath('summary.tabs.ready', 1)
            ->assertJsonPath('summary.tabs.pending', 1)
            ->assertJsonPath('summary.tabs.sync_error', 1)
            ->assertJsonPath('data.0.name', 'S130 Camisa pronta Zak')
            ->assertJsonPath('data.0.brand', 'Zak')
            ->assertJsonPath('data.0.age_group', 'adult')
            ->assertJsonPath('data.0.source_label', 'Importação')
            ->assertJsonPath('data.0.readiness_status', 'ready')
            ->assertJsonPath('data.0.size_labels.0', 'P');

        $this->withHeaders($headers)
            ->getJson('/api/v1/products?search=S130&readiness=without_measurement_table')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'S130 Blusa sem tabela');

        $this->withHeaders($headers)
            ->getJson('/api/v1/products?search=S130&source=bigshop')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'S130 Calca erro sync Zak')
            ->assertJsonPath('data.0.has_sync_error', true);
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }

    private function createOperationalProduct(
        Merchant $merchant,
        MerchantCompany $company,
        ?MeasurementTable $table,
        array $attributes,
        array $sizes = []
    ): Product {
        $product = Product::query()->create(array_merge([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'measurement_table_id' => $table?->id,
            'external_product_id' => $attributes['sku'] ?? null,
            'description' => 'Produto operacional da Sprint 130.',
            'status' => 'active',
            'image_url' => null,
            'metadata' => [],
        ], $attributes));

        foreach ($sizes as $size) {
            ProductVariant::query()->create([
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company->id,
                'product_id' => $product->id,
                'external_variant_id' => $product->sku.'-'.$size,
                'sku' => $product->sku.'-'.$size,
                'size_label' => $size,
                'is_active' => true,
            ]);
        }

        return $product;
    }
}
