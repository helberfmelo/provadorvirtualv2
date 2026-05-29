<?php

namespace App\Services\Catalog;

use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\TaxonomyCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CategoryCatalogService
{
    public function ensureMerchantCategory(
        Merchant $merchant,
        ?MerchantCompany $company,
        string $name,
        array $attributes = []
    ): MerchantCategory {
        $name = $this->cleanName($name);
        abort_if($name === '', 422, 'Informe o nome da categoria.');

        $category = MerchantCategory::query()
            ->where('merchant_id', $merchant->id)
            ->when(
                $company,
                fn (Builder $query) => $query->where('merchant_company_id', $company->id),
                fn (Builder $query) => $query->whereNull('merchant_company_id')
            )
            ->where('name', $name)
            ->first();

        $metadata = array_merge($category?->metadata ?? [], [
            'fingerprint' => $this->fingerprint($name),
            'last_seen_at' => now()->toISOString(),
        ], $attributes['metadata'] ?? []);

        if (! $category) {
            return MerchantCategory::query()->create([
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company?->id,
                'taxonomy_category_id' => $attributes['taxonomy_category_id'] ?? null,
                'name' => $name,
                'slug' => $this->slug($name),
                'source' => $attributes['source'] ?? 'manual',
                'status' => $attributes['status'] ?? 'active',
                'metadata' => $metadata,
            ]);
        }

        $category->fill([
            'taxonomy_category_id' => $attributes['taxonomy_category_id'] ?? $category->taxonomy_category_id,
            'source' => $attributes['source'] ?? $category->source,
            'status' => $attributes['status'] ?? $category->status,
            'metadata' => $metadata,
        ])->save();

        return $category->refresh();
    }

    public function ensureTaxonomyCategory(
        string $name,
        string $categoryType = 'other',
        ?TaxonomyCategory $parent = null,
        array $attributes = []
    ): TaxonomyCategory {
        $name = $this->cleanName($name);
        abort_if($name === '', 422, 'Informe o nome da categoria normalizada.');

        $slug = $this->slug($name);
        $category = TaxonomyCategory::withTrashed()->where('slug', $slug)->first();

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }

            $category->fill([
                'parent_id' => $attributes['parent_id'] ?? $parent?->id ?? $category->parent_id,
                'name' => $name,
                'category_type' => $attributes['category_type'] ?? $categoryType ?: $category->category_type,
                'gender' => $attributes['gender'] ?? $category->gender,
                'age_group' => $attributes['age_group'] ?? $category->age_group,
                'translations' => array_merge($category->translations ?? [], $attributes['translations'] ?? []),
                'status' => 'active',
                'metadata' => array_merge($category->metadata ?? [], $attributes['metadata'] ?? []),
            ])->save();

            return $category->refresh();
        }

        return TaxonomyCategory::query()->create([
            'parent_id' => $attributes['parent_id'] ?? $parent?->id,
            'name' => $name,
            'slug' => $slug,
            'category_type' => $attributes['category_type'] ?? $categoryType ?: 'other',
            'gender' => $attributes['gender'] ?? null,
            'age_group' => $attributes['age_group'] ?? null,
            'translations' => array_merge(['pt_BR' => $name], $attributes['translations'] ?? []),
            'status' => 'active',
            'metadata' => array_merge(['source' => 'merchant_review'], $attributes['metadata'] ?? []),
        ]);
    }

    public function syncProductCategory(Merchant $merchant, ?MerchantCompany $company, Product $product, mixed $categoryName, string $source): ?MerchantCategory
    {
        $categoryName = $this->cleanName((string) $categoryName);

        if ($categoryName === '') {
            return null;
        }

        $merchantCategory = $this->ensureMerchantCategory($merchant, $company, $categoryName, [
            'source' => $source,
            'metadata' => [
                'sources' => [$source],
            ],
        ])->load('taxonomyCategory');

        if ($merchantCategory->taxonomyCategory) {
            $this->applyTaxonomyToProduct($product, $merchantCategory, $merchantCategory->taxonomyCategory, $source);
        }

        return $merchantCategory;
    }

    public function applyToProducts(
        Merchant $merchant,
        ?MerchantCompany $company,
        MerchantCategory $merchantCategory,
        TaxonomyCategory $taxonomyCategory,
        string $source = 'merchant_review'
    ): array {
        $updatedIds = [];
        $products = $this->productQuery($merchant, $company)
            ->latest('id')
            ->get()
            ->filter(fn (Product $product): bool => $this->sameName($product->category, $merchantCategory->name));

        foreach ($products as $product) {
            if ($this->applyTaxonomyToProduct($product, $merchantCategory, $taxonomyCategory, $source)) {
                $updatedIds[] = $product->id;
            }
        }

        return [
            'matched' => $products->count(),
            'updated' => count($updatedIds),
            'product_ids' => $updatedIds,
        ];
    }

    public function applyTaxonomyToProduct(
        Product $product,
        MerchantCategory $merchantCategory,
        TaxonomyCategory $taxonomyCategory,
        string $source = 'merchant_review'
    ): bool {
        $taxonomyCategory->loadMissing('parent');
        $metadata = $product->metadata ?? [];
        $originalCategory = $this->cleanName((string) ($product->category ?: $merchantCategory->name));

        if ($originalCategory !== '' && blank(data_get($metadata, 'category_original'))) {
            $metadata['category_original'] = $originalCategory;
        }

        $metadata['normalized_category_id'] = $taxonomyCategory->id;
        $metadata['normalized_category_name'] = $taxonomyCategory->name;
        $metadata['normalized_category'] = [
            'id' => $taxonomyCategory->id,
            'name' => $taxonomyCategory->name,
            'slug' => $taxonomyCategory->slug,
            'type' => $taxonomyCategory->category_type,
            'parent_id' => $taxonomyCategory->parent_id,
            'parent_name' => $taxonomyCategory->parent?->name,
            'gender' => $taxonomyCategory->gender,
            'age_group' => $taxonomyCategory->age_group,
            'translations' => $taxonomyCategory->translations ?? [],
            'original_name' => $originalCategory ?: $merchantCategory->name,
            'merchant_category_id' => $merchantCategory->id,
            'source' => $source,
            'applied_at' => now()->toISOString(),
        ];
        $metadata['category_mapping'] = [
            'local_category_id' => $merchantCategory->id,
            'local_name' => $merchantCategory->name,
            'taxonomy_category_id' => $taxonomyCategory->id,
            'taxonomy_name' => $taxonomyCategory->name,
            'category_type' => $taxonomyCategory->category_type,
            'source' => $source,
            'reviewed' => in_array($source, ['merchant_review', 'merge', 'import'], true),
            'updated_at' => now()->toISOString(),
        ];
        $metadata['rules_context'] = is_array($metadata['rules_context'] ?? null) ? $metadata['rules_context'] : [];
        $metadata['ai_context'] = is_array($metadata['ai_context'] ?? null) ? $metadata['ai_context'] : [];
        $metadata['rules_context']['category'] = [
            'original' => $originalCategory ?: $merchantCategory->name,
            'normalized' => $taxonomyCategory->name,
            'taxonomy_category_id' => $taxonomyCategory->id,
            'category_type' => $taxonomyCategory->category_type,
        ];
        $metadata['ai_context']['category'] = [
            'original' => $originalCategory ?: $merchantCategory->name,
            'normalized' => $taxonomyCategory->name,
            'category_type' => $taxonomyCategory->category_type,
            'confidence' => $source === 'auto' ? 'medium' : 'reviewed',
        ];
        $metadata = $this->appendProductHistory($metadata, 'category.normalized', [
            'local_category_id' => $merchantCategory->id,
            'local_name' => $merchantCategory->name,
            'taxonomy_category_id' => $taxonomyCategory->id,
            'taxonomy_name' => $taxonomyCategory->name,
            'category_type' => $taxonomyCategory->category_type,
            'source' => $source,
        ]);

        if (($product->metadata ?? []) === $metadata) {
            return false;
        }

        $product->forceFill(['metadata' => $metadata])->save();

        return true;
    }

    public function productsByCategory(Merchant $merchant, ?MerchantCompany $company): Collection
    {
        return $this->productQuery($merchant, $company)
            ->latest('id')
            ->get()
            ->groupBy(fn (Product $product): string => $this->cleanName((string) $product->category))
            ->filter(fn (Collection $products, string $category): bool => $category !== '');
    }

    public function productQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return Product::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            });
    }

    public function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?: '');
    }

    public function slug(string $name): string
    {
        return Str::slug($name) ?: substr(sha1($name), 0, 12);
    }

    public function fingerprint(string $name): string
    {
        $normalized = Str::ascii(Str::lower($name));
        $tokens = collect(preg_split('/[^a-z0-9]+/', $normalized) ?: [])
            ->map(fn (string $token): string => trim($token))
            ->filter()
            ->reject(fn (string $token): bool => in_array($token, [
                'categoria',
                'category',
                'produto',
                'product',
                'moda',
                'fashion',
                'roupa',
                'loja',
                'store',
                'oficial',
                'desde',
                'brasil',
                'br',
            ], true))
            ->reject(fn (string $token): bool => ctype_digit($token))
            ->map(fn (string $token): string => strlen($token) > 3 && str_ends_with($token, 's') ? substr($token, 0, -1) : $token)
            ->values();

        if ($tokens->isEmpty()) {
            return $this->slug($name);
        }

        return $tokens->join(' ');
    }

    private function sameName(mixed $left, mixed $right): bool
    {
        return $this->cleanName((string) $left) === $this->cleanName((string) $right);
    }

    private function appendProductHistory(array $metadata, string $event, array $details): array
    {
        $history = is_array($metadata['history'] ?? null) ? $metadata['history'] : [];
        array_unshift($history, [
            'event' => $event,
            'source' => 'category_catalog',
            'details' => $details,
            'created_at' => now()->toISOString(),
        ]);

        $metadata['history'] = array_slice($history, 0, 25);

        return $metadata;
    }
}
