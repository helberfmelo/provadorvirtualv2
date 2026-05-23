<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementTablesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_manage_measurement_tables_with_rows(): void
    {
        $this->seed();
        $token = $this->loginToken();
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->getJson('/api/v1/measurement-templates')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'female_dress_regular');

        $tableId = $this->withHeaders($headers)
            ->postJson('/api/v1/measurement-tables', [
                'name' => 'Calca alfaiataria regular',
                'product_type' => 'pants',
                'gender' => 'female',
                'fit_profile' => 'regular',
                'source' => 'manual',
                'rows' => [
                    [
                        'size_label' => 'P',
                        'waist_min' => 66,
                        'waist_max' => 72,
                        'hip_min' => 92,
                        'hip_max' => 98,
                    ],
                    [
                        'size_label' => 'M',
                        'waist_min' => 72,
                        'waist_max' => 78,
                        'hip_min' => 98,
                        'hip_max' => 104,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Calca alfaiataria regular')
            ->assertJsonCount(2, 'data.rows')
            ->json('data.id');

        $this->withHeaders($headers)
            ->patchJson("/api/v1/measurement-tables/{$tableId}", [
                'name' => 'Calca alfaiataria revisada',
                'rows' => [
                    [
                        'size_label' => 'P',
                        'waist_min' => 67,
                        'waist_max' => 73,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Calca alfaiataria revisada')
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.waist_min', 67);

        $this->withHeaders($headers)
            ->getJson('/api/v1/measurement-tables')
            ->assertOk()
            ->assertJsonPath('summary.total', 5);

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/measurement-tables/{$tableId}")
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
