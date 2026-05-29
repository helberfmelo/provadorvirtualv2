<?php

namespace Tests\Feature;

use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_overview_returns_operational_coverage_and_next_actions(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $merchant = Merchant::query()->where('slug', 'provador-virtual-demo')->firstOrFail();
        $table = MeasurementTable::query()->where('merchant_id', $merchant->id)->firstOrFail();
        $companyId = $table->merchant_company_id;

        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $companyId,
            'sku' => 'OVERVIEW-NO-TABLE',
            'slug' => 'overview-no-table',
            'name' => 'Overview sem tabela',
            'category' => 'Camisas',
            'fit_profile' => 'regular',
            'status' => 'active',
        ]);

        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $companyId,
            'measurement_table_id' => $table->id,
            'sku' => 'OVERVIEW-NO-MODELING',
            'slug' => 'overview-no-modeling',
            'name' => 'Overview sem modelagem',
            'category' => 'Camisas',
            'status' => 'active',
        ]);

        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $companyId,
            'measurement_table_id' => $table->id,
            'sku' => 'OVERVIEW-NO-CATEGORY',
            'slug' => 'overview-no-category',
            'name' => 'Overview sem categoria',
            'fit_profile' => 'regular',
            'status' => 'active',
        ]);

        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $companyId,
            'measurement_table_id' => $table->id,
            'sku' => 'OVERVIEW-SYNC-ERROR',
            'slug' => 'overview-sync-error',
            'name' => 'Overview erro sync',
            'category' => 'Camisas',
            'fit_profile' => 'regular',
            'status' => 'active',
            'metadata' => ['sync_error' => 'Modeling not found'],
        ]);

        Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $companyId,
            'measurement_table_id' => $table->id,
            'sku' => 'OVERVIEW-INACTIVE',
            'slug' => 'overview-inactive',
            'name' => 'Overview inativo',
            'category' => 'Camisas',
            'fit_profile' => 'regular',
            'status' => 'inactive',
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/merchant/overview')
            ->assertOk()
            ->assertJsonPath('coverage.total_products', 9)
            ->assertJsonPath('coverage.covered_products', 4)
            ->assertJsonPath('coverage.active_products', 8)
            ->assertJsonPath('coverage.pending_products', 5)
            ->assertJsonPath('coverage.inactive_products', 1)
            ->assertJsonPath('coverage.without_measurement_table', 1)
            ->assertJsonPath('coverage.without_modeling', 1)
            ->assertJsonPath('coverage.without_category', 1)
            ->assertJsonPath('coverage.sync_errors', 1)
            ->assertJsonPath('coverage.installation_not_validated', 0)
            ->assertJsonPath('next_actions.0.key', 'without_table')
            ->assertJsonPath('next_actions.0.to', '/app/produtos?filtro=sem_tabela')
            ->assertJsonPath('coverage_trend.period_days', 7);
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
