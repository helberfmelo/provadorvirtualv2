<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_product_payload_is_available(): void
    {
        $this->seed();

        $this->getJson('/api/v1/demo/storefront')
            ->assertOk()
            ->assertJsonPath('store.name', 'Provador Virtual Loja Teste')
            ->assertJsonCount(4, 'products')
            ->assertJsonPath('products.0.slug', 'vestido-midi-aurora');

        $this->getJson('/api/v1/demo/product-test')
            ->assertOk()
            ->assertJsonPath('product.name', 'Vestido Midi Aurora')
            ->assertJsonPath('product.company.platform', 'custom')
            ->assertJsonPath('measurement_table.rows.0.size_label', 'PP')
            ->assertJsonPath('widget.public_key', 'pv_demo_luna')
            ->assertJsonCount(5, 'variants')
            ->assertJsonCount(5, 'measurement_table.rows');
    }
}
