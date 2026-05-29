<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\MerchantBrand;
use App\Models\MerchantCompany;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_discover_review_and_filter_normalized_brands(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $original = $this->createProduct($merchant, $company, 'S136 Camisa Zak Lab', 'S136-LAB-A', 'S136 Zak Lab');
        $duplicated = $this->createProduct($merchant, $company, 'S136 Camisa Zak Desde', 'S136-LAB-B', 'S136 Zak Lab - Desde 1969');

        $brandsResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/brands')
            ->assertOk();
        $this->assertGreaterThanOrEqual(1, $brandsResponse->json('summary.duplicate_groups'));

        $brands = collect($brandsResponse->json('data'));
        $localBrand = $brands->firstWhere('name', 'S136 Zak Lab - Desde 1969');
        $this->assertNotNull($localBrand);
        $this->assertSame('S136 Zak Lab', $localBrand['suggestion']['normalized_name']);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/brands/'.$localBrand['id'], [
                'normalized_name' => 'S136 Zak Lab',
                'apply_to_products' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.normalized_brand.name', 'S136 Zak Lab')
            ->assertJsonPath('summary.updated', 1);

        $duplicated->refresh();
        $this->assertSame('S136 Zak Lab - Desde 1969', data_get($duplicated->metadata, 'brand'));
        $this->assertSame('S136 Zak Lab - Desde 1969', data_get($duplicated->metadata, 'brand_original'));
        $this->assertSame('S136 Zak Lab', data_get($duplicated->metadata, 'normalized_brand.name'));
        $this->assertSame('S136 Zak Lab', data_get($duplicated->metadata, 'rules_context.brand.normalized'));
        $this->assertSame('S136 Zak Lab', data_get($duplicated->metadata, 'ai_context.brand.normalized'));

        $this->withHeaders($headers)
            ->getJson('/api/v1/products?search=S136-LAB-B&normalized_brand=S136%20Zak%20Lab')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $duplicated->id)
            ->assertJsonPath('data.0.brand', 'S136 Zak Lab - Desde 1969')
            ->assertJsonPath('data.0.normalized_brand.name', 'S136 Zak Lab');

        $this->assertDatabaseHas('merchant_brands', [
            'id' => $localBrand['id'],
            'normalized_brand_id' => data_get($duplicated->metadata, 'normalized_brand.id'),
        ]);
        $this->assertDatabaseHas('normalized_brands', [
            'name' => 'S136 Zak Lab',
        ]);
        $this->assertNotNull($original->refresh());
    }

    public function test_merchant_can_merge_duplicate_local_brands_without_losing_original_product_name(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $targetProduct = $this->createProduct($merchant, $company, 'S136 Jaqueta Norte', 'S136-NORTE-A', 'S136 Marca Norte');
        $sourceProduct = $this->createProduct($merchant, $company, 'S136 Jaqueta Norte Oficial', 'S136-NORTE-B', 'S136 Marca Norte Oficial');

        $brands = collect($this->withHeaders($headers)
            ->getJson('/api/v1/brands')
            ->assertOk()
            ->json('data'));
        $target = $brands->firstWhere('name', 'S136 Marca Norte');
        $source = $brands->firstWhere('name', 'S136 Marca Norte Oficial');

        $this->withHeaders($headers)
            ->postJson('/api/v1/brands/merge', [
                'target_brand_id' => $target['id'],
                'source_brand_ids' => [$source['id']],
                'normalized_name' => 'S136 Marca Norte',
                'apply_to_products' => true,
            ])
            ->assertOk()
            ->assertJsonPath('summary.source_brands', 1)
            ->assertJsonPath('summary.updated_products', 2);

        $targetProduct->refresh();
        $sourceProduct->refresh();
        $this->assertSame('S136 Marca Norte', data_get($targetProduct->metadata, 'brand'));
        $this->assertSame('S136 Marca Norte Oficial', data_get($sourceProduct->metadata, 'brand'));
        $this->assertSame('S136 Marca Norte', data_get($targetProduct->metadata, 'normalized_brand.name'));
        $this->assertSame('S136 Marca Norte', data_get($sourceProduct->metadata, 'normalized_brand.name'));
        $this->assertSame('S136 Marca Norte Oficial', data_get($sourceProduct->metadata, 'brand_original'));
        $this->assertSame('inactive', MerchantBrand::query()->findOrFail($source['id'])->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'brand.merged',
            'auditable_type' => MerchantBrand::class,
            'auditable_id' => $target['id'],
        ]);
    }

    public function test_merchant_can_preview_import_and_export_brands(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $product = $this->createProduct($merchant, $company, 'S136 CSV Produto', 'S136-CSV-A', 'S136 CSV Brand');
        $content = "name,normalized_brand,status,source\nS136 CSV Brand,S136 CSV,active,import\n,S136 Sem Nome,active,import";

        $this->withHeaders($headers)
            ->postJson('/api/v1/brands/import', [
                'content' => $content,
                'commit' => false,
            ])
            ->assertOk()
            ->assertJsonPath('summary.valid', 1)
            ->assertJsonPath('summary.invalid', 1);

        $this->withHeaders($headers)
            ->postJson('/api/v1/brands/import', [
                'content' => $content,
                'commit' => true,
                'apply_to_products' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('summary.imported', 1)
            ->assertJsonPath('summary.invalid', 1)
            ->assertJsonPath('summary.updated_products', 1);

        $product->refresh();
        $this->assertSame('S136 CSV Brand', data_get($product->metadata, 'brand'));
        $this->assertSame('S136 CSV', data_get($product->metadata, 'normalized_brand.name'));

        $this->withHeaders($headers)
            ->get('/api/v1/brands/export')
            ->assertOk()
            ->assertSee('S136 CSV Brand')
            ->assertSee('S136 CSV');

        $this->withHeaders($headers)
            ->get('/api/v1/brands/template')
            ->assertOk()
            ->assertSee('normalized_brand');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'brand.imported',
        ]);
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('event', 'brand.imported')->count());
    }

    private function loginToken(): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => 'demo@provadorvirtual.online',
            'password' => 'provador123',
        ])->assertOk()->json('token');
    }

    private function tenant(): array
    {
        $merchant = Merchant::query()->where('slug', 'provador-virtual-demo')->firstOrFail();
        $company = MerchantCompany::query()->where('merchant_id', $merchant->id)->firstOrFail();

        return [$merchant, $company];
    }

    private function createProduct(Merchant $merchant, MerchantCompany $company, string $name, string $sku, string $brand): Product
    {
        return Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => $sku,
            'external_product_id' => $sku,
            'description' => 'Produto de teste da Sprint 136.',
            'category' => 'Camisas',
            'gender' => 'unisex',
            'fit_profile' => 'regular',
            'status' => 'active',
            'image_url' => null,
            'metadata' => [
                'brand' => $brand,
                'source' => 'import',
                'last_imported_at' => now()->toISOString(),
            ],
        ]);
    }
}
