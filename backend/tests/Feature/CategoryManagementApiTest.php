<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantCompany;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_discover_review_and_filter_normalized_categories(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $product = $this->createProduct($merchant, $company, 'S137 Camisa Manga', 'S137-CAMISA-A', 'CAMISA');

        $categoriesResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertOk();

        $categories = collect($categoriesResponse->json('data'));
        $localCategory = $categories->firstWhere('name', 'CAMISA');
        $this->assertNotNull($localCategory);
        $this->assertSame('Camisas', $localCategory['suggestion']['taxonomy_name']);

        $this->withHeaders($headers)
            ->patchJson('/api/v1/categories/'.$localCategory['id'], [
                'taxonomy_category_id' => $localCategory['suggestion']['taxonomy_category_id'],
                'apply_to_products' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.taxonomy_category.name', 'Camisas')
            ->assertJsonPath('summary.updated', 1);

        $product->refresh();
        $this->assertSame('CAMISA', $product->category);
        $this->assertSame('CAMISA', data_get($product->metadata, 'category_original'));
        $this->assertSame('Camisas', data_get($product->metadata, 'normalized_category.name'));
        $this->assertSame('Camisas', data_get($product->metadata, 'rules_context.category.normalized'));
        $this->assertSame('Camisas', data_get($product->metadata, 'ai_context.category.normalized'));

        $this->withHeaders($headers)
            ->getJson('/api/v1/products?search=S137-CAMISA-A&normalized_category=Camisas')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.category', 'CAMISA')
            ->assertJsonPath('data.0.normalized_category.name', 'Camisas');

        $this->assertDatabaseHas('merchant_categories', [
            'id' => $localCategory['id'],
            'taxonomy_category_id' => data_get($product->metadata, 'normalized_category.id'),
        ]);
    }

    public function test_merchant_can_merge_duplicate_local_categories_without_losing_original_product_category(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $targetProduct = $this->createProduct($merchant, $company, 'S137 Vestido Festa', 'S137-VESTIDO-A', 'S137 Vestido Festa');
        $sourceProduct = $this->createProduct($merchant, $company, 'S137 Vestido Festa Oficial', 'S137-VESTIDO-B', 'S137 Vestido Festa Oficial');

        $categoriesResponse = $this->withHeaders($headers)
            ->getJson('/api/v1/categories')
            ->assertOk();
        $this->assertGreaterThanOrEqual(1, $categoriesResponse->json('summary.duplicate_groups'));

        $categories = collect($categoriesResponse->json('data'));
        $target = $categories->firstWhere('name', 'S137 Vestido Festa');
        $source = $categories->firstWhere('name', 'S137 Vestido Festa Oficial');

        $this->withHeaders($headers)
            ->postJson('/api/v1/categories/merge', [
                'target_category_id' => $target['id'],
                'source_category_ids' => [$source['id']],
                'taxonomy_name' => 'Vestidos',
                'category_type' => 'full_body',
                'apply_to_products' => true,
            ])
            ->assertOk()
            ->assertJsonPath('summary.source_categories', 1)
            ->assertJsonPath('summary.updated_products', 2);

        $targetProduct->refresh();
        $sourceProduct->refresh();
        $this->assertSame('S137 Vestido Festa', $targetProduct->category);
        $this->assertSame('S137 Vestido Festa Oficial', $sourceProduct->category);
        $this->assertSame('Vestidos', data_get($targetProduct->metadata, 'normalized_category.name'));
        $this->assertSame('Vestidos', data_get($sourceProduct->metadata, 'normalized_category.name'));
        $this->assertSame('S137 Vestido Festa Oficial', data_get($sourceProduct->metadata, 'category_original'));
        $this->assertSame('inactive', MerchantCategory::query()->findOrFail($source['id'])->status);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'category.merged',
            'auditable_type' => MerchantCategory::class,
            'auditable_id' => $target['id'],
        ]);
    }

    public function test_merchant_can_preview_import_and_export_categories(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $product = $this->createProduct($merchant, $company, 'S137 CSV Produto', 'S137-CSV-A', 'S137 CSV Categoria');
        $content = "name,taxonomy_category,category_type,gender,age_group,status,source,translation_pt_br\nS137 CSV Categoria,S137 CSV Taxonomia,top,,adult,active,import,S137 CSV Taxonomia\n,S137 Sem Nome,top,,adult,active,import,S137 Sem Nome";

        $this->withHeaders($headers)
            ->postJson('/api/v1/categories/import', [
                'content' => $content,
                'commit' => false,
            ])
            ->assertOk()
            ->assertJsonPath('summary.valid', 1)
            ->assertJsonPath('summary.invalid', 1);

        $this->withHeaders($headers)
            ->postJson('/api/v1/categories/import', [
                'content' => $content,
                'commit' => true,
                'apply_to_products' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('summary.imported', 1)
            ->assertJsonPath('summary.invalid', 1)
            ->assertJsonPath('summary.updated_products', 1);

        $product->refresh();
        $this->assertSame('S137 CSV Categoria', $product->category);
        $this->assertSame('S137 CSV Taxonomia', data_get($product->metadata, 'normalized_category.name'));
        $this->assertSame('top', data_get($product->metadata, 'normalized_category.type'));

        $this->withHeaders($headers)
            ->get('/api/v1/categories/export')
            ->assertOk()
            ->assertSee('S137 CSV Categoria')
            ->assertSee('S137 CSV Taxonomia');

        $this->withHeaders($headers)
            ->get('/api/v1/categories/template')
            ->assertOk()
            ->assertSee('taxonomy_category')
            ->assertSee('category_type');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'category.imported',
        ]);
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('event', 'category.imported')->count());
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

    private function createProduct(Merchant $merchant, MerchantCompany $company, string $name, string $sku, string $category): Product
    {
        return Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => $sku,
            'external_product_id' => $sku,
            'description' => 'Produto de teste da Sprint 137.',
            'category' => $category,
            'gender' => 'unisex',
            'fit_profile' => 'regular',
            'status' => 'active',
            'image_url' => null,
            'metadata' => [
                'brand' => 'S137 Brand',
                'source' => 'import',
                'last_imported_at' => now()->toISOString(),
            ],
        ]);
    }
}
