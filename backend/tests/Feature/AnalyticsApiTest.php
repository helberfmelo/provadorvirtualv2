<?php

namespace Tests\Feature;

use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_read_recommendation_analytics_and_audit_logs(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $merchant = Merchant::query()->where('slug', 'loja-luna-demo')->firstOrFail();
        $product = Product::query()->where('sku', 'LUNA-MIDI')->firstOrFail();

        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $product->merchant_company_id,
            'sku' => 'NO-TABLE',
            'slug' => 'produto-sem-tabela',
            'name' => 'Produto sem tabela',
            'category' => 'Camisas',
            'status' => 'active',
        ]);

        $recommendationId = $this->postJson('/api/v1/public/recommendations', [
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
            'measurements' => [
                'bust' => 92,
                'waist' => 74,
                'hip' => 100,
                'height' => 168,
                'weight' => 62,
            ],
        ])->assertCreated()->json('recommendation_id');

        $this->postJson("/api/v1/public/recommendations/{$recommendationId}/feedback", [
            'was_helpful' => true,
            'rating' => 5,
        ])->assertCreated();

        $table = MeasurementTable::query()->where('merchant_id', $merchant->id)->firstOrFail();

        $this->withHeaders($headers)
            ->patchJson("/api/v1/measurement-tables/{$table->id}", [
                'notes' => 'Tabela revisada para analytics.',
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/analytics/recommendations')
            ->assertOk()
            ->assertJsonPath('data.summary.recommendations_total', 1)
            ->assertJsonPath('data.summary.positive_feedback_rate', 100)
            ->assertJsonPath('data.summary.products_without_measurement_table', 1)
            ->assertJsonPath('data.sizes.0.size', 'M');

        $this->withHeaders($headers)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'measurement_table.updated');
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
