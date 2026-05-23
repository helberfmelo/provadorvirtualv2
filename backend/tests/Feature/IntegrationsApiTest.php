<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_configure_platform_connection(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'bigshop')
            ->assertJsonPath('data.0.priority', true)
            ->assertJsonPath('data.0.status', 'draft');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/integrations/bigshop', [
                'external_store_id' => 'loja-demo-bigshop',
                'api_base_url' => 'https://api.bigshop.test',
                'access_token' => 'token-secreto',
                'webhook_secret' => 'assinatura-secreta',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.external_store_id', 'loja-demo-bigshop')
            ->assertJsonPath('data.status', 'configured')
            ->assertJsonPath('data.has_access_token', true)
            ->assertJsonPath('data.has_webhook_secret', true)
            ->assertJsonMissing(['access_token' => 'token-secreto']);

        $this->withHeaders($headers)
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'configured')
            ->assertJsonPath('data.0.connection.has_access_token', true);
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
