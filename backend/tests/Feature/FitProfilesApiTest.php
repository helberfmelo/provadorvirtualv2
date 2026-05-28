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

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
