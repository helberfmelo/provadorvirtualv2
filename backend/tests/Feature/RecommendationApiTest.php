<?php

namespace Tests\Feature;

use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\RecommendationSession;
use App\Models\ShopperProfile;
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

    public function test_bigshop_widget_resolves_company_by_external_store_id(): void
    {
        $this->seed();

        MerchantCompany::query()
            ->whereKey(1)
            ->update([
                'platform' => 'bigshop',
                'external_store_id' => '53',
            ]);

        $this->postJson('/api/v1/public/recommendations/config-check', [
            'store_id' => 53,
            'product_id' => 1,
            'platform' => 'bigshop',
        ])
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('available_sizes.2', 'M');

        $this->postJson('/api/v1/public/recommendations', [
            'store_id' => 53,
            'product_id' => 1,
            'platform' => 'bigshop',
            'measurements' => [
                'bust' => 92,
                'waist' => 74,
                'hip' => 100,
                'height' => 166,
                'weight' => 62,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('recommended_size', 'M');
    }

    public function test_bigshop_widget_resolves_company_by_platform_connection_store_id(): void
    {
        $this->seed();

        PlatformConnection::query()->create([
            'merchant_id' => 1,
            'merchant_company_id' => 1,
            'platform' => 'bigshop',
            'external_store_id' => '53',
            'status' => 'connected',
        ]);

        $this->postJson('/api/v1/public/recommendations/config-check', [
            'store_id' => 53,
            'product_id' => 1,
            'platform' => 'bigshop',
        ])
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('available_sizes.2', 'M');
    }

    public function test_shopper_profile_with_consent_is_reused_and_can_be_forgotten(): void
    {
        $this->seed();

        $first = $this->postJson('/api/v1/public/recommendations', [
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
            'shopper_profile' => [
                'consent_measurements' => true,
                'gender' => 'female',
                'body_shape' => 'hourglass',
                'fit_preference' => 'regular',
            ],
        ])->assertCreated()
            ->assertJsonPath('shopper_profile.consent', true)
            ->assertJsonPath('shopper_profile.known_profile', false)
            ->assertJsonPath('learning.status', 'accepted');

        $profileId = $first->json('shopper_profile.id');
        $profileToken = $first->json('shopper_profile.token');

        $this->assertNotEmpty($profileId);
        $this->assertNotEmpty($profileToken);
        $this->assertSame(1, ShopperProfile::query()->count());
        $this->assertSame(1, RecommendationLearningEvent::query()->where('status', 'accepted')->count());
        $this->assertDatabaseHas('recommendation_sessions', [
            'shopper_profile_uuid' => $profileId,
            'consent_given' => true,
        ]);

        $this->postJson('/api/v1/public/recommendations', [
            'merchant_id' => 1,
            'store_id' => 1,
            'product_id' => 1,
            'platform' => 'custom',
            'measurements' => [
                'bust' => 94,
                'waist' => 76,
                'hip' => 101,
                'height' => 166,
                'weight' => 63,
            ],
            'shopper_profile' => [
                'profile_id' => $profileId,
                'profile_token' => $profileToken,
                'consent_measurements' => true,
                'gender' => 'female',
                'body_shape' => 'hourglass',
                'fit_preference' => 'regular',
            ],
        ])->assertCreated()
            ->assertJsonPath('shopper_profile.id', $profileId)
            ->assertJsonPath('shopper_profile.known_profile', true);

        $profile = ShopperProfile::query()->firstOrFail();
        $this->assertSame('known', $profile->profile_type);
        $this->assertSame(76.0, (float) $profile->measurements['waist']);
        $this->assertSame(2, RecommendationSession::query()->where('shopper_profile_uuid', $profileId)->count());

        $this->postJson('/api/v1/public/shopper-profiles/forget', [
            'profile_id' => $profileId,
            'profile_token' => $profileToken,
        ])->assertOk()
            ->assertJsonPath('forgotten', true);

        $this->assertDatabaseHas('shopper_profiles', [
            'uuid' => $profileId,
            'status' => 'forgotten',
        ]);
    }

    public function test_outlier_learning_blocks_extreme_signals_before_training(): void
    {
        $this->seed();

        $recommendation = $this->postJson('/api/v1/public/recommendations', [
            'merchant_id' => 1,
            'store_id' => 1,
            'product_id' => 1,
            'platform' => 'custom',
            'measurements' => [
                'bust' => 200,
                'waist' => 190,
                'hip' => 230,
                'height' => 140,
                'weight' => 120,
            ],
            'shopper_profile' => [
                'consent_measurements' => true,
                'fit_preference' => 'regular',
            ],
        ])->assertCreated()
            ->assertJsonPath('learning.status', 'blocked_outlier');

        $recommendationId = $recommendation->json('recommendation_id');

        $this->assertDatabaseHas('recommendation_logs', [
            'id' => $recommendationId,
            'learning_status' => 'blocked_outlier',
        ]);

        $this->postJson("/api/v1/public/recommendations/{$recommendationId}/signal", [
            'signal' => 'return',
            'selected_size' => 'P',
            'source' => 'checkout',
            'order_reference' => 'ORDER-123',
        ])->assertCreated()
            ->assertJsonPath('learning_status', 'blocked_outlier');

        $this->assertSame(2, RecommendationLearningEvent::query()->where('status', 'blocked_outlier')->count());
    }
}
