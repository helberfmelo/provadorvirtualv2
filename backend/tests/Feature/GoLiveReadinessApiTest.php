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
        $this->seed();

        $token = $this->loginToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/go-live/readiness')
            ->assertOk()
            ->assertJsonPath('summary.status', 'ready_with_warnings')
            ->assertJsonPath('missing_credentials.bigshop_activation_secret', true)
            ->assertJsonPath('missing_credentials.bigshop_test_store', true)
            ->assertJsonPath('missing_credentials.external_ai_key', true);

        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'products'));
        $this->assertSame('passed', $this->statusFor($response->json('checks'), 'product_test'));
        $this->assertSame('warning', $this->statusFor($response->json('checks'), 'bigshop_pilot'));
    }

    public function test_readiness_blocks_release_without_product_measurement_table(): void
    {
        $this->seed();
        Product::query()->update(['measurement_table_id' => null]);

        $this->withHeader('Authorization', 'Bearer '.$this->loginToken())
            ->getJson('/api/v1/go-live/readiness')
            ->assertOk()
            ->assertJsonPath('summary.status', 'blocked')
            ->assertJsonPath('summary.blockers', 1);
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
