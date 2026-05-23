<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SaasAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_saas_overview_and_merchants(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/overview')
            ->assertOk()
            ->assertJsonPath('data.summary.merchants', 1)
            ->assertJsonPath('data.summary.products', 1);

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/merchants')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'loja-luna-demo');
    }

    public function test_merchant_cannot_read_saas_admin(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->merchantToken()];

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/overview')
            ->assertForbidden();
    }

    private function adminToken(): string
    {
        User::query()->create([
            'name' => 'Admin SaaS',
            'email' => 'admin@provadorvirtual.online',
            'role' => 'admin',
            'password' => Hash::make('admin12345'),
        ]);

        return $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@provadorvirtual.online',
            'password' => 'admin12345',
        ])->assertOk()->json('token');
    }

    private function merchantToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
