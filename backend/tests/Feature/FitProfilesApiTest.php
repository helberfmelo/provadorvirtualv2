<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitProfilesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_manage_fit_profiles_and_see_usage(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $profiles = $this->withHeaders($headers)
            ->getJson('/api/v1/fit-profiles')
            ->assertOk()
            ->assertJsonPath('summary.active', 5)
            ->json('data');

        $this->assertContains('regular', collect($profiles)->pluck('code')->all());

        $profileId = $this->withHeaders($headers)
            ->postJson('/api/v1/fit-profiles', [
                'name' => 'Reta confortável',
                'code' => 'reta_confortavel',
                'product_type' => 'pants',
                'gender' => 'female',
                'fit_intensity' => 'relaxed',
                'stretch_level' => 'high',
                'description' => 'Base para calças com folga moderada.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'reta_confortavel')
            ->assertJsonPath('data.fit_intensity', 'relaxed')
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/api/v1/fit-profiles', [
                'name' => 'Reta confortável duplicada',
                'code' => 'reta_confortavel',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $tableId = $this->withHeaders($headers)
            ->getJson('/api/v1/measurement-tables')
            ->assertOk()
            ->json('data.0.id');
        $productId = $this->withHeaders($headers)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->json('data.0.id');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/measurement-tables/{$tableId}", [
                'fit_profile' => 'reta_confortavel',
            ])
            ->assertOk();
        $this->withHeaders($headers)
            ->patchJson("/api/v1/products/{$productId}", [
                'fit_profile' => 'reta_confortavel',
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson("/api/v1/fit-profiles/{$profileId}")
            ->assertOk()
            ->assertJsonPath('data.products_count', 1)
            ->assertJsonPath('data.measurement_tables_count', 1);

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/fit-profiles/{$profileId}")
            ->assertStatus(422)
            ->assertJsonPath('usage.products_count', 1);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/fit-profiles/{$profileId}", [
                'code' => 'reta_confortavel_plus',
            ])
            ->assertOk()
            ->assertJsonPath('data.code', 'reta_confortavel_plus')
            ->assertJsonPath('data.products_count', 1)
            ->assertJsonPath('data.measurement_tables_count', 1);

        $this->withHeaders($headers)
            ->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.fit_profile', 'reta_confortavel_plus');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/fit-profiles/{$profileId}", [
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $temporaryProfileId = $this->withHeaders($headers)
            ->postJson('/api/v1/fit-profiles', [
                'name' => 'Temporária',
                'code' => 'temporaria',
                'status' => 'draft',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/fit-profiles/{$temporaryProfileId}")
            ->assertOk();
    }

    public function test_merchant_can_diagnose_and_apply_modeling_fixes(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $products = $this->withHeaders($headers)
            ->getJson('/api/v1/products?per_page=10')
            ->assertOk()
            ->json('data');
        $missingProductId = $products[0]['id'];
        $unknownProductId = $products[1]['id'];

        $this->withHeaders($headers)
            ->patchJson("/api/v1/products/{$missingProductId}", [
                'fit_profile' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.fit_profile', null);

        $this->withHeaders($headers)
            ->patchJson("/api/v1/products/{$unknownProductId}", [
                'fit_profile' => 'athletic_fit',
            ])
            ->assertOk()
            ->assertJsonPath('data.fit_profile', 'athletic_fit');

        $diagnostics = $this->withHeaders($headers)
            ->getJson('/api/v1/fit-profiles/diagnostics')
            ->assertOk()
            ->assertJsonPath('summary.without_modeling', 1)
            ->assertJsonPath('summary.modeling_not_found', 1)
            ->json();

        $missingGroup = collect($diagnostics['groups'])->firstWhere('code', 'without_modeling');
        $this->assertNotNull($missingGroup);
        $this->assertSame('existing', $missingGroup['suggested_profile']['mode']);
        $this->assertNotEmpty($missingGroup['suggested_profile']['id']);

        $this->withHeaders($headers)
            ->postJson('/api/v1/fit-profiles/diagnostics/apply', [
                'product_ids' => [$missingProductId],
                'profile_id' => $missingGroup['suggested_profile']['id'],
            ])
            ->assertOk()
            ->assertJsonPath('summary.updated', 1);

        $this->withHeaders($headers)
            ->getJson("/api/v1/products/{$missingProductId}")
            ->assertOk()
            ->assertJsonPath('data.fit_profile', $missingGroup['suggested_profile']['code'])
            ->assertJsonFragment(['event' => 'fit_profile.diagnostic_applied']);

        $unknownGroup = collect($diagnostics['groups'])->firstWhere('code', 'modeling_not_found');
        $this->assertNotNull($unknownGroup);
        $this->assertSame('create', $unknownGroup['suggested_profile']['mode']);

        $this->withHeaders($headers)
            ->postJson('/api/v1/fit-profiles/diagnostics/apply', [
                'product_ids' => [$unknownProductId],
                'profile' => $unknownGroup['suggested_profile']['profile'],
            ])
            ->assertOk()
            ->assertJsonPath('summary.created', true)
            ->assertJsonPath('profile.code', 'athletic_fit');

        $this->withHeaders($headers)
            ->getJson("/api/v1/products/{$unknownProductId}")
            ->assertOk()
            ->assertJsonPath('data.fit_profile', 'athletic_fit');

        $this->assertDatabaseHas('fit_profiles', [
            'code' => 'athletic_fit',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'fit_profile.diagnostic_applied',
        ]);
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
