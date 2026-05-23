<?php

namespace Tests\Feature;

use App\Models\RecommendationFeedback;
use App\Models\RecommendationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_recommendation_flow_creates_log_and_feedback(): void
    {
        $this->seed();

        $check = $this->postJson('/api/v1/public/recommendations/config-check', [
            'merchant_id' => 1,
            'store_id' => 1,
            'product_id' => 1,
            'platform' => 'custom',
        ]);

        $check->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('available_sizes.2', 'M');

        $recommendation = $this->postJson('/api/v1/public/recommendations', [
            'merchant_id' => 1,
            'store_id' => 1,
            'product_id' => 1,
            'platform' => 'custom',
            'measurements' => [
                'bust' => 92,
                'waist' => 74,
                'hip' => 100,
                'height' => 166,
                'weight' => 62,
            ],
        ]);

        $recommendation->assertCreated()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('recommended_size', 'M')
            ->assertJsonPath('needs_more_data', false)
            ->assertJsonStructure([
                'recommendation_id',
                'session_id',
                'confidence',
                'fit_notes',
                'score_breakdown',
            ]);

        $this->assertDatabaseHas('recommendation_logs', [
            'id' => $recommendation->json('recommendation_id'),
            'recommended_size' => 'M',
            'status' => 'recommended',
        ]);

        $this->postJson("/api/v1/public/recommendations/{$recommendation->json('recommendation_id')}/feedback", [
            'was_helpful' => true,
            'rating' => 5,
            'selected_size' => 'M',
        ])->assertCreated();

        $this->assertSame(1, RecommendationLog::query()->count());
        $this->assertSame(1, RecommendationFeedback::query()->count());
    }

    public function test_recommendation_requires_configured_measurement_table(): void
    {
        $this->seed();

        $this->postJson('/api/v1/public/recommendations', [
            'merchant_id' => 1,
            'store_id' => 1,
            'product_id' => 'missing',
            'measurements' => ['bust' => 90],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('configured', false);
    }
}
