<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoLiveReadinessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_read_go_live_readiness(): void
    {
        config()->set('services.mercado_pago.access_token', null);
        config()->set('services.mercado_pago.public_key', null);
        config()->set('services.pagarme.secret_key', null);
        config()->set('services.pagarme.public_key', null);

        $this->seed();

        $token = $this->loginToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/go-live/readiness')
            ->assertOk()
            ->assertJsonPath('summary.status', 'ready_with_warnings')
            ->assertJsonPath('summary.status_label', 'Pronto com avisos')
            ->assertJsonPath('missing_credentials.bigshop_activation_secret', true)
            ->assertJsonPath('missing_credentials.bigshop_test_store', true)
            ->assertJsonPath('missing_credentials.external_ai_key', true)
            ->assertJsonPath('missing_credentials.checkout_provider_keys', true)
            ->assertJsonStructure([
                'connected_data' => [
                    'coverage' => ['status', 'summary', 'detail', 'link', 'metrics'],
                    'widget' => ['status', 'summary', 'detail', 'link', 'metrics'],
                    'sync' => ['status', 'summary', 'detail', 'link', 'metrics'],
                ],
                'report' => [
                    'title',
                    'generated_at',
                    'status_label',
                    'headline',
                    'summary',
                    'blockers',
                    'warnings',
                    'recommendations',
                    'text',
                ],
                'pilot_package' => [
                    'status',
                    'sales_assets',
                    'onboarding_steps',
                    'automation_commands',
                    'pending_real_world_tests',
                ],
            ]);

        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'products'));
        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'product_test'));
        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'catalog_coverage'));
        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'product_data_quality'));
        $this->assertSame('warning', $this->statusFor($response->json('checks'), 'widget_publication'));
        $this->assertSame('warning', $this->statusFor($response->json('checks'), 'sync_health'));
        $this->assertSame('warning', $this->statusFor($response->json('checks'), 'bigshop_pilot'));
        $this->assertSame('warning', $this->statusFor($response->json('checks'), 'checkout_provider'));
        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'widget_performance'));
        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'accessibility_mobile'));
        $this->assertSame('/app/produtos?filtro=pendentes', $response->json('connected_data.coverage.link'));
        $this->assertSame('/app/widget', $response->json('connected_data.widget.link'));
        $this->assertSame('/app/sincronizacao', $response->json('connected_data.sync.link'));
        $this->assertNotEmpty($response->json('report.text'));
    }

    public function test_readiness_blocks_release_without_product_measurement_table(): void
    {
        $this->seed();
        Product::query()->update(['measurement_table_id' => null]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->loginToken())
            ->getJson('/api/v1/go-live/readiness')
            ->assertOk()
            ->assertJsonPath('summary.status', 'blocked')
            ->assertJsonPath('connected_data.coverage.status', 'blocked')
            ->assertJsonPath('connected_data.coverage.ready_products', 0);

        $this->assertGreaterThanOrEqual(2, (int) $response->json('summary.blockers'));
    }

    private function statusFor(array $checks, string $key): ?string
    {
        foreach ($checks as $check) {
            if ($check['key'] === $key) {
                return $check['status'];
            }
        }

        return null;
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
