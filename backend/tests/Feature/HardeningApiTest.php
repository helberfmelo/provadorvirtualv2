<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\IntegrationEvent;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\RecommendationFeedback;
use App\Models\RecommendationLearningEvent;
use App\Models\RecommendationLog;
use App\Models\RecommendationSession;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HardeningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_status_reports_core_checks(): void
    {
        $this->getJson('/api/v1/ops/status')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure(['status', 'checks', 'app_env', 'timestamp']);
    }

    public function test_widget_public_api_rejects_unlisted_origin(): void
    {
        $this->seed();

        $this->withHeader('Origin', 'https://evil.example')
            ->postJson('/api/v1/public/recommendations/config-check', [
                'merchant_id' => 1,
                'store_id' => 1,
                'product_id' => 1,
                'platform' => 'custom',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Origem não autorizada para este widget.');
    }

    public function test_widget_public_api_allows_configured_origin(): void
    {
        $this->seed();

        $this->withHeader('Origin', 'https://provadorvirtual.online')
            ->postJson('/api/v1/public/recommendations/config-check', [
                'merchant_id' => 1,
                'store_id' => 1,
                'product_id' => 1,
                'platform' => 'custom',
            ])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://provadorvirtual.online')
            ->assertJsonPath('configured', true);
    }

    public function test_bigshop_widget_public_api_allows_origin_with_external_store_id(): void
    {
        $this->seed();

        MerchantCompany::query()
            ->whereKey(1)
            ->update([
                'platform' => 'bigshop',
                'external_store_id' => '53',
            ]);

        $this->withHeader('Origin', 'https://provadorvirtual.online')
            ->postJson('/api/v1/public/recommendations/config-check', [
                'store_id' => 53,
                'product_id' => 1,
                'platform' => 'bigshop',
            ])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://provadorvirtual.online')
            ->assertJsonPath('configured', true);
    }

    public function test_bigshop_widget_public_api_allows_origin_with_platform_connection_store_id(): void
    {
        $this->seed();

        PlatformConnection::query()->create([
            'merchant_id' => 1,
            'merchant_company_id' => 1,
            'platform' => 'bigshop',
            'external_store_id' => '53',
            'status' => 'connected',
        ]);

        $this->withHeader('Origin', 'https://provadorvirtual.online')
            ->postJson('/api/v1/public/recommendations/config-check', [
                'store_id' => 53,
                'product_id' => 1,
                'platform' => 'bigshop',
            ])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://provadorvirtual.online')
            ->assertJsonPath('configured', true);
    }

    public function test_login_route_is_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rate-limit@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rate-limit@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_audit_logger_masks_sensitive_metadata_recursively(): void
    {
        $request = Request::create('/api/v1/test', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Feature Test',
        ]);

        $log = app(AuditLogger::class)->log($request, null, 'security.mask_test', 'security', 'info', [
            'access_token' => 'plain-token',
            'headers' => [
                'Authorization' => 'Bearer plain-token',
                'x-api' => 'plain-key',
                'safe' => 'kept',
            ],
        ]);

        $this->assertSame('[masked]', $log->metadata['access_token']);
        $this->assertSame('[masked]', $log->metadata['headers']['Authorization']);
        $this->assertSame('[masked]', $log->metadata['headers']['x-api']);
        $this->assertSame('kept', $log->metadata['headers']['safe']);
    }

    public function test_privacy_anonymize_removes_old_widget_body_data(): void
    {
        $this->seed();

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
            'shopper_profile' => [
                'fit_preference' => 'regular',
                'raw_widget_data' => [
                    'source' => 'widget_v2_staged',
                    'precision' => 80,
                    'raw_measurements' => [
                        'altura' => 166,
                        'peso' => 62,
                    ],
                ],
            ],
        ])->assertCreated();

        $this->postJson("/api/v1/public/recommendations/{$recommendation->json('recommendation_id')}/feedback", [
            'was_helpful' => false,
            'rating' => 3,
            'selected_size' => 'M',
            'comment' => 'Comentario pessoal do consumidor.',
        ])->assertCreated();

        $old = now()->subDays(45);
        $log = RecommendationLog::query()->findOrFail($recommendation->json('recommendation_id'));

        RecommendationSession::query()
            ->whereKey($log->recommendation_session_id)
            ->update(['created_at' => $old, 'updated_at' => $old]);

        $log->forceFill(['created_at' => $old, 'updated_at' => $old])->save();
        RecommendationFeedback::query()->firstOrFail()->forceFill(['created_at' => $old])->save();
        RecommendationLearningEvent::query()->update(['created_at' => $old, 'updated_at' => $old]);

        Artisan::call('pv:privacy-anonymize', ['--days' => 30]);

        $session = RecommendationSession::query()->findOrFail($log->recommendation_session_id);
        $log->refresh();
        $feedback = RecommendationFeedback::query()->firstOrFail();
        $learningEvent = RecommendationLearningEvent::query()->firstOrFail();

        $this->assertNull($session->shopper_profile);
        $this->assertNull($session->ip_hash);
        $this->assertNull($session->user_agent_hash);
        $this->assertNull($log->input_measurements);
        $this->assertNull($log->raw_widget_payload);
        $this->assertNull($log->score_breakdown);
        $this->assertNull($feedback->comment);
        $this->assertNull($learningEvent->payload);
    }

    public function test_privacy_prune_removes_old_operational_logs(): void
    {
        $this->seed();

        $merchant = Merchant::query()->firstOrFail();
        $old = now()->subDays(210);

        $audit = AuditLog::query()->create([
            'merchant_id' => $merchant->id,
            'event' => 'old.audit',
            'category' => 'security',
            'severity' => 'info',
            'created_at' => $old,
            'updated_at' => $old,
        ]);

        $aiUsage = AiUsageLog::query()->create([
            'merchant_id' => $merchant->id,
            'feature' => 'measurement_table_suggestion',
            'provider' => 'local',
            'status' => 'completed',
            'created_at' => $old,
            'updated_at' => $old,
        ]);

        $integrationEvent = IntegrationEvent::query()->create([
            'merchant_id' => $merchant->id,
            'platform' => 'bigshop',
            'event_type' => 'old.sync',
            'status' => 'ok',
            'created_at' => $old,
            'updated_at' => $old,
        ]);

        Artisan::call('pv:privacy-prune', ['--days' => 180]);

        $this->assertDatabaseMissing('audit_logs', ['id' => $audit->id]);
        $this->assertDatabaseMissing('ai_usage_logs', ['id' => $aiUsage->id]);
        $this->assertSoftDeleted('integration_events', ['id' => $integrationEvent->id]);
    }
}
