<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\MerchantOrder;
use App\Models\MerchantOrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_view_orders_overview_and_list(): void
    {
        [$token, $merchant, $company] = $this->merchantToken();
        $order = MerchantOrder::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'source' => 'csv',
            'source_platform' => 'custom',
            'order_reference' => 'PV-ORDER-1001',
            'order_reference_hash' => hash('sha256', 'PV-ORDER-1001'),
            'status' => 'paid',
            'ordered_at' => now()->subDay(),
            'items_count' => 1,
            'total_quantity' => 1,
            'total_amount_cents' => 19990,
            'currency' => 'BRL',
            'used_virtual_try_on' => true,
            'assisted_items_count' => 1,
            'assisted_revenue_cents' => 19990,
        ]);
        MerchantOrderItem::query()->create([
            'merchant_order_id' => $order->id,
            'product_name' => 'Vestido Midi Aurora',
            'ordered_size' => 'M',
            'quantity' => 1,
            'unit_price_cents' => 19990,
            'line_total_cents' => 19990,
            'used_virtual_try_on' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/orders/overview?period=30d')
            ->assertOk()
            ->assertJsonPath('data.summary.orders_total', 1)
            ->assertJsonPath('data.summary.assisted_orders', 1);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/orders?period=30d')
            ->assertOk()
            ->assertJsonPath('data.0.order_reference', 'PV-ORDER-1001')
            ->assertJsonPath('data.0.items.0.product_name', 'Vestido Midi Aurora');
    }

    public function test_merchant_can_preview_and_import_orders_csv_matching_learning_events(): void
    {
        [$token, $merchant, $company, $product, $variant] = $this->merchantToken(withCatalog: true);
        $orderReference = 'PV-ORDER-2002';
        $log = RecommendationLog::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'recommended_size' => 'M',
            'confidence' => 91.5,
            'input_measurements' => ['height' => 168, 'weight' => 62],
        ]);
        RecommendationLearningEvent::query()->create([
            'uuid' => (string) str()->uuid(),
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'recommendation_log_id' => $log->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'event_type' => 'purchase',
            'recommended_size' => 'M',
            'selected_size' => 'M',
            'confidence' => 91.5,
            'outlier_score' => 0,
            'learning_weight' => 3,
            'status' => 'accepted',
            'reason' => 'ok',
            'payload' => [
                'order_reference_hash' => hash('sha256', $orderReference),
                'ordered_size' => 'M',
            ],
            'occurred_at' => now(),
        ]);

        $content = implode("\n", [
            'order_reference;ordered_at;status;currency;total_amount;sku;product_name;ordered_size;quantity;unit_price;source_platform',
            $orderReference.';'.now()->format('Y-m-d H:i:s').';paid;BRL;189.90;'.$variant->sku.';'.$product->name.';M;1;189.90;custom',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/orders/import', [
                'content' => $content,
                'commit' => false,
            ])
            ->assertOk()
            ->assertJsonPath('summary.valid', 1);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/orders/import', [
                'content' => $content,
                'commit' => true,
            ])
            ->assertOk()
            ->assertJsonPath('summary.imported_orders', 1);

        $this->assertDatabaseHas('merchant_orders', [
            'merchant_id' => $merchant->id,
            'order_reference' => $orderReference,
            'used_virtual_try_on' => 1,
        ]);
        $this->assertDatabaseHas('merchant_order_items', [
            'product_name' => $product->name,
            'used_virtual_try_on' => 1,
            'recommended_size' => 'M',
        ]);
    }

    public function test_user_without_analytics_permission_cannot_access_orders(): void
    {
        [$merchant, $company] = $this->merchantContext();
        $user = User::query()->create([
            'name' => 'Sem Analytics',
            'email' => 'sem-analytics.orders@example.com',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);
        $user->merchants()->attach($merchant->id, [
            'merchant_company_id' => $company->id,
            'role' => 'analyst',
            'status' => 'active',
            'permissions' => json_encode([
                'analytics' => ['view' => false, 'edit' => false],
            ]),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken])
            ->getJson('/api/v1/orders')
            ->assertForbidden();
    }

    private function merchantToken(bool $withCatalog = false): array
    {
        [$merchant, $company] = $this->merchantContext();
        $user = User::query()->create([
            'name' => 'Pedidos Merchant',
            'email' => 'merchant.orders@example.com',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);
        $user->merchants()->attach($merchant->id, [
            'merchant_company_id' => $company->id,
            'role' => 'owner',
            'is_owner' => true,
            'status' => 'active',
        ]);

        $payload = [$user->createToken('test', ['merchant:'.$merchant->id, 'company:'.$company->id])->plainTextToken, $merchant, $company];

        if (! $withCatalog) {
            return $payload;
        }

        $product = Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'name' => 'Vestido Midi Aurora',
            'slug' => 'vestido-midi-aurora',
            'sku' => 'PV-AURORA-MIDI',
            'status' => 'active',
        ]);
        $variant = ProductVariant::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'product_id' => $product->id,
            'size_label' => 'M',
            'sku' => 'PV-AURORA-MIDI-M',
            'is_active' => true,
        ]);

        return [...$payload, $product, $variant];
    }

    private function merchantContext(): array
    {
        $merchant = Merchant::query()->create([
            'name' => 'Loja Orders',
            'slug' => 'loja-orders',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Orders',
            'legal_name' => 'Loja Orders Ltda',
            'document' => '12345678000195',
            'platform' => 'custom',
            'status' => 'active',
        ]);

        return [$merchant, $company];
    }
}
