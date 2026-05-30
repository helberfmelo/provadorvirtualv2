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

    public function test_merchant_can_read_recommendation_and_widget_usage_analytics(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $merchant = Merchant::query()->where('slug', 'provador-virtual-demo')->firstOrFail();
        $product = Product::query()->where('sku', 'PV-AURORA-MIDI')->firstOrFail();
        $mobileUserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

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
            'shopper_profile' => [
                'consent_measurements' => true,
                'fit_preference' => 'regular',
            ],
        ])->assertCreated()->json('recommendation_id');

        $this->withHeader('User-Agent', $mobileUserAgent)
            ->postJson('/api/v1/public/widget-events', [
                'merchant_id' => $merchant->id,
                'product_id' => $product->id,
                'platform' => 'custom',
                'event_name' => 'button_impression',
                'event_id' => 'evt-button-impression-1',
                'session_key' => 'session-1',
                'visit_key' => 'visit-1',
                'payload' => ['presentation_mode' => 'drawer'],
            ])
            ->assertCreated()
            ->assertJsonPath('tracked', true)
            ->assertJsonPath('duplicate', false);

        $this->withHeader('User-Agent', $mobileUserAgent)
            ->postJson('/api/v1/public/widget-events', [
                'merchant_id' => $merchant->id,
                'product_id' => $product->id,
                'platform' => 'custom',
                'event_name' => 'button_impression',
                'event_id' => 'evt-button-impression-1',
                'session_key' => 'session-1',
                'visit_key' => 'visit-1',
            ])
            ->assertOk()
            ->assertJsonPath('tracked', true)
            ->assertJsonPath('duplicate', true);

        foreach ([
            ['virtual_try_on_open', 'evt-open-1'],
            ['measurement_table_open', 'evt-table-1'],
            ['recommendation_generated', 'evt-recommendation-1'],
            ['size_selected', 'evt-size-1'],
            ['feedback_submitted', 'evt-feedback-1'],
        ] as [$eventName, $eventId]) {
            $payload = [
                'merchant_id' => $merchant->id,
                'product_id' => $product->id,
                'platform' => 'custom',
                'event_name' => $eventName,
                'event_id' => $eventId,
                'recommendation_id' => in_array($eventName, ['recommendation_generated', 'size_selected', 'feedback_submitted'], true)
                    ? $recommendationId
                    : null,
                'selected_size' => in_array($eventName, ['size_selected', 'feedback_submitted'], true) ? 'M' : null,
                'session_key' => 'session-1',
                'visit_key' => 'visit-1',
            ];

            $this->withHeader('User-Agent', $mobileUserAgent)
                ->postJson('/api/v1/public/widget-events', $payload)
                ->assertCreated();
        }

        $this->postJson("/api/v1/public/recommendations/{$recommendationId}/feedback", [
            'was_helpful' => true,
            'rating' => 5,
        ])->assertCreated();

        $this->postJson("/api/v1/public/recommendations/{$recommendationId}/signal", [
            'signal' => 'purchase',
            'ordered_size' => 'M',
            'source' => 'checkout',
            'source_platform' => 'bigshop',
            'order_reference' => 'ORDER-ANALYTICS-1',
        ])->assertCreated();

        $this->postJson("/api/v1/public/recommendations/{$recommendationId}/signal", [
            'signal' => 'return',
            'returned_size' => 'M',
            'return_reason' => 'size_too_small',
            'source' => 'returns_api',
            'source_platform' => 'bigshop',
            'order_reference' => 'ORDER-ANALYTICS-1',
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
            ->assertJsonPath('data.summary.shopper_profiles_total', 1)
            ->assertJsonPath('data.summary.learning_events_total', 4)
            ->assertJsonPath('data.summary.commerce_purchases', 1)
            ->assertJsonPath('data.summary.commerce_returns', 1)
            ->assertJsonPath('data.summary.commerce_return_rate', 100)
            ->assertJsonPath('data.summary.measurement_table_insights_review', 1)
            ->assertJsonPath('data.learning_pipeline.summary.ready_for_learning', 2)
            ->assertJsonPath('data.learning_pipeline.manual_review_queue.0.review_required', true)
            ->assertJsonPath('data.learning_pipeline.manual_review_queue.0.suggested_adjustment.direction', 'increase_tolerance')
            ->assertJsonPath('data.learning_pipeline.patterns.by_fit_profile.0.label', 'regular')
            ->assertJsonPath('data.learning_pipeline.privacy.order_reference_policy', 'hash_only')
            ->assertJsonPath('data.sizes.0.size', 'M')
            ->assertJsonPath('data.learning_statuses.0.status', 'accepted')
            ->assertJsonPath('data.measurement_table_insights.0.suggested_action', 'review_size_too_small')
            ->assertJsonPath('data.measurement_table_insights.0.suggested_adjustment.direction', 'increase_tolerance')
            ->assertJsonPath('data.product_ranking.0.product_id', $product->id)
            ->assertJsonPath('data.product_ranking.0.button_impressions', 1)
            ->assertJsonPath('data.product_ranking.0.recommendations_generated', 1)
            ->assertJsonPath('data.product_ranking.0.returns_exchanges', 1)
            ->assertJsonPath('data.recommendation_report.data.0.recommendation_id', $recommendationId)
            ->assertJsonPath('data.recommendation_report.data.0.recommended_size', 'M')
            ->assertJsonPath('data.recommendation_report.data.0.device_type', 'mobile')
            ->assertJsonPath('data.recommendation_report.meta.total', 1)
            ->assertJsonFragment(['id' => $product->id, 'name' => 'Vestido Midi Aurora']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/analytics/widget-usage?period=30d&device_type=mobile')
            ->assertOk()
            ->assertJsonPath('data.summary.button_impressions', 1)
            ->assertJsonPath('data.summary.virtual_try_on_opens', 1)
            ->assertJsonPath('data.summary.measurement_table_opens', 1)
            ->assertJsonPath('data.summary.recommendations_generated', 1)
            ->assertJsonPath('data.summary.size_selections', 1)
            ->assertJsonPath('data.summary.feedback_submitted', 1)
            ->assertJsonPath('data.summary.conversions', 1)
            ->assertJsonPath('data.summary.usage_rate', 100)
            ->assertJsonPath('data.summary.selection_rate', 100)
            ->assertJsonPath('data.summary.conversion_rate', 100)
            ->assertJsonPath('data.device_distribution.0.device_type', 'mobile')
            ->assertJsonPath('data.funnel.0.key', 'button_impression')
            ->assertJsonPath('data.filter_options.platforms.0', 'custom');

        $rankingExport = $this->withHeaders($headers)
            ->get('/api/v1/analytics/recommendations/export?report=ranking&device_type=mobile');

        $rankingExport->assertOk();
        $this->assertStringContainsString('text/csv', (string) $rankingExport->headers->get('content-type'));
        $this->assertStringContainsString('Vestido Midi Aurora', $rankingExport->getContent());

        $recommendationExport = $this->withHeaders($headers)
            ->get('/api/v1/analytics/recommendations/export?report=recommendations&device_type=mobile');

        $recommendationExport->assertOk();
        $this->assertStringContainsString('provador-virtual-recomendacoes.csv', (string) $recommendationExport->headers->get('content-disposition'));
        $this->assertStringContainsString((string) $recommendationId, $recommendationExport->getContent());

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
