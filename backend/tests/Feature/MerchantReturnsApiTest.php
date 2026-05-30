<?php

namespace Tests\Feature;

use App\Models\MeasurementTable;
use App\Models\MeasurementTableRow;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\MerchantOrder;
use App\Models\MerchantOrderItem;
use App\Models\MerchantReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantReturnsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_view_returns_overview_and_list(): void
    {
        [$token, $merchant, $company] = $this->merchantToken();
        $return = MerchantReturn::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'source' => 'import',
            'source_platform' => 'custom',
            'return_reference' => 'RET-1001',
            'return_reference_hash' => hash('sha256', 'RET-1001'),
            'order_reference' => 'PV-ORDER-1001',
            'order_reference_hash' => hash('sha256', 'PV-ORDER-1001'),
            'status' => 'returned',
            'processed_at' => now()->subDay(),
            'items_count' => 1,
            'total_quantity' => 1,
            'refund_amount_cents' => 18990,
            'used_virtual_try_on' => true,
            'assisted_items_count' => 1,
            'assisted_refund_cents' => 18990,
        ]);
        $return->items()->create([
            'product_name' => 'Vestido Midi Aurora',
            'ordered_size' => 'M',
            'ideal_size' => 'G',
            'returned_size' => 'M',
            'return_reason' => 'size_too_small',
            'status' => 'returned',
            'quantity' => 1,
            'refund_amount_cents' => 18990,
            'used_virtual_try_on' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/returns/overview?period=30d')
            ->assertOk()
            ->assertJsonPath('data.summary.returns_total', 1)
            ->assertJsonPath('data.summary.assisted_returns', 1);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/returns?period=30d')
            ->assertOk()
            ->assertJsonPath('data.0.return_reference', 'RET-1001')
            ->assertJsonPath('data.0.items.0.return_reason', 'size_too_small');
    }

    public function test_merchant_can_preview_and_import_returns_csv_generating_learning_signal(): void
    {
        [$token, $merchant, $company, $product, $variant] = $this->merchantToken(withCatalog: true);
        $orderReference = 'PV-ORDER-RET-2002';
        $log = RecommendationLog::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'recommended_size' => 'M',
            'confidence' => 91.5,
            'input_measurements' => ['height' => 168, 'weight' => 62],
        ]);

        $order = MerchantOrder::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'source' => 'csv',
            'source_platform' => 'custom',
            'order_reference' => $orderReference,
            'order_reference_hash' => hash('sha256', $orderReference),
            'status' => 'paid',
            'ordered_at' => now()->subDays(2),
            'items_count' => 1,
            'total_quantity' => 1,
            'total_amount_cents' => 18990,
            'currency' => 'BRL',
            'used_virtual_try_on' => true,
            'assisted_items_count' => 1,
            'assisted_revenue_cents' => 18990,
        ]);
        MerchantOrderItem::query()->create([
            'merchant_order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'recommendation_log_id' => $log->id,
            'sku' => $variant->sku,
            'product_name' => $product->name,
            'ordered_size' => 'M',
            'recommended_size' => 'M',
            'recommendation_confidence' => 91.5,
            'quantity' => 1,
            'unit_price_cents' => 18990,
            'line_total_cents' => 18990,
            'used_virtual_try_on' => true,
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
            'occurred_at' => now()->subDay(),
        ]);

        $content = implode("\n", [
            'return_reference;order_reference;ordered_at;processed_at;status;return_reason;sku;product_name;ordered_size;ideal_size;returned_size;quantity;refund_amount;source_platform',
            'RET-2002;'.$orderReference.';'.now()->subDays(2)->format('Y-m-d H:i:s').';'.now()->format('Y-m-d H:i:s').';returned;ficou pequeno;'.$variant->sku.';'.$product->name.';M;G;M;1;189.90;custom',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/returns/import', [
                'format' => 'csv',
                'content' => $content,
                'commit' => false,
            ])
            ->assertOk()
            ->assertJsonPath('summary.valid', 1)
            ->assertJsonPath('columns.mapping.order_reference', 'order_reference')
            ->assertJsonPath('rows.0.return_reason', 'size_too_small');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/returns/import', [
                'format' => 'csv',
                'content' => $content,
                'commit' => true,
            ])
            ->assertOk()
            ->assertJsonPath('summary.imported_returns', 1);

        $this->assertDatabaseHas('merchant_returns', [
            'merchant_id' => $merchant->id,
            'return_reference' => 'RET-2002',
            'used_virtual_try_on' => 1,
        ]);
        $this->assertDatabaseHas('merchant_return_items', [
            'product_name' => $product->name,
            'return_reason' => 'size_too_small',
            'used_virtual_try_on' => 1,
        ]);
        $this->assertDatabaseHas('recommendation_learning_events', [
            'merchant_id' => $merchant->id,
            'recommendation_log_id' => $log->id,
            'event_type' => 'return',
        ]);
    }

    public function test_merchant_can_download_returns_xlsx_template(): void
    {
        [$token] = $this->merchantToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/api/v1/returns/template?format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString(
            'modelo-devolucoes-provador-virtual.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_user_without_analytics_permission_cannot_access_returns(): void
    {
        [$merchant, $company] = $this->merchantContext();
        $user = User::query()->create([
            'name' => 'Sem Returns Analytics',
            'email' => 'sem-returns-analytics@example.com',
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
            ->getJson('/api/v1/returns')
            ->assertForbidden();
    }

    private function merchantToken(bool $withCatalog = false): array
    {
        [$merchant, $company] = $this->merchantContext();
        $user = User::query()->create([
            'name' => 'Returns Merchant',
            'email' => 'merchant.returns@example.com',
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

        $table = MeasurementTable::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'name' => 'Tabela Aurora',
            'product_type' => 'dress',
            'gender' => 'female',
            'fit_profile' => 'regular',
            'measurement_target' => 'body',
            'size_system' => 'br_alpha',
            'range_mode' => 'min_max',
            'status' => 'active',
            'source' => 'manual',
        ]);
        MeasurementTableRow::query()->create([
            'measurement_table_id' => $table->id,
            'size_label' => 'M',
            'sort_order' => 1,
            'bust_min' => 90,
            'bust_max' => 94,
            'waist_min' => 72,
            'waist_max' => 76,
            'hip_min' => 98,
            'hip_max' => 102,
            'height_min' => 165,
            'height_max' => 170,
            'weight_min' => 60,
            'weight_max' => 65,
        ]);

        $product = Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'measurement_table_id' => $table->id,
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
            'name' => 'Loja Returns',
            'slug' => 'loja-returns',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Returns',
            'legal_name' => 'Loja Returns Ltda',
            'document' => '12345678000195',
            'platform' => 'custom',
            'status' => 'active',
        ]);

        return [$merchant, $company];
    }
}
