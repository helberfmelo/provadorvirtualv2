<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Resources\MerchantCategoryResource;
use App\Http\Resources\TaxonomyCategoryResource;
use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\TaxonomyCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\CategoryCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController extends Controller
{
    use ResolvesMerchant;

    public function __construct(private readonly CategoryCatalogService $catalog) {}

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $products = $this->discoverCategoriesFromProducts($merchant, $company);
        $stats = $this->categoryStats($products);
        $taxonomyOptions = TaxonomyCategory::query()
            ->with('parent')
            ->where('status', 'active')
            ->orderByRaw('parent_id is not null')
            ->orderBy('name')
            ->limit(600)
            ->get();
        $merchantCategories = $this->categoryQuery($merchant, $company)
            ->with('taxonomyCategory.parent')
            ->orderBy('name')
            ->get();

        $merchantCategories->each(function (MerchantCategory $category) use ($stats, $taxonomyOptions, $merchantCategories): void {
            $fingerprint = data_get($category->metadata ?? [], 'fingerprint') ?: $this->catalog->fingerprint($category->name);
            $category->product_count = (int) ($stats['product_counts'][$category->name] ?? 0);
            $category->normalized_product_count = $category->taxonomy_category_id
                ? (int) ($stats['taxonomy_counts'][$category->taxonomy_category_id] ?? 0)
                : 0;
            $category->aliases = $stats['aliases'][$fingerprint] ?? [];
            $category->suggestion = $this->suggestionFor($category, $taxonomyOptions, $merchantCategories);
        });

        return MerchantCategoryResource::collection($merchantCategories)->additional([
            'summary' => [
                'local_categories' => $merchantCategories->count(),
                'mapped_categories' => $merchantCategories->whereNotNull('taxonomy_category_id')->count(),
                'pending_categories' => $merchantCategories->whereNull('taxonomy_category_id')->count(),
                'taxonomy_categories' => $taxonomyOptions->count(),
                'products_with_category' => $stats['products_with_category'],
                'products_with_normalized_category' => $stats['products_with_normalized_category'],
                'duplicate_groups' => count($stats['duplicate_groups']),
            ],
            'taxonomy_categories' => TaxonomyCategoryResource::collection($taxonomyOptions)->resolve($request),
            'taxonomy_tree' => TaxonomyCategoryResource::collection($taxonomyOptions->whereNull('parent_id')->load('children'))->resolve($request),
            'duplicate_groups' => $stats['duplicate_groups'],
            'category_types' => $this->categoryTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'taxonomy_category_id' => ['nullable', 'integer', 'exists:taxonomy_categories,id'],
            'taxonomy_name' => ['nullable', 'string', 'max:160'],
            'category_type' => ['nullable', 'string', 'max:60'],
            'gender' => ['nullable', 'string', 'max:40'],
            'age_group' => ['nullable', 'string', 'max:40'],
            'translation_pt_br' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,inactive,draft'],
            'apply_to_products' => ['nullable', 'boolean'],
        ]);
        $taxonomyCategory = $this->resolveTaxonomyCategory($data);
        $category = $this->catalog->ensureMerchantCategory($merchant, $company, $data['name'], [
            'taxonomy_category_id' => $taxonomyCategory?->id,
            'source' => $data['source'] ?? 'manual',
            'status' => $data['status'] ?? 'active',
            'metadata' => [
                'reviewed_at' => $taxonomyCategory ? now()->toISOString() : null,
            ],
        ])->load('taxonomyCategory.parent');
        $summary = null;

        if ($taxonomyCategory && $request->boolean('apply_to_products')) {
            $summary = $this->catalog->applyToProducts($merchant, $company, $category, $taxonomyCategory);
            $this->audit($request, $merchant, 'category.normalization_applied', $category, $summary);
        }

        return (new MerchantCategoryResource($category))
            ->additional(['summary' => $summary])
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, MerchantCategory $category)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedCategory($merchant, $company, $category);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'taxonomy_category_id' => ['nullable', 'integer', 'exists:taxonomy_categories,id'],
            'taxonomy_name' => ['nullable', 'string', 'max:160'],
            'category_type' => ['nullable', 'string', 'max:60'],
            'gender' => ['nullable', 'string', 'max:40'],
            'age_group' => ['nullable', 'string', 'max:40'],
            'translation_pt_br' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'in:active,inactive,draft'],
            'apply_to_products' => ['nullable', 'boolean'],
        ]);
        $taxonomyCategory = $this->resolveTaxonomyCategory($data, $category->taxonomyCategory);
        $name = $data['name'] ?? $category->name;
        $metadata = array_merge($category->metadata ?? [], [
            'fingerprint' => $this->catalog->fingerprint($name),
            'reviewed_at' => $taxonomyCategory ? now()->toISOString() : data_get($category->metadata ?? [], 'reviewed_at'),
        ]);

        $category->fill([
            'name' => $name,
            'slug' => $this->catalog->slug($name),
            'taxonomy_category_id' => $taxonomyCategory?->id,
            'source' => $data['source'] ?? $category->source,
            'status' => $data['status'] ?? $category->status,
            'metadata' => $metadata,
        ])->save();
        $summary = null;

        if ($taxonomyCategory && $request->boolean('apply_to_products')) {
            $summary = $this->catalog->applyToProducts($merchant, $company, $category->fresh(), $taxonomyCategory);
            $this->audit($request, $merchant, 'category.normalization_applied', $category, $summary);
        }

        return (new MerchantCategoryResource($category->fresh('taxonomyCategory.parent')))
            ->additional(['summary' => $summary]);
    }

    public function destroy(Request $request, MerchantCategory $category)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedCategory($merchant, $company, $category);
        $products = $this->catalog->productsByCategory($merchant, $company)->get($category->name, collect())->count();

        if ($products > 0) {
            $category->update([
                'status' => 'inactive',
                'metadata' => array_merge($category->metadata ?? [], [
                    'inactivated_at' => now()->toISOString(),
                    'inactivated_reason' => 'Categoria possui produtos vinculados.',
                ]),
            ]);
        } else {
            $category->delete();
        }

        $this->audit($request, $merchant, 'category.removed', $category, ['products' => $products]);

        return response()->json([
            'message' => $products > 0 ? 'Categoria inativada porque possui produtos vinculados.' : 'Categoria removida.',
        ]);
    }

    public function export(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->discoverCategoriesFromProducts($merchant, $company);
        $categories = $this->categoryQuery($merchant, $company)
            ->with('taxonomyCategory')
            ->orderBy('name')
            ->get();
        $csv = $this->csv([
            ['name', 'taxonomy_category', 'category_type', 'gender', 'age_group', 'translation_pt_br', 'status', 'source'],
            ...$categories->map(fn (MerchantCategory $category): array => [
                $category->name,
                $category->taxonomyCategory?->name,
                $category->taxonomyCategory?->category_type,
                $category->taxonomyCategory?->gender,
                $category->taxonomyCategory?->age_group,
                data_get($category->taxonomyCategory?->translations ?? [], 'pt_BR'),
                $category->status,
                $category->source,
            ])->all(),
        ]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="provador-virtual-categorias.csv"',
        ]);
    }

    public function template()
    {
        return response($this->csv([
            ['name', 'taxonomy_category', 'category_type', 'gender', 'age_group', 'translation_pt_br', 'status', 'source'],
            ['CAMISA', 'Camisas', 'top', '', 'adult', 'Camisas', 'active', 'import'],
        ]), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modelo-categorias-provador-virtual.csv"',
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
            $name = $this->value($row, ['name', 'categoria', 'category']);
            $status = $this->value($row, ['status']) ?: 'active';

            return [
                'line' => $index + 2,
                'valid' => $name !== '',
                'errors' => $name === '' ? ['Informe o nome da categoria.'] : [],
                'name' => $name,
                'taxonomy_category' => $this->value($row, ['taxonomy_category', 'categoria_normalizada', 'associated_category']),
                'category_type' => $this->value($row, ['category_type', 'type', 'tipo']) ?: 'other',
                'gender' => $this->value($row, ['gender', 'genero']),
                'age_group' => $this->value($row, ['age_group', 'faixa_etaria']),
                'translation_pt_br' => $this->value($row, ['translation_pt_br', 'traducao_pt_br', 'translation']),
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
                $taxonomyCategory = $row['taxonomy_category']
                    ? $this->catalog->ensureTaxonomyCategory($row['taxonomy_category'], $row['category_type'], null, [
                        'gender' => $row['gender'] ?: null,
                        'age_group' => $row['age_group'] ?: null,
                        'translations' => array_filter(['pt_BR' => $row['translation_pt_br'] ?: $row['taxonomy_category']]),
                        'metadata' => ['source' => 'category_import'],
                    ])
                    : null;
                $category = $this->catalog->ensureMerchantCategory($merchant, $company, $row['name'], [
                    'taxonomy_category_id' => $taxonomyCategory?->id,
                    'source' => $row['source'],
                    'status' => $row['status'],
                    'metadata' => ['imported_at' => now()->toISOString()],
                ]);

                if ($taxonomyCategory && $applyToProducts) {
                    $updatedProducts += $this->catalog
                        ->applyToProducts($merchant, $company, $category, $taxonomyCategory, 'import')['updated'];
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

        $this->audit($request, $merchant, 'category.imported', null, $summary);

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
            'target_category_id' => ['required', 'integer', 'exists:merchant_categories,id'],
            'source_category_ids' => ['required', 'array', 'min:1'],
            'source_category_ids.*' => ['integer', 'exists:merchant_categories,id'],
            'taxonomy_category_id' => ['nullable', 'integer', 'exists:taxonomy_categories,id'],
            'taxonomy_name' => ['nullable', 'string', 'max:160'],
            'category_type' => ['nullable', 'string', 'max:60'],
            'apply_to_products' => ['nullable', 'boolean'],
        ]);
        $target = $this->scopedCategory($merchant, $company, MerchantCategory::query()->findOrFail($data['target_category_id']));
        $sources = MerchantCategory::query()
            ->whereIn('id', $data['source_category_ids'])
            ->get()
            ->map(fn (MerchantCategory $source): MerchantCategory => $this->scopedCategory($merchant, $company, $source));
        $taxonomyCategory = $this->resolveTaxonomyCategory($data, $target->taxonomyCategory)
            ?: $this->catalog->ensureTaxonomyCategory($target->name, $data['category_type'] ?? 'other', null, ['metadata' => ['source' => 'category_merge']]);
        $applyToProducts = $request->boolean('apply_to_products', true);

        $summary = DB::transaction(function () use ($merchant, $company, $target, $sources, $taxonomyCategory, $applyToProducts): array {
            $updatedProducts = 0;
            $target->update([
                'taxonomy_category_id' => $taxonomyCategory->id,
                'metadata' => array_merge($target->metadata ?? [], [
                    'reviewed_at' => now()->toISOString(),
                    'merge_target' => true,
                ]),
            ]);

            if ($applyToProducts) {
                $updatedProducts += $this->catalog
                    ->applyToProducts($merchant, $company, $target->fresh(), $taxonomyCategory, 'merge')['updated'];
            }

            foreach ($sources as $source) {
                if ($applyToProducts) {
                    $updatedProducts += $this->catalog
                        ->applyToProducts($merchant, $company, $source, $taxonomyCategory, 'merge')['updated'];
                }

                $source->update([
                    'taxonomy_category_id' => $taxonomyCategory->id,
                    'status' => 'inactive',
                    'metadata' => array_merge($source->metadata ?? [], [
                        'merged_into_category_id' => $target->id,
                        'merged_into_name' => $target->name,
                        'merged_at' => now()->toISOString(),
                    ]),
                ]);
            }

            return [
                'target_category_id' => $target->id,
                'source_categories' => $sources->count(),
                'taxonomy_category_id' => $taxonomyCategory->id,
                'updated_products' => $updatedProducts,
            ];
        });

        $this->audit($request, $merchant, 'category.merged', $target, $summary);

        return response()->json([
            'summary' => $summary,
            'data' => (new MerchantCategoryResource($target->fresh('taxonomyCategory.parent')))->resolve($request),
        ]);
    }

    private function discoverCategoriesFromProducts(Merchant $merchant, ?MerchantCompany $company): Collection
    {
        $groups = $this->catalog->productsByCategory($merchant, $company);

        foreach ($groups as $categoryName => $products) {
            $taxonomyId = $products
                ->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_category.id') ?: data_get($product->metadata ?? [], 'normalized_category_id'))
                ->filter()
                ->first();
            $this->catalog->ensureMerchantCategory($merchant, $company, $categoryName, [
                'taxonomy_category_id' => $taxonomyId ? (int) $taxonomyId : null,
                'source' => $this->sourceForProducts($products),
                'metadata' => [
                    'product_count_detected' => $products->count(),
                    'sources' => $products->map(fn (Product $product) => $this->sourceForProduct($product))->unique()->values()->all(),
                ],
            ]);
        }

        return $groups->flatten(1);
    }

    private function categoryStats(Collection $products): array
    {
        $productsWithCategory = $products->filter(fn (Product $product): bool => filled($product->category));
        $productCounts = $productsWithCategory
            ->countBy(fn (Product $product): string => $this->catalog->cleanName((string) $product->category))
            ->all();
        $taxonomyCounts = $products
            ->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_category.id') ?: data_get($product->metadata ?? [], 'normalized_category_id'))
            ->filter()
            ->countBy()
            ->all();
        $aliases = $productsWithCategory
            ->map(fn (Product $product): string => $this->catalog->cleanName((string) $product->category))
            ->unique()
            ->groupBy(fn (string $category): string => $this->catalog->fingerprint($category))
            ->map(fn (Collection $categories): array => $categories->values()->all())
            ->all();
        $duplicateGroups = collect($aliases)
            ->filter(fn (array $categories): bool => count($categories) > 1)
            ->map(fn (array $categories, string $fingerprint): array => [
                'fingerprint' => $fingerprint,
                'suggested_name' => $this->displayName($categories[0], $fingerprint),
                'categories' => $categories,
                'products_count' => collect($categories)->sum(fn (string $category): int => (int) ($productCounts[$category] ?? 0)),
            ])
            ->values()
            ->all();

        return [
            'product_counts' => $productCounts,
            'taxonomy_counts' => $taxonomyCounts,
            'aliases' => $aliases,
            'duplicate_groups' => $duplicateGroups,
            'products_with_category' => $productsWithCategory->count(),
            'products_with_normalized_category' => $products
                ->filter(fn (Product $product): bool => filled(data_get($product->metadata ?? [], 'normalized_category.name')) || filled(data_get($product->metadata ?? [], 'normalized_category_name')))
                ->count(),
        ];
    }

    private function suggestionFor(MerchantCategory $category, Collection $taxonomyOptions, Collection $merchantCategories): array
    {
        $fingerprint = data_get($category->metadata ?? [], 'fingerprint') ?: $this->catalog->fingerprint($category->name);
        $exactTaxonomy = $taxonomyOptions->first(fn (TaxonomyCategory $taxonomy): bool => $this->taxonomyMatches($taxonomy, $category->name, $fingerprint));
        $mappedSibling = $merchantCategories
            ->first(fn (MerchantCategory $sibling): bool => (int) $sibling->id !== (int) $category->id
                && filled($sibling->taxonomy_category_id)
                && (data_get($sibling->metadata ?? [], 'fingerprint') ?: $this->catalog->fingerprint($sibling->name)) === $fingerprint);

        if ($category->taxonomyCategory) {
            return [
                'mode' => 'mapped',
                'taxonomy_category_id' => $category->taxonomyCategory->id,
                'taxonomy_name' => $category->taxonomyCategory->name,
                'category_type' => $category->taxonomyCategory->category_type,
                'confidence' => 'high',
                'reasons' => ['Mapeamento revisado para regras, filtros, IA e relatórios.'],
            ];
        }

        if ($exactTaxonomy) {
            return [
                'mode' => 'existing',
                'taxonomy_category_id' => $exactTaxonomy->id,
                'taxonomy_name' => $exactTaxonomy->name,
                'category_type' => $exactTaxonomy->category_type,
                'confidence' => 'high',
                'reasons' => ['Nome igual ou equivalente a uma categoria da taxonomia.'],
            ];
        }

        if ($mappedSibling?->taxonomyCategory) {
            return [
                'mode' => 'existing',
                'taxonomy_category_id' => $mappedSibling->taxonomyCategory->id,
                'taxonomy_name' => $mappedSibling->taxonomyCategory->name,
                'category_type' => $mappedSibling->taxonomyCategory->category_type,
                'confidence' => 'high',
                'reasons' => ['Outra variação local já foi revisada para esta categoria.'],
            ];
        }

        $inferred = $this->inferredTaxonomy($category->name, $taxonomyOptions);

        if ($inferred) {
            return [
                'mode' => 'existing',
                'taxonomy_category_id' => $inferred->id,
                'taxonomy_name' => $inferred->name,
                'category_type' => $inferred->category_type,
                'confidence' => 'medium',
                'reasons' => ['Sugestão por tipo de peça, nome do produto/feed e histórico local.'],
            ];
        }

        return [
            'mode' => 'create',
            'taxonomy_category_id' => null,
            'taxonomy_name' => $this->displayName($category->name, $fingerprint),
            'category_type' => 'other',
            'confidence' => count($category->aliases ?? []) > 1 ? 'medium' : 'low',
            'reasons' => [
                'Sugestão criada a partir da categoria local e histórico de importação.',
                'Revise antes de aplicar aos produtos.',
            ],
        ];
    }

    private function taxonomyMatches(TaxonomyCategory $taxonomy, string $name, string $fingerprint): bool
    {
        return $taxonomy->slug === $this->catalog->slug($name)
            || $this->catalog->fingerprint($taxonomy->name) === $fingerprint;
    }

    private function inferredTaxonomy(string $name, Collection $taxonomyOptions): ?TaxonomyCategory
    {
        $fingerprint = $this->catalog->fingerprint($name);
        $dictionary = [
            'camisa' => 'Camisas',
            'camiseta' => 'Camisetas',
            'blusa' => 'Blusas',
            'casaco' => 'Casacos',
            'calca' => 'Calças',
            'bermuda' => 'Bermudas',
            'short' => 'Shorts',
            'saia' => 'Saias',
            'vestido' => 'Vestidos',
            'macacao' => 'Macacões',
            'conjunto' => 'Conjuntos',
            'tenis' => 'Tênis',
            'sapato' => 'Sapatos',
            'sandalia' => 'Sandálias',
            'bota' => 'Botas',
        ];

        foreach ($dictionary as $needle => $target) {
            if (str_contains($fingerprint, $needle)) {
                return $taxonomyOptions->firstWhere('name', $target);
            }
        }

        return null;
    }

    private function resolveTaxonomyCategory(array $data, ?TaxonomyCategory $fallback = null): ?TaxonomyCategory
    {
        if (! empty($data['taxonomy_category_id'])) {
            return TaxonomyCategory::query()->findOrFail((int) $data['taxonomy_category_id']);
        }

        if (! empty($data['taxonomy_name'])) {
            return $this->catalog->ensureTaxonomyCategory($data['taxonomy_name'], $data['category_type'] ?? 'other', null, [
                'gender' => $data['gender'] ?? null,
                'age_group' => $data['age_group'] ?? null,
                'translations' => array_filter(['pt_BR' => $data['translation_pt_br'] ?? $data['taxonomy_name']]),
            ]);
        }

        return $fallback;
    }

    private function scopedCategory(Merchant $merchant, ?MerchantCompany $company, MerchantCategory $category): MerchantCategory
    {
        if ((int) $category->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Categoria não encontrada.');
        }

        if ($company && $category->merchant_company_id && (int) $category->merchant_company_id !== (int) $company->id) {
            throw new NotFoundHttpException('Categoria não encontrada.');
        }

        return $category;
    }

    private function categoryQuery(Merchant $merchant, ?MerchantCompany $company): Builder
    {
        return MerchantCategory::query()
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

    private function categoryTypes(): array
    {
        return [
            ['value' => 'top', 'label' => 'Superior'],
            ['value' => 'bottom', 'label' => 'Inferior'],
            ['value' => 'full_body', 'label' => 'Corpo inteiro'],
            ['value' => 'shoe', 'label' => 'Calçados'],
            ['value' => 'top_underwear', 'label' => 'Íntimo superior'],
            ['value' => 'bottom_underwear', 'label' => 'Íntimo inferior'],
            ['value' => 'accessory', 'label' => 'Acessórios'],
            ['value' => 'other', 'label' => 'Outro'],
        ];
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

    private function audit(Request $request, Merchant $merchant, string $event, ?MerchantCategory $category, array $metadata): void
    {
        app(AuditLogger::class)->log($request, $merchant, $event, 'categories', 'info', [
            'module' => 'categories',
            'action' => Str::after($event, 'category.'),
            'merchant_company_id' => $category?->merchant_company_id ?: $this->currentCompany($request, $merchant)?->id,
            ...$metadata,
        ], $category);
    }
}
