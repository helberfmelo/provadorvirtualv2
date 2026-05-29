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
            ->assertJsonPath('data.summary.products', 4);

        $this->withHeaders($headers)
            ->getJson('/api/v1/saas/merchants')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'provador-virtual-demo');
    }

    public function test_admin_can_create_company_without_checkout_and_public_access_resolves_it(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];
        $expectedAccessCode = now()->year.'0002';

        $company = $this->withHeaders($headers)
            ->postJson('/api/v1/saas/companies', [
                'merchant_name' => 'Loja Piloto',
                'billing_status' => 'trialing',
                'name' => 'Loja Piloto Teste',
                'legal_name' => 'Loja Piloto Teste Ltda',
                'document' => '11222333000181',
                'zip_code' => '01001000',
                'street' => 'Praca da Se',
                'number' => '10',
                'district' => 'Se',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'platform' => 'custom',
            ])
            ->assertCreated()
            ->assertJsonPath('data.access_code', $expectedAccessCode)
            ->json('data');

        $this->postJson('/api/v1/public/company-access', [
            'code_or_document' => $company['access_code'],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Loja Piloto Teste');
    }

    public function test_admin_controls_bigshop_commercial_benefit_separately_from_platform(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $company = $this->withHeaders($headers)
            ->postJson('/api/v1/saas/companies', [
                'merchant_name' => 'Loja BigShop',
                'billing_status' => 'active',
                'name' => 'Loja BigShop',
                'legal_name' => 'Loja BigShop Ltda',
                'document' => '11222333000181',
                'platform' => 'bigshop',
                'external_store_id' => '124',
            ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.bigshop_discount_active', true)
            ->json('data');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/saas/companies/'.$company['id'], [
                'name' => 'Loja BigShop',
                'legal_name' => 'Loja BigShop Ltda',
                'document' => '11222333000181',
                'platform' => 'bigshop',
                'bigshop_discount_active' => false,
                'external_store_id' => '124',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'bigshop')
            ->assertJsonPath('data.bigshop_discount_active', false);
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
