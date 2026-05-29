<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantBrandResource;
use App\Http\Resources\NormalizedBrandResource;
use App\Models\Merchant;
use App\Models\MerchantBrand;
use App\Models\MerchantCompany;
use App\Models\NormalizedBrand;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\BrandCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BrandController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly BrandCatalogService $catalog) {}

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $products = $this->discoverBrandsFromProducts($merchant, $company);
        $stats = $this->brandStats($products);
        $normalizedOptions = NormalizedBrand::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(500)
            ->get();
        $merchantBrands = $this->brandQuery($merchant, $company)
            ->with('normalizedBrand')
            ->orderBy('name')
            ->get();

        $merchantBrands->each(function (MerchantBrand $brand) use ($stats, $normalizedOptions, $merchantBrands): void {
            $fingerprint = data_get($brand->metadata ?? [], 'fingerprint') ?: $this->catalog->fingerprint($brand->name);
            $brand->product_count = (int) ($stats['product_counts'][$brand->name] ?? 0);
            $brand->normalized_product_count = $brand->normalized_brand_id
                ? (int) ($stats['normalized_counts'][$brand->normalized_brand_id] ?? 0)
                : 0;
            $brand->aliases = $stats['aliases'][$fingerprint] ?? [];
            $brand->suggestion = $this->suggestionFor($brand, $normalizedOptions, $merchantBrands);
        });

        return MerchantBrandResource::collection($merchantBrands)->additional([
            'summary' => [
                'local_brands' => $merchantBrands->count(),
                'mapped_brands' => $merchantBrands->whereNotNull('normalized_brand_id')->count(),
                'pending_brands' => $merchantBrands->whereNull('normalized_brand_id')->count(),
                'normalized_brands' => $normalizedOptions->count(),
                'products_with_brand' => $stats['products_with_brand'],
                'products_with_normalized_brand' => $stats['products_with_normalized_brand'],
                'duplicate_groups' => count($stats['duplicate_groups']),
            ],
            'normalized_brands' => NormalizedBrandResource::collection($normalizedOptions)->resolve($request),
            'duplicate_groups' => $stats['duplicate_groups'],
        ]);
    }

    public function store(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'normalized_brand_id' => ['nullable', 'integer', 'exists:normalized_brands,id'],
            'normalized_name' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,inactive,draft'],
            'apply_to_products' => ['nullable', 'boolean'],
        ]);
        $normalizedBrand = $this->resolveNormalizedBrand($data);
        $brand = $this->catalog->ensureMerchantBrand($merchant, $company, $data['name'], [
            'normalized_brand_id' => $normalizedBrand?->id,
            'source' => $data['source'] ?? 'manual',
            'status' => $data['status'] ?? 'active',
            'metadata' => [
                'reviewed_at' => $normalizedBrand ? now()->toISOString() : null,
            ],
        ])->load('normalizedBrand');
        $summary = null;

        if ($normalizedBrand && $request->boolean('apply_to_products')) {
            $summary = $this->catalog->applyToProducts($merchant, $company, $brand, $normalizedBrand);
            $this->audit($request, $merchant, 'brand.normalization_applied', $brand, $summary);
        }

        return (new MerchantBrandResource($brand))
            ->additional(['summary' => $summary])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, MerchantBrand $brand)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedBrand($merchant, $company, $brand);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'normalized_brand_id' => ['nullable', 'integer', 'exists:normalized_brands,id'],
            'normalized_name' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,inactive,draft'],
            'apply_to_products' => ['nullable', 'boolean'],
        ]);
        $normalizedBrand = $this->resolveNormalizedBrand($data, $brand->normalizedBrand);
        $name = $data['name'] ?? $brand->name;
        $metadata = array_merge($brand->metadata ?? [], [
            'fingerprint' => $this->catalog->fingerprint($name),
            'reviewed_at' => $normalizedBrand ? now()->toISOString() : data_get($brand->metadata ?? [], 'reviewed_at'),
        ]);

        $brand->fill([
            'name' => $name,
            'slug' => $this->catalog->slug($name),
            'normalized_brand_id' => $normalizedBrand?->id,
            'source' => $data['source'] ?? $brand->source,
            'status' => $data['status'] ?? $brand->status,
            'metadata' => $metadata,
        ])->save();
        $summary = null;

        if ($normalizedBrand && $request->boolean('apply_to_products')) {
            $summary = $this->catalog->applyToProducts($merchant, $company, $brand->fresh(), $normalizedBrand);
            $this->audit($request, $merchant, 'brand.normalization_applied', $brand, $summary);
        }

        return (new MerchantBrandResource($brand->fresh('normalizedBrand')))
            ->additional(['summary' => $summary]);
    }

    public function destroy(Request $request, MerchantBrand $brand)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedBrand($merchant, $company, $brand);
        $products = $this->catalog->productsByBrand($merchant, $company)->get($brand->name, collect())->count();

        if ($products > 0) {
            $brand->update([
                'status' => 'inactive',
                'metadata' => array_merge($brand->metadata ?? [], [
                    'inactivated_at' => now()->toISOString(),
                    'inactivated_reason' => 'Marca possui produtos vinculados.',
                ]),
            ]);
        } else {
            $brand->delete();
        }

        $this->audit($request, $merchant, 'brand.removed', $brand, ['products' => $products]);

        return response()->json([
            'message' => $products > 0 ? 'Marca inativada porque possui produtos vinculados.' : 'Marca removida.',
        ]);
    }

    public function export(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->discoverBrandsFromProducts($merchant, $company);
        $brands = $this->brandQuery($merchant, $company)
            ->with('normalizedBrand')
            ->orderBy('name')
            ->get();
        $csv = $this->csv([
            ['name', 'normalized_brand', 'status', 'source'],
            ...$brands->map(fn (MerchantBrand $brand): array => [
                $brand->name,
                $brand->normalizedBrand?->name,
                $brand->status,
                $brand->source,
            ])->all(),
        ]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="provador-virtual-marcas.csv"',
        ]);
    }

    public function template()
    {
        return response($this->csv([
            ['name', 'normalized_brand', 'status', 'source'],
            ['Zak - Desde 1969', 'Zak', 'active', 'import'],
        ]), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-marcas-provador-virtual.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'content' => ['required', 'string'],
            'commit' => ['nullable', 'boolean'],
            'apply_to_products' => ['nullable', 'boolean'],
        ]);
        $rows = $this->parseCsv($data['content']);
        $commit = $request->boolean('commit', true);
        $applyToProducts = $request->boolean('apply_to_products', true);
        $preview = collect($rows)->map(function (array $row, int $index): array {
            $name = $this->value($row, ['name', 'marca', 'brand']);
            $status = $this->value($row, ['status']) ?: 'active';

            return [
                'line' => $index + 2,
                'valid' => $name !== '',
                'errors' => $name === '' ? ['Informe o nome da marca.'] : [],
                'name' => $name,
                'normalized_brand' => $this->value($row, ['normalized_brand', 'marca_normalizada', 'associated_brand']),
                'status' => in_array($status, ['active', 'inactive', 'draft'], true) ? $status : 'active',
                'source' => $this->value($row, ['source', 'origem']) ?: 'import',
            ];
        })->values();

        if (! $commit) {
            return response()->json([
                'summary' => [
                    'rows' => $preview->count(),
                    'valid' => $preview->where('valid', true)->count(),
                    'invalid' => $preview->where('valid', false)->count(),
                ],
                'rows' => $preview,
            ]);
        }

        $summary = DB::transaction(function () use ($preview, $merchant, $company, $applyToProducts): array {
            $imported = 0;
            $updatedProducts = 0;

            foreach ($preview->where('valid', true) as $row) {
                $normalizedBrand = $row['normalized_brand']
                    ? $this->catalog->ensureNormalizedBrand($row['normalized_brand'], ['source' => 'brand_import'])
                    : null;
                $brand = $this->catalog->ensureMerchantBrand($merchant, $company, $row['name'], [
                    'normalized_brand_id' => $normalizedBrand?->id,
                    'source' => $row['source'],
                    'status' => $row['status'],
                    'metadata' => ['imported_at' => now()->toISOString()],
                ]);

                if ($normalizedBrand && $applyToProducts) {
                    $updatedProducts += $this->catalog
                        ->applyToProducts($merchant, $company, $brand, $normalizedBrand, 'import')['updated'];
                }

                $imported++;
            }

            return [
                'rows' => $preview->count(),
                'imported' => $imported,
                'invalid' => $preview->where('valid', false)->count(),
                'updated_products' => $updatedProducts,
            ];
        });

        $this->audit($request, $merchant, 'brand.imported', null, $summary);

        return response()->json([
            'summary' => $summary,
            'rows' => $preview,
        ], 201);
    }

    public function merge(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'target_brand_id' => ['required', 'integer', 'exists:merchant_brands,id'],
            'source_brand_ids' => ['required', 'array', 'min:1'],
            'source_brand_ids.*' => ['integer', 'exists:merchant_brands,id'],
            'normalized_brand_id' => ['nullable', 'integer', 'exists:normalized_brands,id'],
            'normalized_name' => ['nullable', 'string', 'max:160'],
            'apply_to_products' => ['nullable', 'boolean'],
        ]);
        $target = $this->scopedBrand($merchant, $company, MerchantBrand::query()->findOrFail($data['target_brand_id']));
        $sources = MerchantBrand::query()
            ->whereIn('id', $data['source_brand_ids'])
            ->get()
            ->map(fn (MerchantBrand $source): MerchantBrand => $this->scopedBrand($merchant, $company, $source));
        $normalizedBrand = $this->resolveNormalizedBrand($data, $target->normalizedBrand)
            ?: $this->catalog->ensureNormalizedBrand($target->name, ['source' => 'brand_merge']);
        $applyToProducts = $request->boolean('apply_to_products', true);

        $summary = DB::transaction(function () use ($merchant, $company, $target, $sources, $normalizedBrand, $applyToProducts): array {
            $updatedProducts = 0;
            $target->update([
                'normalized_brand_id' => $normalizedBrand->id,
                'metadata' => array_merge($target->metadata ?? [], [
                    'reviewed_at' => now()->toISOString(),
                    'merge_target' => true,
                ]),
            ]);

            if ($applyToProducts) {
                $updatedProducts += $this->catalog
                    ->applyToProducts($merchant, $company, $target->fresh(), $normalizedBrand, 'merge')['updated'];
            }

            foreach ($sources as $source) {
                if ($applyToProducts) {
                    $updatedProducts += $this->catalog
                        ->applyToProducts($merchant, $company, $source, $normalizedBrand, 'merge')['updated'];
                }

                $source->update([
                    'normalized_brand_id' => $normalizedBrand->id,
                    'status' => 'inactive',
                    'metadata' => array_merge($source->metadata ?? [], [
                        'merged_into_brand_id' => $target->id,
                        'merged_into_name' => $target->name,
                        'merged_at' => now()->toISOString(),
                    ]),
                ]);
            }

            return [
                'target_brand_id' => $target->id,
                'source_brands' => $sources->count(),
                'normalized_brand_id' => $normalizedBrand->id,
                'updated_products' => $updatedProducts,
            ];
        });

        $this->audit($request, $merchant, 'brand.merged', $target, $summary);

        return response()->json([
            'summary' => $summary,
            'data' => (new MerchantBrandResource($target->fresh('normalizedBrand')))->resolve($request),
        ]);
    }

    private function discoverBrandsFromProducts(Merchant $merchant, ?MerchantCompany $company): Collection
    {
        $groups = $this->catalog->productsByBrand($merchant, $company);

        foreach ($groups as $brandName => $products) {
            $normalizedId = $products
                ->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_brand.id') ?: data_get($product->metadata ?? [], 'normalized_brand_id'))
                ->filter()
                ->first();
            $this->catalog->ensureMerchantBrand($merchant, $company, $brandName, [
                'normalized_brand_id' => $normalizedId ? (int) $normalizedId : null,
                'source' => $this->sourceForProducts($products),
                'metadata' => [
                    'product_count_detected' => $products->count(),
                    'sources' => $products->map(fn (Product $product) => $this->sourceForProduct($product))->unique()->values()->all(),
                ],
            ]);
        }

        return $groups->flatten(1);
    }

    private function brandStats(Collection $products): array
    {
        $productsWithBrand = $products
            ->filter(fn (Product $product): bool => filled(data_get($product->metadata ?? [], 'brand')));
        $productCounts = $productsWithBrand
            ->countBy(fn (Product $product): string => $this->catalog->cleanName((string) data_get($product->metadata ?? [], 'brand')))
            ->all();
        $normalizedCounts = $products
            ->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_brand.id') ?: data_get($product->metadata ?? [], 'normalized_brand_id'))
            ->filter()
            ->countBy()
            ->all();
        $aliases = $productsWithBrand
            ->map(fn (Product $product): string => $this->catalog->cleanName((string) data_get($product->metadata ?? [], 'brand')))
            ->unique()
            ->groupBy(fn (string $brand): string => $this->catalog->fingerprint($brand))
            ->map(fn (Collection $brands): array => $brands->values()->all())
            ->all();
        $duplicateGroups = collect($aliases)
            ->filter(fn (array $brands): bool => count($brands) > 1)
            ->map(fn (array $brands, string $fingerprint): array => [
                'fingerprint' => $fingerprint,
                'suggested_name' => $this->displayName($brands[0], $fingerprint),
                'brands' => $brands,
                'products_count' => collect($brands)->sum(fn (string $brand): int => (int) ($productCounts[$brand] ?? 0)),
            ])
            ->values()
            ->all();

        return [
            'product_counts' => $productCounts,
            'normalized_counts' => $normalizedCounts,
            'aliases' => $aliases,
            'duplicate_groups' => $duplicateGroups,
            'products_with_brand' => $productsWithBrand->count(),
            'products_with_normalized_brand' => $products
                ->filter(fn (Product $product): bool => filled(data_get($product->metadata ?? [], 'normalized_brand.name')) || filled(data_get($product->metadata ?? [], 'normalized_brand_name')))
                ->count(),
        ];
    }

    private function suggestionFor(MerchantBrand $brand, Collection $normalizedOptions, Collection $merchantBrands): array
    {
        $fingerprint = data_get($brand->metadata ?? [], 'fingerprint') ?: $this->catalog->fingerprint($brand->name);
        $slug = $this->catalog->slug($this->displayName($brand->name, $fingerprint));
        $exactNormalized = $normalizedOptions->first(fn (NormalizedBrand $normalized): bool => $normalized->slug === $brand->slug || $normalized->slug === $slug);
        $mappedSibling = $merchantBrands
            ->first(fn (MerchantBrand $sibling): bool => (int) $sibling->id !== (int) $brand->id
                && filled($sibling->normalized_brand_id)
                && (data_get($sibling->metadata ?? [], 'fingerprint') ?: $this->catalog->fingerprint($sibling->name)) === $fingerprint);

        if ($brand->normalizedBrand) {
            return [
                'mode' => 'mapped',
                'normalized_brand_id' => $brand->normalizedBrand->id,
                'normalized_name' => $brand->normalizedBrand->name,
                'confidence' => 'high',
                'reasons' => ['Mapeamento revisado para regras, filtros e relatórios.'],
            ];
        }

        if ($exactNormalized) {
            return [
                'mode' => 'existing',
                'normalized_brand_id' => $exactNormalized->id,
                'normalized_name' => $exactNormalized->name,
                'confidence' => 'high',
                'reasons' => ['Nome igual ou equivalente a uma marca normalizada existente.'],
            ];
        }

        if ($mappedSibling?->normalizedBrand) {
            return [
                'mode' => 'existing',
                'normalized_brand_id' => $mappedSibling->normalizedBrand->id,
                'normalized_name' => $mappedSibling->normalizedBrand->name,
                'confidence' => 'high',
                'reasons' => ['Outra variação local já foi revisada para esta marca.'],
            ];
        }

        return [
            'mode' => 'create',
            'normalized_brand_id' => null,
            'normalized_name' => $this->displayName($brand->name, $fingerprint),
            'confidence' => count($brand->aliases ?? []) > 1 ? 'medium' : 'low',
            'reasons' => [
                'Sugestão criada a partir de nome similar, domínio/feed e histórico local.',
                'Revise antes de aplicar aos produtos.',
            ],
        ];
    }

    private function resolveNormalizedBrand(array $data, ?NormalizedBrand $fallback = null): ?NormalizedBrand
    {
        if (! empty($data['normalized_brand_id'])) {
            return NormalizedBrand::query()->findOrFail((int) $data['normalized_brand_id']);
        }

        if (! empty($data['normalized_name'])) {
            return $this->catalog->ensureNormalizedBrand($data['normalized_name']);
        }

        return $fallback;
    }

    private function scopedBrand(Merchant $merchant, ?MerchantCompany $company, MerchantBrand $brand): MerchantBrand
    {
        if ((int) $brand->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Marca não encontrada.');
        }

        if ($company && $brand->merchant_company_id && (int) $brand->merchant_company_id !== (int) $company->id) {
            throw new NotFoundHttpException('Marca não encontrada.');
        }

        return $brand;
    }

    private function brandQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return MerchantBrand::query()
            ->where('merchant_id', $merchant->id)
            ->when($company, function (Builder $query) use ($company): void {
                $query->where(function (Builder $innerQuery) use ($company): void {
                    $innerQuery->where('merchant_company_id', $company->id)
                        ->orWhereNull('merchant_company_id');
                });
            });
    }

    private function sourceForProducts(Collection $products): string
    {
        $sources = $products->map(fn (Product $product): string => $this->sourceForProduct($product))->unique()->values();

        if ($sources->contains('bigshop')) {
            return 'bigshop';
        }

        if ($sources->contains('import')) {
            return 'import';
        }

        return $sources->first() ?: 'manual';
    }

    private function sourceForProduct(Product $product): string
    {
        $metadata = $product->metadata ?? [];
        $source = data_get($metadata, 'source') ?: data_get($metadata, 'data_source');

        if ($source) {
            return (string) $source;
        }

        if (filled(data_get($metadata, 'bigshop_last_sync_at'))) {
            return 'bigshop';
        }

        if (filled(data_get($metadata, 'last_imported_at'))) {
            return 'import';
        }

        return 'manual';
    }

    private function displayName(string $original, string $fingerprint): string
    {
        $original = $this->catalog->cleanName($original);

        if (Str::upper($original) === $original && strlen($original) <= 8) {
            return $original;
        }

        return Str::of($fingerprint ?: $original)->title()->toString();
    }

    private function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));

        if ($content === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $delimiter = str_contains($lines[0] ?? '', ';') ? ';' : ',';
        $headers = array_map(fn (string $header): string => $this->normalizeHeader($header), str_getcsv(array_shift($lines), $delimiter));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $row = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($values[$index] ?? ''));
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->trim()->lower()->replace([' ', '-', '.'], '_')->ascii()->toString();
    }

    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return '';
    }

    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    private function audit(Request $request, Merchant $merchant, string $event, ?MerchantBrand $brand, array $metadata): void
    {
        app(AuditLogger::class)->log($request, $merchant, $event, 'brands', 'info', [
            'module' => 'brands',
            'action' => Str::after($event, 'brand.'),
            'merchant_company_id' => $brand?->merchant_company_id ?: $this->currentCompany($request, $merchant)?->id,
            ...$metadata,
        ], $brand);
    }
}
