<?php

namespace Tests\Feature;

use App\Models\ImportJob;
use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantBrand;
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

    public function test_merchant_can_preview_commit_and_rollback_sizebay_migration_package(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $payload = [
            'sections' => [
                'measurement_tables' => [
                    [
                        'table_name' => 'Vestidos Zak regular',
                        'product_type' => 'dress',
                        'gender' => 'female',
                        'fit_profile' => 'Regular Zak',
                        'size_system' => 'br_alpha',
                        'size_label' => 'P',
                        'bust_min' => 84,
                        'bust_max' => 90,
                        'waist_min' => 68,
                        'waist_max' => 74,
                        'hip_min' => 92,
                        'hip_max' => 98,
                    ],
                    [
                        'table_name' => 'Vestidos Zak regular',
                        'product_type' => 'dress',
                        'gender' => 'female',
                        'fit_profile' => 'Regular Zak',
                        'size_system' => 'br_alpha',
                        'size_label' => 'M',
                        'bust_min' => 90,
                        'bust_max' => 96,
                        'waist_min' => 74,
                        'waist_max' => 80,
                        'hip_min' => 98,
                        'hip_max' => 104,
                    ],
                ],
                'fit_profiles' => [
                    [
                        'name' => 'Regular Zak',
                        'code' => 'regular-zak',
                        'product_type' => 'dress',
                        'gender' => 'female',
                        'fit_intensity' => 'regular',
                        'stretch_level' => 'medium',
                        'status' => 'active',
                    ],
                ],
                'brands' => [
                    [
                        'name' => 'Zak',
                        'normalized_name' => 'Zak',
                    ],
                ],
                'categories' => [
                    [
                        'name' => 'Vestidos',
                        'taxonomy_name' => 'Vestidos',
                        'category_type' => 'dress',
                    ],
                ],
                'products' => [
                    [
                        'external_product_id' => 'zak-vestido-midi',
                        'sku' => 'ZAK-MIDI',
                        'name' => 'Vestido Midi Zak',
                        'category' => 'Vestidos',
                        'brand' => 'Zak',
                        'fit_profile' => 'Regular Zak',
                        'measurement_table' => 'Vestidos Zak regular',
                        'size_label' => 'P',
                        'variant_sku' => 'ZAK-MIDI-P',
                        'price' => '249.90',
                        'stock_quantity' => '4',
                    ],
                    [
                        'external_product_id' => 'zak-vestido-midi',
                        'sku' => 'ZAK-MIDI',
                        'name' => 'Vestido Midi Zak',
                        'category' => 'Vestidos',
                        'brand' => 'Zak',
                        'fit_profile' => 'Regular Zak',
                        'measurement_table' => 'Vestidos Zak regular',
                        'size_label' => 'M',
                        'variant_sku' => 'ZAK-MIDI-M',
                        'price' => '249.90',
                        'stock_quantity' => '6',
                    ],
                ],
                'import_rules' => [
                    [
                        'field' => 'gender',
                        'match_type' => 'equals',
                        'match_value' => 'feminino',
                        'target_value' => 'female',
                    ],
                ],
                'reports' => [
                    [
                        'period' => '2026-05',
                        'dimension' => 'Vestidos',
                        'metric' => 'uso_widget',
                        'device' => 'mobile',
                        'value' => '182',
                    ],
                ],
            ],
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/imports/preview', [
                'type' => 'sizebay_migration',
                'source_format' => 'json',
                'filename' => 'sizebay-zak-migration.json',
                'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                'compare_with_bigshop' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'sizebay_migration')
            ->assertJsonPath('data.total_rows', 9)
            ->assertJsonPath('data.summary.measurement_tables', 2)
            ->assertJsonPath('data.summary.products', 2)
            ->assertJsonPath('data.coverage.products_in_package', 1);

        $jobId = $this->withHeaders($headers)
            ->postJson('/api/v1/imports', [
                'type' => 'sizebay_migration',
                'source_format' => 'json',
                'filename' => 'sizebay-zak-migration.json',
                'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                'compare_with_bigshop' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed_with_warnings')
            ->assertJsonPath('data.imported_rows', 6)
            ->assertJsonPath('data.metadata.batch_id', fn (mixed $value) => is_string($value) && $value !== '')
            ->json('data.id');

        $product = Product::query()->where('sku', 'ZAK-MIDI')->with('variants')->firstOrFail();
        $table = MeasurementTable::query()->where('name', 'Vestidos Zak regular')->with('rows')->firstOrFail();

        $this->assertSame('Vestido Midi Zak', $product->name);
        $this->assertCount(2, $product->variants);
        $this->assertCount(2, $table->rows);
        $this->assertTrue(MerchantBrand::query()->where('name', 'Zak')->exists());

        $this->withHeaders($headers)
            ->postJson("/api/v1/imports/{$jobId}/rollback")
            ->assertOk()
            ->assertJsonPath('data.status', 'rolled_back');

        $this->assertTrue(Product::query()->where('sku', 'ZAK-MIDI')->doesntExist());
        $this->assertTrue(MeasurementTable::query()->where('name', 'Vestidos Zak regular')->doesntExist());
        $this->assertTrue(MerchantBrand::query()->where('name', 'Zak')->doesntExist());
        $this->assertSame('rolled_back', ImportJob::query()->findOrFail($jobId)->status);
    }

    public function test_sizebay_migration_blocks_identifiable_report_rows(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        $payload = [
            'sections' => [
                'reports' => [
                    [
                        'period' => '2026-05',
                        'dimension' => 'Vestidos',
                        'metric' => 'uso_widget',
                        'device' => 'mobile',
                        'value' => '182',
                        'email' => 'cliente@example.com',
                    ],
                ],
            ],
        ];

        $this->withHeaders($headers)
            ->postJson('/api/v1/imports/preview', [
                'type' => 'sizebay_migration',
                'source_format' => 'json',
                'filename' => 'sizebay-report.json',
                'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                'compare_with_bigshop' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.failed_rows', 1)
            ->assertJsonPath('data.review_queue.0.section', 'reports')
            ->assertJsonPath('data.review_queue.0.severity', 'conflict')
            ->assertJsonPath('data.rows.0.errors.0', 'O arquivo traz dado identificável ou segredo bloqueado para migração.');
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }
}
