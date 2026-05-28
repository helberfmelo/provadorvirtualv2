<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMeasurementAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_suggest_measurement_table_from_text(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $product = Product::query()->where('sku', 'PV-AURORA-MIDI')->firstOrFail();
        $recommendationId = $this->postJson('/api/v1/public/recommendations', [
            'merchant_id' => $product->merchant_id,
            'store_id' => $product->merchant_company_id,
            'product_id' => $product->id,
            'measurements' => [
                'bust' => 92,
                'waist' => 74,
                'hip' => 100,
                'height' => 168,
                'weight' => 62,
            ],
        ])->assertCreated()->json('recommendation_id');

        $this->postJson("/api/v1/public/recommendations/{$recommendationId}/signal", [
            'signal' => 'return',
            'returned_size' => 'M',
            'return_reason' => 'size_too_small',
            'source' => 'returns_api',
            'source_platform' => 'bigshop',
            'order_reference' => 'ORDER-AI-1',
        ])->assertCreated();

        $content = implode("\n", [
            'Tamanho Busto Cintura Quadril',
            'P 88-94 70-76 92-98',
            'M 94-100 76-82 98-104',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/ai/measurement-table-suggestions', [
                'source_type' => 'text',
                'name' => 'Camisa assistida',
                'product_type' => 'dress',
                'gender' => 'female',
                'fit_profile' => 'regular',
                'content' => $content,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.review_required', true)
            ->assertJsonPath('data.suggestion.name', 'Camisa assistida')
            ->assertJsonPath('data.suggestion.rows.0.size_label', 'P')
            ->assertJsonPath('data.suggestion.rows.0.bust_min', 88)
            ->assertJsonPath('data.learning_context.has_signals', true)
            ->assertJsonPath('data.learning_context.matching_insights.0.suggested_action', 'review_size_too_small')
            ->assertJsonCount(2, 'data.suggestion.rows');

        $log = AiUsageLog::query()->firstOrFail();

        $this->assertSame('measurement_table_suggestion', $log->feature);
        $this->assertSame('local_parser', $log->provider);
        $this->assertSame('completed', $log->status);
        $this->assertNotNull($log->input_fingerprint);
        $this->assertTrue($log->summary['learning_context_used']);
        $this->assertStringNotContainsString('88-94', json_encode($log->summary));
    }

    public function test_image_source_requires_provider_when_secret_is_missing(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->postJson('/api/v1/ai/measurement-table-suggestions', [
                'source_type' => 'image',
                'filename' => 'tabela.png',
                'image_data' => 'data:image/png;base64,'.base64_encode('fake-image'),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'needs_provider')
            ->assertJsonPath('data.review_required', true)
            ->assertJsonCount(0, 'data.suggestion.rows');

        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'measurement_table_suggestion',
            'status' => 'needs_provider',
            'input_type' => 'image',
        ]);
    }

    public function test_status_reports_missing_external_secret_without_exposing_values(): void
    {
        config(['services.ai.provider' => 'openai']);

        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/ai/status')
            ->assertOk()
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.missing_secret', 'OPENAI_API_KEY');
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
