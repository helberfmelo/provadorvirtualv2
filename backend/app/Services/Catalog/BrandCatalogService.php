<?php

namespace App\Services\Catalog;

use App\Models\Merchant;
use App\Models\MerchantBrand;
use App\Models\MerchantCompany;
use App\Models\NormalizedBrand;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BrandCatalogService
{
    public function ensureMerchantBrand(
        Merchant $merchant,
        ?MerchantCompany $company,
        string $name,
        array $attributes = []
    ): MerchantBrand {
        $name = $this->cleanName($name);
        abort_if($name === '', 422, 'Informe o nome da marca.');

        $brand = MerchantBrand::query()
            ->where('merchant_id', $merchant->id)
            ->when(
                $company,
                fn (Builder $query) => $query->where('merchant_company_id', $company->id),
                fn (Builder $query) => $query->whereNull('merchant_company_id')
            )
            ->where('name', $name)
            ->first();

        $metadata = array_merge($brand?->metadata ?? [], [
            'fingerprint' => $this->fingerprint($name),
            'last_seen_at' => now()->toISOString(),
        ], $attributes['metadata'] ?? []);

        if (! $brand) {
            return MerchantBrand::query()->create([
                'merchant_id' => $merchant->id,
                'merchant_company_id' => $company?->id,
                'normalized_brand_id' => $attributes['normalized_brand_id'] ?? null,
                'name' => $name,
                'slug' => $this->slug($name),
                'source' => $attributes['source'] ?? 'manual',
                'status' => $attributes['status'] ?? 'active',
                'metadata' => $metadata,
            ]);
        }

        $brand->fill([
            'normalized_brand_id' => $attributes['normalized_brand_id'] ?? $brand->normalized_brand_id,
            'source' => $attributes['source'] ?? $brand->source,
            'status' => $attributes['status'] ?? $brand->status,
            'metadata' => $metadata,
        ])->save();

        return $brand->refresh();
    }

    public function ensureNormalizedBrand(string $name, array $metadata = []): NormalizedBrand
    {
        $name = $this->cleanName($name);
        abort_if($name === '', 422, 'Informe o nome da marca normalizada.');

        $slug = $this->slug($name);
        $brand = NormalizedBrand::withTrashed()->where('slug', $slug)->first();

        if ($brand) {
            if ($brand->trashed()) {
                $brand->restore();
            }

            $brand->fill([
                'name' => $name,
                'status' => 'active',
                'metadata' => array_merge($brand->metadata ?? [], $metadata),
            ])->save();

            return $brand->refresh();
        }

        return NormalizedBrand::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'metadata' => array_merge([
                'source' => 'merchant_review',
            ], $metadata),
        ]);
    }

    public function syncProductBrand(Merchant $merchant, ?MerchantCompany $company, Product $product, mixed $brandName, string $source): ?MerchantBrand
    {
        $brandName = $this->cleanName((string) $brandName);

        if ($brandName === '') {
            return null;
        }

        $merchantBrand = $this->ensureMerchantBrand($merchant, $company, $brandName, [
            'source' => $source,
            'metadata' => [
                'sources' => array_values(array_unique([
                    ...((array) data_get($product->metadata ?? [], 'brand_mapping.sources', [])),
                    $source,
                ])),
            ],
        ])->load('normalizedBrand');

        if ($merchantBrand->normalizedBrand) {
            $this->applyNormalizedToProduct($product, $merchantBrand, $merchantBrand->normalizedBrand, $source);
        }

        return $merchantBrand;
    }

    public function applyToProducts(
        Merchant $merchant,
        ?MerchantCompany $company,
        MerchantBrand $merchantBrand,
        NormalizedBrand $normalizedBrand,
        string $source = 'merchant_review'
    ): array {
        $updatedIds = [];
        $products = $this->productQuery($merchant, $company)
            ->latest('id')
            ->get()
            ->filter(fn (Product $product): bool => $this->sameName(data_get($product->metadata ?? [], 'brand'), $merchantBrand->name));

        foreach ($products as $product) {
            if ($this->applyNormalizedToProduct($product, $merchantBrand, $normalizedBrand, $source)) {
                $updatedIds[] = $product->id;
            }
        }

        return [
            'matched' => $products->count(),
            'updated' => count($updatedIds),
            'product_ids' => $updatedIds,
        ];
    }

    public function applyNormalizedToProduct(
        Product $product,
        MerchantBrand $merchantBrand,
        NormalizedBrand $normalizedBrand,
        string $source = 'merchant_review'
    ): bool {
        $metadata = $product->metadata ?? [];
        $originalBrand = $this->cleanName((string) (data_get($metadata, 'brand') ?: $merchantBrand->name));

        if ($originalBrand !== '' && blank(data_get($metadata, 'brand_original'))) {
            $metadata['brand_original'] = $originalBrand;
        }

        $metadata['normalized_brand_id'] = $normalizedBrand->id;
        $metadata['normalized_brand_name'] = $normalizedBrand->name;
        $metadata['normalized_brand'] = [
            'id' => $normalizedBrand->id,
            'name' => $normalizedBrand->name,
            'slug' => $normalizedBrand->slug,
            'original_name' => $originalBrand ?: $merchantBrand->name,
            'merchant_brand_id' => $merchantBrand->id,
            'source' => $source,
            'applied_at' => now()->toISOString(),
        ];
        $metadata['brand_mapping'] = [
            'local_brand_id' => $merchantBrand->id,
            'local_name' => $merchantBrand->name,
            'normalized_brand_id' => $normalizedBrand->id,
            'normalized_name' => $normalizedBrand->name,
            'source' => $source,
            'reviewed' => in_array($source, ['merchant_review', 'ai_review', 'merge', 'import'], true),
            'updated_at' => now()->toISOString(),
        ];
        $metadata['rules_context'] = is_array($metadata['rules_context'] ?? null) ? $metadata['rules_context'] : [];
        $metadata['ai_context'] = is_array($metadata['ai_context'] ?? null) ? $metadata['ai_context'] : [];
        $metadata['rules_context']['brand'] = [
            'original' => $originalBrand ?: $merchantBrand->name,
            'normalized' => $normalizedBrand->name,
            'normalized_brand_id' => $normalizedBrand->id,
        ];
        $metadata['ai_context']['brand'] = [
            'original' => $originalBrand ?: $merchantBrand->name,
            'normalized' => $normalizedBrand->name,
            'confidence' => $source === 'auto' ? 'medium' : 'reviewed',
        ];
        $metadata = $this->appendProductHistory($metadata, 'brand.normalized', [
            'local_brand_id' => $merchantBrand->id,
            'local_name' => $merchantBrand->name,
            'normalized_brand_id' => $normalizedBrand->id,
            'normalized_name' => $normalizedBrand->name,
            'source' => $source,
        ]);

        if (($product->metadata ?? []) === $metadata) {
            return false;
        }

        $product->forceFill(['metadata' => $metadata])->save();

        return true;
    }

    public function productsByBrand(Merchant $merchant, ?MerchantCompany $company): Collection
    {
        return $this->productQuery($merchant, $company)
            ->latest('id')
            ->get()
            ->groupBy(fn (Product $product): string => $this->cleanName((string) data_get($product->metadata ?? [], 'brand')))
            ->filter(fn (Collection $products, string $brand): bool => $brand !== '');
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
                'desde',
                'oficial',
                'brasil',
                'br',
                'loja',
                'store',
                'marca',
                'moda',
                'fashion',
                'company',
                'co',
                'ltda',
                'sa',
            ], true))
            ->reject(fn (string $token): bool => ctype_digit($token))
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
            'source' => 'brand_catalog',
            'details' => $details,
            'created_at' => now()->toISOString(),
        ]);

        $metadata['history'] = array_slice($history, 0, 25);

        return $metadata;
    }
}
