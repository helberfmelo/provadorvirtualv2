<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_can_login_and_read_profile(): void
    {
        $this->seed();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.email', 'demo@provadorvirtual.online')
            ->assertJsonPath('active_company.access_code', now()->year.'0001')
            ->assertJsonStructure(['token']);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'demo@provadorvirtual.online')
            ->assertJsonPath('active_company.access_code', now()->year.'0001')
            ->assertJsonCount(1, 'merchants');
    }

    public function test_user_can_login_with_cpf_and_company_document(): void
    {
        $user = User::query()->create([
            'name' => 'Lojista CPF',
            'email' => 'cpf@example.com',
            'cpf' => '05521345620',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);
        $merchant = Merchant::query()->create([
            'name' => 'Loja CPF',
            'slug' => 'loja-cpf',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja CPF Ltda',
            'document' => '11222333000181',
            'platform' => 'bigshop',
            'status' => 'active',
        ]);
        $company->ensureAccessCode();
        $user->merchants()->attach($merchant->id, ['role' => 'owner', 'is_owner' => true]);

        $this->postJson('/api/v1/auth/login', [
            'login' => '055.213.456-20',
            'password' => 'password123',
            'company_access' => '11.222.333/0001-81',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'cpf@example.com')
            ->assertJsonPath('active_merchant.id', $merchant->id)
            ->assertJsonPath('active_company.id', $company->id);
    }

    public function test_multi_company_user_must_send_company_code_or_document(): void
    {
        $user = User::query()->create([
            'name' => 'Multi Loja',
            'email' => 'multi@example.com',
            'cpf' => '11122233344',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);

        foreach ([1, 2] as $index) {
            $merchant = Merchant::query()->create([
                'name' => 'Loja Multi '.$index,
                'slug' => 'loja-multi-'.$index,
                'billing_status' => 'active',
            ]);
            MerchantCompany::query()->create([
                'merchant_id' => $merchant->id,
                'name' => 'Loja Multi '.$index,
                'platform' => 'custom',
                'status' => 'active',
            ])->ensureAccessCode();
            $user->merchants()->attach($merchant->id, ['role' => 'owner', 'is_owner' => true]);
        }

        $this->postJson('/api/v1/auth/login', [
            'login' => 'multi@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Selecione a empresa para acessar o portal.')
            ->assertJsonCount(2, 'company_options');
    }

    public function test_user_can_switch_active_company_and_keep_data_scoped(): void
    {
        $user = User::query()->create([
            'name' => 'Switcher User',
            'email' => 'switcher@example.com',
            'cpf' => '33344455566',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);
        $merchant = Merchant::query()->create([
            'name' => 'Loja Switcher',
            'slug' => 'loja-switcher',
            'billing_status' => 'active',
        ]);
        $firstCompany = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Switcher Matriz',
            'platform' => 'custom',
            'status' => 'active',
        ]);
        $secondCompany = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Switcher Filial',
            'platform' => 'bigshop',
            'status' => 'active',
        ]);
        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $firstCompany->id,
            'slug' => 'produto-matriz',
            'name' => 'Produto Matriz',
            'status' => 'active',
        ]);
        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $secondCompany->id,
            'slug' => 'produto-filial',
            'name' => 'Produto Filial',
            'status' => 'active',
        ]);
        $user->merchants()->attach($merchant->id, ['role' => 'owner', 'is_owner' => true]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'switcher@example.com',
            'password' => 'password123',
            'company_access' => $firstCompany->access_code,
        ])
            ->assertOk()
            ->assertJsonPath('active_company.id', $firstCompany->id)
            ->assertJsonCount(2, 'company_options')
            ->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/merchant/overview')
            ->assertOk()
            ->assertJsonPath('summary.products', 1);

        $newToken = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/select-company', [
                'company_id' => $secondCompany->id,
            ])
            ->assertOk()
            ->assertJsonPath('active_company.id', $secondCompany->id)
            ->json('token');

        $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/v1/merchant/overview')
            ->assertOk()
            ->assertJsonPath('summary.products', 1);
    }

    public function test_company_code_selects_active_merchant_context(): void
    {
        $user = User::query()->create([
            'name' => 'Context User',
            'email' => 'context@example.com',
            'cpf' => '22233344455',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);

        $firstMerchant = Merchant::query()->create([
            'name' => 'Primeira Loja',
            'slug' => 'primeira-loja',
            'billing_status' => 'active',
        ]);
        $secondMerchant = Merchant::query()->create([
            'name' => 'Segunda Loja',
            'slug' => 'segunda-loja',
            'billing_status' => 'active',
        ]);
        MerchantCompany::query()->create([
            'merchant_id' => $firstMerchant->id,
            'name' => 'Primeira Loja',
            'platform' => 'custom',
            'status' => 'active',
        ])->ensureAccessCode();
        $secondCompany = MerchantCompany::query()->create([
            'merchant_id' => $secondMerchant->id,
            'name' => 'Segunda Loja',
            'platform' => 'bigshop',
            'status' => 'active',
        ]);
        $secondCompany->ensureAccessCode();
        Product::query()->create([
            'merchant_id' => $secondMerchant->id,
            'merchant_company_id' => $secondCompany->id,
            'slug' => 'produto-segunda-loja',
            'name' => 'Produto Segunda Loja',
            'status' => 'active',
        ]);
        $user->merchants()->attach($firstMerchant->id, ['role' => 'owner', 'is_owner' => true]);
        $user->merchants()->attach($secondMerchant->id, ['role' => 'owner', 'is_owner' => true]);

        $token = $this->postJson('/api/v1/auth/login', [
            'login' => 'context@example.com',
            'password' => 'password123',
            'company_access' => $secondCompany->access_code,
        ])
            ->assertOk()
            ->assertJsonPath('active_merchant.id', $secondMerchant->id)
            ->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/merchant/overview')
            ->assertOk()
            ->assertJsonPath('summary.products', 1);
    }

    public function test_admin_selected_company_resolves_merchant_without_pivot_link(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin.context@example.com',
            'cpf' => '12345678901',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);
        $merchant = Merchant::query()->create([
            'name' => 'Zak',
            'slug' => 'zak',
            'billing_status' => 'trialing',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Zak',
            'platform' => 'bigshop',
            'bigshop_discount_active' => true,
            'status' => 'active',
        ]);

        $token = $admin->createToken('admin-test', ['role:admin'])->plainTextToken;
        $companyToken = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/select-company', [
                'company_id' => $company->id,
            ])
            ->assertOk()
            ->assertJsonPath('active_company.id', $company->id)
            ->json('token');

        // Force the next test request to authenticate with the freshly issued tenant-scoped token.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$companyToken)
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'bigshop');
    }
}
