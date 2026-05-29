<?php

namespace Tests\Feature;

use App\Models\IntegrationChangeRequest;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IntegrationChangeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_discounted_bigshop_company_requests_platform_change(): void
    {
        [$user, $merchant, $company] = $this->tenant(bigshopDiscountActive: true);

        $this->withHeaders($this->headers($user, $merchant, $company))
            ->postJson('/api/v1/merchant/integration-change-requests', [
                'to_platform' => 'shopify',
                'accepted_terms' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.from_platform', 'bigshop')
            ->assertJsonPath('data.to_platform', 'shopify')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('integration_change_requests', [
            'merchant_company_id' => $company->id,
            'from_platform' => 'bigshop',
            'to_platform' => 'shopify',
            'status' => IntegrationChangeRequest::STATUS_PENDING,
        ]);
    }

    public function test_change_request_requires_discounted_bigshop_company(): void
    {
        [$user, $merchant, $company] = $this->tenant(platform: 'custom');

        $this->withHeaders($this->headers($user, $merchant, $company))
            ->postJson('/api/v1/merchant/integration-change-requests', [
                'to_platform' => 'shopify',
                'accepted_terms' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to_platform');
    }

    public function test_saas_can_list_and_update_change_requests(): void
    {
        [$user, $merchant, $company] = $this->tenant(bigshopDiscountActive: true);
        $changeRequest = IntegrationChangeRequest::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'user_id' => $user->id,
            'from_platform' => 'bigshop',
            'to_platform' => 'shopify',
            'status' => IntegrationChangeRequest::STATUS_PENDING,
            'terms_version' => 'bigshop-change-2026-05-29',
            'terms_accepted_at' => now(),
            'requested_at' => now(),
        ]);
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin.requests@example.com',
            'cpf' => '12345678901',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $admin->createToken('admin-test', ['role:admin'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/v1/saas/integration-change-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $changeRequest->id);

        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->patchJson('/api/v1/saas/integration-change-requests/'.$changeRequest->id, [
                'status' => IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED,
                'payment_link' => 'https://provadorvirtual.online/checkout?upgrade=zak',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', IntegrationChangeRequest::STATUS_PAYMENT_REQUESTED)
            ->assertJsonPath('data.payment_link', 'https://provadorvirtual.online/checkout?upgrade=zak');
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

    private function tenant(string $platform = 'bigshop', bool $bigshopDiscountActive = false): array
    {
        $merchant = Merchant::query()->create([
            'name' => 'Loja Troca',
            'slug' => 'loja-troca',
            'billing_status' => 'active',
        ]);
        $company = MerchantCompany::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Loja Troca',
            'platform' => $platform,
            'bigshop_discount_active' => $bigshopDiscountActive,
            'status' => 'active',
        ]);
        $user = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner.change@example.com',
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
