<?php

namespace Tests\Feature;

use App\Models\ImportJob;
use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_preview_and_commit_product_csv(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $csv = implode("\n", [
            'sku,name,category,gender,fit_profile,size_label,variant_sku,price,stock_quantity,measurement_table',
            'LINHO-IMP,Camisa Linho Importada,Camisas,unisex,regular,P,LINHO-IMP-P,199.90,8,Vestidos femininos - modelagem regular',
            'LINHO-IMP,Camisa Linho Importada,Camisas,unisex,regular,M,LINHO-IMP-M,199.90,6,Vestidos femininos - modelagem regular',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/imports/preview', [
                'type' => 'products',
                'source_format' => 'csv',
                'filename' => 'products.csv',
                'content' => $csv,
            ])
            ->assertOk()
            ->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.valid_rows', 2)
            ->assertJsonPath('data.summary.products', 1)
            ->assertJsonPath('data.summary.variants', 2);

        $jobId = $this->withHeaders($headers)
            ->postJson('/api/v1/imports', [
                'type' => 'products',
                'source_format' => 'csv',
                'filename' => 'products.csv',
                'content' => $csv,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.imported_rows', 2)
            ->json('data.id');

        $product = Product::query()->where('sku', 'LINHO-IMP')->with('variants')->firstOrFail();
        $this->assertCount(2, $product->variants);
        $this->assertDatabaseHas('import_jobs', ['id' => $jobId, 'type' => 'products']);
    }

    public function test_merchant_can_commit_measurement_table_csv(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $csv = implode("\n", [
            'table_name,product_type,gender,fit_profile,size_label,bust_min,bust_max,waist_min,waist_max,hip_min,hip_max',
            'Camisas importadas,shirt,unisex,regular,P,88,94,70,76,92,98',
            'Camisas importadas,shirt,unisex,regular,M,94,100,76,82,98,104',
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/imports', [
                'type' => 'measurement_tables',
                'source_format' => 'csv',
                'filename' => 'tables.csv',
                'content' => $csv,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.imported_rows', 2);

        $table = MeasurementTable::query()->where('name', 'Camisas importadas')->with('rows')->firstOrFail();
        $this->assertSame('shirt', $table->product_type);
        $this->assertCount(2, $table->rows);
        $this->assertSame('P', $table->rows->first()->size_label);
    }

    public function test_import_jobs_are_scoped_to_authenticated_merchant(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];

        $otherMerchant = Merchant::query()->create([
            'name' => 'Outra Loja',
            'slug' => 'outra-loja',
            'billing_status' => 'trialing',
        ]);

        ImportJob::query()->create([
            'merchant_id' => $otherMerchant->id,
            'type' => 'products',
            'source_format' => 'csv',
            'status' => 'completed',
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/imports')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
