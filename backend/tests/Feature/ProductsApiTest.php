<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_manage_products_and_variants(): void
    {
        $this->seed();
        $token = $this->loginToken();
        $headers = ['Authorization' => 'Bearer '.$token];

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

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
