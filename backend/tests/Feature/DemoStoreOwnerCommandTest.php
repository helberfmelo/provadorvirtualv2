<?php

namespace Tests\Feature;

use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoStoreOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_be_registered_as_demo_store_owner(): void
    {
        $this->seed();

        $this->artisan('pv:ensure-demo-store-owner', [
            '--email' => 'helber@bigshop.com.br',
            '--name' => 'Helber Melo',
            '--cpf' => '05521345620',
            '--password' => 'secret123',
        ])->assertExitCode(0);

        $company = MerchantCompany::query()
            ->where('external_store_id', 'pv-demo-store')
            ->firstOrFail();
        $user = User::query()
            ->where('email', 'helber@bigshop.com.br')
            ->firstOrFail();

        $this->assertSame('admin', $user->role);
        $this->assertDatabaseHas('merchant_user', [
            'merchant_id' => $company->merchant_id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'is_owner' => 1,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '055.213.456-20',
            'password' => 'secret123',
            'company_access' => '12.345.678/0001-95',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'helber@bigshop.com.br')
            ->assertJsonPath('active_company.id', $company->id)
            ->assertJsonPath('permissions.users.edit', true)
            ->assertJsonPath('saas_permissions.saas_users.edit', true);
    }
}
