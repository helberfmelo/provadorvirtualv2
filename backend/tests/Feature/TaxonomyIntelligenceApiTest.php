<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\MerchantBrand;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\TaxonomyLearningEvent;
use App\Models\TaxonomyMappingSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyIntelligenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_generates_reviewable_suggestions_with_reasons_impact_and_confidence(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $this->createProduct($merchant, $company, 'S138 Camisa IA', 'S138-IA-CAMISA', 'CAMISA', 'S138 Marca Nebulosa');

        $response = $this->withHeaders($headers)
            ->postJson('/api/v1/taxonomy/intelligence/generate', ['type' => 'all'])
            ->assertCreated()
            ->assertJsonPath('summary.review_required', 1);

        $suggestions = collect($response->json('suggestions'));
        $category = $suggestions
            ->where('suggestion_type', 'category')
            ->firstWhere('original_value', 'CAMISA');
        $brand = $suggestions->firstWhere('suggestion_type', 'brand');

        $this->assertNotNull($category);
        $this->assertSame('CAMISA', $category['original_value']);
        $this->assertSame('Camisas', $category['suggested_name']);
        $this->assertSame('high', $category['confidence_level']);
        $this->assertNotEmpty($category['reasons']);
        $this->assertSame(1, $category['impact']['products_count']);
        $this->assertContains('recommendations', $category['impact']['uses']);

        $this->assertNotNull($brand);
        $this->assertSame('S138 Marca Nebulosa', $brand['original_value']);
        $this->assertSame('low', $brand['confidence_level']);
        $this->assertTrue($brand['review_required']);
        $this->assertNotEmpty($brand['reasons']);
        $this->assertSame('unknown', data_get($brand, 'context.signals.size_system'));
    }

    public function test_approved_mapping_updates_products_and_improves_future_imports(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $product = $this->createProduct($merchant, $company, 'S138 Camisa Aprendizado', 'S138-LEARN-A', 'CAMISA', 'S138 Zak Lab');

        $categorySuggestion = collect($this->withHeaders($headers)
            ->postJson('/api/v1/taxonomy/intelligence/generate', ['type' => 'category'])
            ->assertCreated()
            ->json('suggestions'))
            ->where('suggestion_type', 'category')
            ->firstWhere('original_value', 'CAMISA');

        $this->withHeaders($headers)
            ->postJson('/api/v1/taxonomy/suggestions/'.$categorySuggestion['id'].'/approve', [
                'apply_to_products' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.suggested_name', 'Camisas')
            ->assertJsonPath('summary.products_updated', 1);

        $product->refresh();
        $this->assertSame('Camisas', data_get($product->metadata, 'normalized_category.name'));
        $this->assertSame('ai_review', data_get($product->metadata, 'category_mapping.source'));
        $this->assertTrue((bool) data_get($product->metadata, 'category_mapping.reviewed'));
        $this->assertDatabaseHas('taxonomy_learning_events', [
            'event_type' => 'mapping_applied',
            'target_type' => 'category',
            'original_value' => 'CAMISA',
            'normalized_value' => 'Camisas',
        ]);

        $content = "sku,name,category,gender,fit_profile,brand\nS138-FUTURE-A,S138 Import Futuro,CAMISA,unisex,regular,S138 Zak Lab";
        $this->withHeaders($headers)
            ->postJson('/api/v1/imports', [
                'type' => 'products',
                'source_format' => 'csv',
                'filename' => 'sprint-138-products.csv',
                'content' => $content,
            ])
            ->assertCreated();

        $futureProduct = Product::query()->where('sku', 'S138-FUTURE-A')->firstOrFail();
        $this->assertSame('Camisas', data_get($futureProduct->metadata, 'normalized_category.name'));
        $this->assertSame('import', data_get($futureProduct->metadata, 'normalized_category.source'));
    }

    public function test_low_confidence_mapping_requires_confirmation_and_can_be_rejected(): void
    {
        $this->seed();
        $headers = ['Authorization' => 'Bearer '.$this->loginToken()];
        [$merchant, $company] = $this->tenant();
        $product = $this->createProduct($merchant, $company, 'S138 Marca Revisao', 'S138-LOW-BRAND', 'Categoria Livre 138', 'S138 Marca Sem Historico');

        $brandSuggestion = collect($this->withHeaders($headers)
            ->postJson('/api/v1/taxonomy/intelligence/generate', ['type' => 'brand'])
            ->assertCreated()
            ->json('suggestions'))
            ->firstWhere('suggestion_type', 'brand');

        $this->assertSame('low', $brandSuggestion['confidence_level']);

        $this->withHeaders($headers)
            ->postJson('/api/v1/taxonomy/suggestions/'.$brandSuggestion['id'].'/approve', [
                'apply_to_products' => true,
            ])
            ->assertUnprocessable();

        $product->refresh();
        $this->assertNull(data_get($product->metadata, 'normalized_brand.name'));

        $this->withHeaders($headers)
            ->postJson('/api/v1/taxonomy/suggestions/'.$brandSuggestion['id'].'/reject', [
                'reason' => 'Marca local ainda precisa de revisão comercial.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('taxonomy_mapping_suggestions', [
            'id' => $brandSuggestion['id'],
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('taxonomy_learning_events', [
            'event_type' => 'suggestion_rejected',
            'target_type' => 'brand',
            'original_value' => 'S138 Marca Sem Historico',
        ]);
        $this->assertNull(MerchantBrand::query()->where('name', 'S138 Marca Sem Historico')->firstOrFail()->normalized_brand_id);
        $this->assertSame(1, TaxonomyLearningEvent::query()->where('event_type', 'suggestion_rejected')->count());
        $this->assertSame(1, TaxonomyMappingSuggestion::query()->where('status', 'rejected')->count());
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

    private function createProduct(Merchant $merchant, MerchantCompany $company, string $name, string $sku, string $category, string $brand): Product
    {
        return Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => $sku,
            'external_product_id' => $sku,
            'description' => 'Produto de teste da Sprint 138.',
            'category' => $category,
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
