<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantCompanyProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_completes_company_profile_after_cnpj_only_checkout(): void
    {
        [$user, $merchant, $company] = $this->tenant();
        $headers = $this->headers($user, $merchant, $company);

        $this->withHeaders($headers)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('active_company.profile_completed', false);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/merchant/company-profile', [
                'name' => 'Loja Completa',
                'legal_name' => 'Loja Completa Ltda',
                'domain' => 'loja-completa.com.br',
                'platform' => 'bigshop',
                'zip_code' => '01001000',
                'street' => 'Praca da Se',
                'number' => '10',
                'complement' => 'Sala 2',
                'district' => 'Se',
                'city' => 'Sao Paulo',
                'state' => 'sp',
            ])
            ->assertOk()
            ->assertJsonPath('data.profile_completed', true)
            ->assertJsonPath('data.domain', 'loja-completa.com.br')
            ->assertJsonPath('data.state', 'SP');

        $this->assertDatabaseHas('merchant_companies', [
            'id' => $company->id,
            'name' => 'Loja Completa',
            'legal_name' => 'Loja Completa Ltda',
            'document' => '11222333000181',
            'domain' => 'loja-completa.com.br',
            'state' => 'SP',
        ]);
        $this->assertDatabaseHas('merchants', [
            'id' => $merchant->id,
            'name' => 'Loja Completa',
            'slug' => 'loja-completa',
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('active_company.profile_completed', true);
    }

    public function test_owner_updates_non_bigshop_company_platform_from_integrations(): void
    {
        [$user, $merchant, $company] = $this->tenant('custom');

        $this->withHeaders($this->headers($user, $merchant, $company))
            ->patchJson('/api/v1/merchant/company-platform', [
                'platform' => 'shopify',
            ])
            ->assertOk()
            ->assertJsonPath('data.platform', 'shopify');

        $this->assertDatabaseHas('merchant_companies', [
            'id' => $company->id,
            'platform' => 'shopify',
        ]);
    }

    public function test_bigshop_company_platform_is_locked_in_company_portal(): void
    {
        [$user, $merchant, $company] = $this->tenant('bigshop');

        $this->withHeaders($this->headers($user, $merchant, $company))
            ->patchJson('/api/v1/merchant/company-platform', [
                'platform' => 'shopify',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');

        $this->assertDatabaseHas('merchant_companies', [
            'id' => $company->id,
            'platform' => 'bigshop',
        ]);
    }

    public function test_non_bigshop_company_cannot_self_activate_bigshop_platform(): void
    {
        [$user, $merchant, $company] = $this->tenant('custom');

        $this->withHeaders($this->headers($user, $merchant, $company))
            ->patchJson('/api/v1/merchant/company-platform', [
                'platform' => 'bigshop',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('platform');

        $this->assertDatabaseHas('merchant_companies', [
            'id' => $company->id,
            'platform' => 'custom',
        ]);
    }

    private function headers(User $user, Merchant $merchant, MerchantCompany $company): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test', [
                'role:merchant',
                'merchant:'.$merchant->id,
                'company:'.$company->id,
            ])->plainTextToken,
        ];
    }

    private function tenant(string $platform = 'bigshop'): array
    {
        $merchant = Merchant::query()->create([
            'name' => 'Empresa CNPJ 11.222.333/0001-81',
            'slug' => 'empresa-cnpj-11222333000181',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Empresa CNPJ 11.222.333/0001-81',
            'document' => '11222333000181',
            'platform' => $platform,
            'status' => 'active',
        ]);
        $user = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner.profile@example.com',
            'cpf' => '05521345620',
            'role' => 'merchant',
            'password' => Hash::make('password123'),
        ]);

        $user->merchants()->attach($merchant->id, [
            'merchant_company_id' => $company->id,
            'role' => 'owner',
            'is_owner' => true,
            'status' => 'active',
        ]);

        return [$user, $merchant, $company];
    }
}
