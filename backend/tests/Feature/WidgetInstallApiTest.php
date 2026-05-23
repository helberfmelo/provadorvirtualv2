<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetInstallApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_view_and_update_widget_install(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $response = $this->withHeaders($headers)
            ->getJson('/api/v1/widget-install')
            ->assertOk()
            ->assertJsonPath('data.public_key', 'pv_demo_luna')
            ->assertJsonPath('data.platform', 'custom')
            ->assertJsonPath('data.is_active', true);

        $this->assertStringContainsString('provador-virtual.js', $response->json('data.snippet'));
        $this->assertStringContainsString('data-merchant-id', $response->json('data.snippet'));

        $this->withHeaders($headers)
            ->patchJson('/api/v1/widget-install', [
                'platform' => 'bigshop',
                'allowed_domains' => ['ProvadorVirtual.Online', 'localhost', 'localhost'],
                'theme' => [
                    'primary' => '#101820',
                    'secondary' => '#ff4d5e',
                    'accent' => '#17a398',
                ],
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.allowed_domains.0', 'provadorvirtual.online')
            ->assertJsonPath('data.allowed_domains.1', 'localhost')
            ->assertJsonPath('data.theme.primary', '#101820')
            ->assertJsonPath('data.is_active', false);
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
