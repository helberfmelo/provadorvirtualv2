<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccessApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_saas_users_with_permissions(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->adminToken()];

        $created = $this->withHeaders($headers)
            ->postJson('/api/v1/saas/users', [
                'name' => 'Suporte Operacional',
                'email' => 'suporte@example.com',
                'cpf' => '05521345620',
                'password' => 'password123',
                'role' => 'support',
                'status' => 'active',
                'permissions' => [
                    'saas_users' => ['view' => false, 'edit' => true],
                    'saas_emails' => ['view' => true, 'edit' => false],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'support')
            ->assertJsonPath('data.permissions.saas_users.view', true)
            ->assertJsonPath('data.permissions.saas_users.edit', true)
            ->json('data');

        $this->withHeaders($headers)
            ->patchJson('/api/v1/saas/users/'.$created['id'], [
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->postJson('/api/v1/auth/login', [
            'login' => 'suporte@example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('login');
    }

    public function test_merchant_owner_can_manage_company_users_and_permissions(): void
    {
        $this->seed();
        $ownerHeaders = ['Authorization' => 'Bearer '.$this->merchantToken()];
        $companyCode = now()->year.'0001';

        $created = $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/merchant/users', [
                'name' => 'Gerente Loja',
                'email' => 'gerente@example.com',
                'cpf' => '11122233344',
                'password' => 'password123',
                'merchant_role' => 'manager',
                'merchant_user_status' => 'active',
                'is_owner' => false,
                'permissions' => [
                    'products' => ['view' => false, 'edit' => true],
                    'users' => ['view' => true, 'edit' => false],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.access.role', 'manager')
            ->assertJsonPath('data.access.permissions.products.view', true)
            ->assertJsonPath('data.access.permissions.products.edit', true)
            ->assertJsonPath('data.access.permissions.users.edit', false)
            ->json('data');

        $this->postJson('/api/v1/auth/login', [
            'login' => '111.222.333-44',
            'password' => 'password123',
            'company_access' => $companyCode,
        ])
            ->assertOk()
            ->assertJsonPath('active_company.access_code', $companyCode);

        $this->withHeaders($ownerHeaders)
            ->patchJson('/api/v1/merchant/users/'.$created['id'], [
                'merchant_user_status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.access.status', 'inactive');

        $this->postJson('/api/v1/auth/login', [
            'login' => 'gerente@example.com',
            'password' => 'password123',
            'company_access' => $companyCode,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('company_access');
    }

    public function test_merchant_user_without_edit_permission_cannot_manage_users(): void
    {
        $this->seed();
        $ownerHeaders = ['Authorization' => 'Bearer '.$this->merchantToken()];

        $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/merchant/users', [
                'name' => 'Leitor Usuarios',
                'email' => 'leitor@example.com',
                'password' => 'password123',
                'merchant_role' => 'staff',
                'merchant_user_status' => 'active',
                'permissions' => [
                    'users' => ['view' => true, 'edit' => false],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'leitor@example.com');

        $this->flushHeaders();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'leitor@example.com',
            'password' => 'password123',
            'company_access' => now()->year.'0001',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'leitor@example.com')
            ->json('token');

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'leitor@example.com')
            ->assertJsonPath('permissions.users.view', true)
            ->assertJsonPath('permissions.users.edit', false);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/merchant/users')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->postJson('/api/v1/merchant/users', [
                'name' => 'Outro Usuario',
                'email' => 'outro@example.com',
                'password' => 'password123',
                'merchant_role' => 'staff',
                'permissions' => [
                    'products' => ['view' => true, 'edit' => false],
                ],
            ])
            ->assertForbidden();
    }

    public function test_merchant_user_without_product_permission_is_blocked_and_audited(): void
    {
        $this->seed();
        $ownerHeaders = ['Authorization' => 'Bearer '.$this->merchantToken()];
        $companyCode = now()->year.'0001';

        $this->withHeaders($ownerHeaders)
            ->postJson('/api/v1/merchant/users', [
                'name' => 'Leitor Restrito',
                'email' => 'restrito@example.com',
                'password' => 'password123',
                'merchant_role' => 'staff',
                'merchant_user_status' => 'active',
                'permissions' => [
                    'dashboard' => ['view' => true, 'edit' => false],
                ],
            ])
            ->assertCreated();

        $this->flushHeaders();

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'restrito@example.com',
            'password' => 'password123',
            'company_access' => $companyCode,
        ])
            ->assertOk()
            ->assertJsonPath('permissions.products.view', false)
            ->assertJsonPath('permissions.dashboard.view', true)
            ->json('token');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/products')
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'permission.denied',
            'module' => 'products',
            'action' => 'view',
            'severity' => 'warning',
        ]);
    }

    private function adminToken(): string
    {
        User::query()->create([
            'name' => 'Admin SaaS',
            'email' => 'admin@provadorvirtual.online',
            'role' => 'admin',
            'status' => 'active',
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
