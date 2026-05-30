<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkLinkProductMeasurementTableRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\MeasurementTable;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\BrandCatalogService;
use App\Services\Catalog\CategoryCatalogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ResolvesMerchant;

    private const PRODUCT_COLUMNS = [
        'merchant_company_id',
        'measurement_table_id',
        'external_product_id',
        'sku',
        'name',
        'slug',
        'description',
        'category',
        'gender',
        'fit_profile',
        'status',
        'image_url',
    ];

    private const PRODUCT_ORIGIN_FIELDS = [
        'external_product_id',
        'sku',
        'name',
        'slug',
        'description',
        'category',
        'gender',
        'fit_profile',
        'measurement_table_id',
        'image_url',
        'brand',
        'age_group',
    ];

    private const PRODUCT_METADATA_FIELDS = [
        'brand',
        'age_group',
    ];

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $baseQuery = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn (Builder $query) => $this->scopeCompany($query, $company));

        $countQuery = clone $baseQuery;
        $this->applyProductFilters($countQuery, $request, ['status', 'table', 'readiness', 'sync_error']);

        $filteredQuery = clone $baseQuery;
        $this->applyProductFilters($filteredQuery, $request);

        $products = $filteredQuery
            ->with([
                'company',
                'measurementTable',
                'variants' => fn ($query) => $query->select(['id', 'product_id', 'size_label', 'is_active'])->orderBy('id'),
            ])
            ->withCount('variants')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return ProductResource::collection($products)->additional([
            'summary' => [
                'total' => (clone $countQuery)->count(),
                'filtered' => $products->total(),
                'with_measurement_table' => (clone $countQuery)->whereNotNull('measurement_table_id')->count(),
                'tabs' => $this->tabCounts($countQuery),
                'filters' => $this->filterOptions($baseQuery),
            ],
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        $company = array_key_exists('merchant_company_id', $data)
            ? $this->merchantCompany($merchant, $data['merchant_company_id'])
            : $activeCompany;
        $measurementTable = $this->resolveMeasurementTable($merchant->id, $company, $data['measurement_table_id'] ?? null);

        $product = Product::query()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $company?->id,
            'measurement_table_id' => $measurementTable?->id,
            'external_product_id' => $data['external_product_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'name' => $data['name'],
            'slug' => $this->slugFor($data['slug'] ?? $data['name']),
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'gender' => $data['gender'] ?? null,
            'fit_profile' => $data['fit_profile'] ?? null,
            'status' => $data['status'] ?? 'active',
            'image_url' => $data['image_url'] ?? null,
            'metadata' => $this->metadataForStore($data),
        ]);
        app(BrandCatalogService::class)->syncProductBrand($merchant, $company, $product, $data['brand'] ?? null, 'manual');
        app(CategoryCatalogService::class)->syncProductCategory($merchant, $company, $product, $data['category'] ?? null, 'manual');

        return (new ProductResource($product->load(['company', 'measurementTable', 'variants'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Product $product)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedProduct($merchant, $product, $company);

        return new ProductResource($product->load([
            'company',
            'measurementTable.rows',
            'variants' => fn ($query) => $query->orderBy('id'),
            'auditLogs' => fn ($query) => $query->latest()->limit(12),
        ]));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedProduct($merchant, $product, $company);
        $data = $request->validated();

        if (array_key_exists('merchant_company_id', $data)) {
            $data['merchant_company_id'] = $this->merchantCompany($merchant, $data['merchant_company_id'])?->id;
        }

        if (array_key_exists('measurement_table_id', $data)) {
            $targetCompanyId = $data['merchant_company_id'] ?? $product->merchant_company_id;
            $resolvedCompany = $targetCompanyId ? $this->merchantCompany($merchant, $targetCompanyId) : $company;
            $data['measurement_table_id'] = $this->resolveMeasurementTable($merchant->id, $resolvedCompany, $data['measurement_table_id'])?->id;
        }

        if (array_key_exists('slug', $data) && $data['slug']) {
            $data['slug'] = $this->slugFor($data['slug']);
        } elseif (array_key_exists('name', $data) && ! $product->slug) {
            $data['slug'] = $this->slugFor($data['name']);
        }

        $metadataResult = $this->metadataForUpdate($product, $data);
        $product->update([
            ...$this->columnData($data),
            'metadata' => $metadataResult['metadata'],
        ]);
        app(BrandCatalogService::class)->syncProductBrand($merchant, $company, $product->fresh(), $data['brand'] ?? null, 'manual');
        app(CategoryCatalogService::class)->syncProductCategory(
            $merchant,
            $company,
            $product->fresh(),
            array_key_exists('category', $data) ? $data['category'] : $product->category,
            'manual'
        );

        $this->logProductUpdateAudits($request, $merchant, $product, $metadataResult);

        $product->refresh()->load([
            'company',
            'measurementTable',
            'variants',
            'auditLogs' => fn ($query) => $query->latest()->limit(12),
        ]);

        return new ProductResource($product);
    }

    public function bulkLinkMeasurementTable(BulkLinkProductMeasurementTableRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        $action = $data['action'] ?? 'apply';
        $measurementTable = $action === 'undo'
            ? null
            : $this->resolveMeasurementTable($merchant->id, $company, (int) $data['measurement_table_id']);
        $productIds = collect($data['product_ids'])->map(fn (mixed $id): int => (int) $id)->values();
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('id', $productIds)
            ->with([
                'company',
                'measurementTable',
                'variants' => fn ($query) => $query->select(['id', 'product_id', 'size_label', 'is_active'])->orderBy('id'),
            ])
            ->get();
        $candidateTables = $this->candidateMeasurementTables($merchant->id, $company);
        $preview = $measurementTable
            ? $this->bulkMeasurementTablePreview($products, $measurementTable, $candidateTables)
            : null;
        $before = $products->map(fn (Product $product): array => [
            'product_id' => $product->id,
            'measurement_table_id' => $product->measurement_table_id,
            'measurement_table_name' => $product->measurementTable?->name,
        ])->values()->all();

        if ($action === 'preview') {
            return response()->json($preview);
        }

        if ($action === 'undo') {
            return $this->undoBulkMeasurementTable($request, $merchant, $company, $products, $data['batch_id'] ?? null);
        }

        if (($preview['summary']['conflicts'] ?? 0) > 0 && ! $request->boolean('confirm_conflicts')) {
            return response()->json([
                ...$preview,
                'message' => 'Confirme a substituição de produtos que já possuem tabela vinculada.',
            ], 409);
        }

        $batchId = (string) Str::uuid();
        $updatedIds = [];

        DB::transaction(function () use ($products, $measurementTable, $batchId, &$updatedIds): void {
            foreach ($products as $product) {
                if ((int) $product->measurement_table_id === (int) $measurementTable?->id) {
                    continue;
                }

                $metadata = $product->metadata ?? [];
                $metadata['bulk_measurement_table']['last_action'] = [
                    'batch_id' => $batchId,
                    'previous_measurement_table_id' => $product->measurement_table_id,
                    'measurement_table_id' => $measurementTable?->id,
                    'created_at' => now()->toISOString(),
                ];
                $metadata = $this->appendProductHistory($metadata, 'measurement_table.bulk_linked', [
                    'batch_id' => $batchId,
                    'from' => $product->measurement_table_id,
                    'to' => $measurementTable?->id,
                ]);

                $product->update([
                    'measurement_table_id' => $measurementTable?->id,
                    'metadata' => $metadata,
                ]);
                $updatedIds[] = $product->id;
            }
        });

        app(AuditLogger::class)->log($request, $merchant, 'product.bulk_measurement_table_linked', 'products', 'info', [
            'module' => 'products',
            'action' => 'bulk_measurement_table_link',
            'merchant_company_id' => $company?->id,
            'before' => $before,
            'after' => $this->productsForBulkResponse($products->pluck('id'))
                ->map(fn (Product $product): array => [
                    'product_id' => $product->id,
                    'measurement_table_id' => $product->measurement_table_id,
                    'measurement_table_name' => $product->measurementTable?->name,
                ])->values()->all(),
            'context_data' => [
                'batch_id' => $batchId,
                'product_ids' => $products->pluck('id')->values()->all(),
                'updated_product_ids' => $updatedIds,
                'measurement_table_id' => $measurementTable?->id,
                'conflicts' => $preview['summary']['conflicts'] ?? 0,
            ],
        ]);

        $updatedProducts = $this->productsForBulkResponse($products->pluck('id'));

        return ProductResource::collection($updatedProducts)->additional([
            'summary' => [
                'requested' => $productIds->count(),
                'updated' => count($updatedIds),
                'skipped_same_table' => $products->count() - count($updatedIds),
                'measurement_table_id' => $measurementTable?->id,
                'batch_id' => $batchId,
                'conflicts' => $preview['summary']['conflicts'] ?? 0,
            ],
            'preview' => $preview['preview'] ?? [],
        ]);
    }

    private function undoBulkMeasurementTable(Request $request, $merchant, $company, Collection $products, ?string $batchId)
    {
        $before = $products->map(fn (Product $product): array => [
            'product_id' => $product->id,
            'measurement_table_id' => $product->measurement_table_id,
            'measurement_table_name' => $product->measurementTable?->name,
        ])->values()->all();
        $updatedIds = [];
        $skippedIds = [];

        DB::transaction(function () use ($products, $batchId, &$updatedIds, &$skippedIds): void {
            foreach ($products as $product) {
                $metadata = $product->metadata ?? [];
                $lastAction = data_get($metadata, 'bulk_measurement_table.last_action');

                if (! is_array($lastAction) || ($batchId && data_get($lastAction, 'batch_id') !== $batchId)) {
                    $skippedIds[] = $product->id;

                    continue;
                }

                $previousTableId = data_get($lastAction, 'previous_measurement_table_id');
                $metadata['bulk_measurement_table']['last_undo'] = [
                    'batch_id' => data_get($lastAction, 'batch_id'),
                    'restored_measurement_table_id' => $previousTableId,
                    'undone_measurement_table_id' => $product->measurement_table_id,
                    'created_at' => now()->toISOString(),
                ];
                unset($metadata['bulk_measurement_table']['last_action']);
                $metadata = $this->appendProductHistory($metadata, 'measurement_table.bulk_unlinked', [
                    'batch_id' => data_get($lastAction, 'batch_id'),
                    'from' => $product->measurement_table_id,
                    'to' => $previousTableId,
                ]);

                $product->update([
                    'measurement_table_id' => $previousTableId ? (int) $previousTableId : null,
                    'metadata' => $metadata,
                ]);
                $updatedIds[] = $product->id;
            }
        });

        app(AuditLogger::class)->log($request, $merchant, 'product.bulk_measurement_table_undone', 'products', 'info', [
            'module' => 'products',
            'action' => 'bulk_measurement_table_undo',
            'merchant_company_id' => $company?->id,
            'before' => $before,
            'after' => $this->productsForBulkResponse($products->pluck('id'))
                ->map(fn (Product $product): array => [
                    'product_id' => $product->id,
                    'measurement_table_id' => $product->measurement_table_id,
                    'measurement_table_name' => $product->measurementTable?->name,
                ])->values()->all(),
            'context_data' => [
                'batch_id' => $batchId,
                'product_ids' => $products->pluck('id')->values()->all(),
                'updated_product_ids' => $updatedIds,
                'skipped_product_ids' => $skippedIds,
            ],
        ]);

        return ProductResource::collection($this->productsForBulkResponse($products->pluck('id')))->additional([
            'summary' => [
                'requested' => $products->count(),
                'updated' => count($updatedIds),
                'skipped' => count($skippedIds),
                'batch_id' => $batchId,
            ],
        ]);
    }

    private function productsForBulkResponse(Collection $productIds): Collection
    {
        return Product::query()
            ->whereKey($productIds->values()->all())
            ->with(['company', 'measurementTable'])
            ->withCount('variants')
            ->orderByDesc('id')
            ->get();
    }

    private function candidateMeasurementTables(int $merchantId, $company): Collection
    {
        return MeasurementTable::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->where('status', 'active')
            ->with('rows')
            ->get();
    }

    private function bulkMeasurementTablePreview(Collection $products, MeasurementTable $targetTable, Collection $candidateTables): array
    {
        $items = $products->map(function (Product $product) use ($targetTable, $candidateTables): array {
            $currentTable = $product->measurementTable;
            $recommendation = $this->recommendMeasurementTable($product, $candidateTables);
            $sameTable = (int) $product->measurement_table_id === (int) $targetTable->id;
            $conflict = $product->measurement_table_id && ! $sameTable;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku ?: $product->external_product_id,
                'category' => $product->category,
                'brand' => data_get($product->metadata ?? [], 'brand'),
                'fit_profile' => $product->fit_profile,
                'sizes' => $product->variants->pluck('size_label')->filter()->unique()->values()->all(),
                'current_table_id' => $currentTable?->id,
                'current_table_name' => $currentTable?->name,
                'target_table_id' => $targetTable->id,
                'target_table_name' => $targetTable->name,
                'conflict' => (bool) $conflict,
                'same_table' => $sameTable,
                'without_table' => blank($product->measurement_table_id),
                'recommendation' => $recommendation,
                'target_matches_recommendation' => $recommendation && (int) $recommendation['table_id'] === (int) $targetTable->id,
            ];
        })->values();

        return [
            'summary' => [
                'requested' => $products->count(),
                'target_table' => [
                    'id' => $targetTable->id,
                    'name' => $targetTable->name,
                    'product_type' => $targetTable->product_type,
                ],
                'without_table' => $items->where('without_table', true)->count(),
                'same_table' => $items->where('same_table', true)->count(),
                'conflicts' => $items->where('conflict', true)->count(),
                'recommended_target_matches' => $items->where('target_matches_recommendation', true)->count(),
            ],
            'preview' => $items->all(),
        ];
    }

    private function recommendMeasurementTable(Product $product, Collection $candidateTables): ?array
    {
        $productCategory = $this->normalizedText($product->category);
        $productBrand = $this->normalizedText(data_get($product->metadata ?? [], 'brand'));
        $productFit = $this->normalizedText($product->fit_profile);
        $productGender = $this->normalizedText($product->gender);
        $productSizes = $product->variants
            ->pluck('size_label')
            ->map(fn ($size) => $this->normalizedText($size))
            ->filter()
            ->unique()
            ->values();

        return $candidateTables
            ->map(function (MeasurementTable $table) use ($productCategory, $productBrand, $productFit, $productGender, $productSizes): array {
                $score = 0;
                $reasons = [];
                $tableType = $this->normalizedText($table->product_type);
                $tableName = $this->normalizedText($table->name);
                $tableGender = $this->normalizedText($table->gender);
                $tableFit = $this->normalizedText($table->fit_profile);
                $tableSizes = $table->rows
                    ->pluck('size_label')
                    ->map(fn ($size) => $this->normalizedText($size))
                    ->filter()
                    ->unique()
                    ->values();

                if ($productCategory && (($tableType && ($productCategory === $tableType || str_contains($tableType, $productCategory) || str_contains($productCategory, $tableType))) || str_contains($tableName, $productCategory))) {
                    $score += 4;
                    $reasons[] = 'categoria';
                }

                if ($productGender && $tableGender && $productGender === $tableGender) {
                    $score += 2;
                    $reasons[] = 'genero';
                }

                if ($productFit && $tableFit && $productFit === $tableFit) {
                    $score += 2;
                    $reasons[] = 'modelagem';
                }

                if ($productBrand && str_contains($tableName, $productBrand)) {
                    $score += 2;
                    $reasons[] = 'marca';
                }

                $sizeMatches = $productSizes->intersect($tableSizes)->count();
                if ($sizeMatches > 0) {
                    $score += min(3, $sizeMatches);
                    $reasons[] = "{$sizeMatches} tamanho(s)";
                }

                return [
                    'table_id' => $table->id,
                    'table_name' => $table->name,
                    'score' => $score,
                    'reasons' => $reasons,
                ];
            })
            ->filter(fn (array $recommendation): bool => $recommendation['score'] > 0)
            ->sortByDesc('score')
            ->values()
            ->first();
    }

    private function normalizedText(mixed $value): string
    {
        $normalized = Str::ascii(Str::lower((string) $value));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '');
    }

    public function destroy(Request $request, Product $product)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedProduct($merchant, $product, $company);
        $product->delete();

        return response()->json([
            'message' => 'Produto removido com sucesso.',
        ]);
    }

    private function resolveMeasurementTable(int $merchantId, $company, ?int $measurementTableId): ?MeasurementTable
    {
        if (! $measurementTableId) {
            return null;
        }

        return MeasurementTable::query()
            ->where('merchant_id', $merchantId)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereKey($measurementTableId)
            ->firstOrFail();
    }

    private function slugFor(string $value): string
    {
        return Str::slug($value) ?: Str::random(10);
    }

    private function applyProductFilters(Builder $query, Request $request, array $except = []): void
    {
        $skip = fn (string $key): bool => in_array($key, $except, true);

        if (! $skip('search') && $search = $this->stringFilter($request, 'search')) {
            $query->where(function (Builder $subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('external_product_id', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('gender', 'like', "%{$search}%")
                    ->orWhere('fit_profile', 'like', "%{$search}%")
                    ->orWhere('metadata', 'like', "%{$search}%")
                    ->orWhereHas('measurementTable', fn (Builder $tableQuery) => $tableQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (! $skip('status') && $status = $this->stringFilter($request, 'status')) {
            $query->where('status', $status);
        }

        if (! $skip('table') && $table = $this->stringFilter($request, 'table')) {
            match ($table) {
                'with_table' => $query->whereNotNull('measurement_table_id'),
                'without_table' => $query->whereNull('measurement_table_id'),
                default => ctype_digit($table) ? $query->where('measurement_table_id', (int) $table) : null,
            };
        }

        foreach ([
            'category' => 'category',
            'gender' => 'gender',
            'modeling' => 'fit_profile',
        ] as $requestKey => $column) {
            if (! $skip($requestKey) && $value = $this->stringFilter($request, $requestKey)) {
                $query->where($column, $value);
            }
        }

        foreach (['brand', 'age_group'] as $metadataKey) {
            if (! $skip($metadataKey) && $value = $this->stringFilter($request, $metadataKey)) {
                $query->where("metadata->{$metadataKey}", $value);
            }
        }

        if (! $skip('normalized_brand') && $value = $this->stringFilter($request, 'normalized_brand')) {
            $query->where(function (Builder $brandQuery) use ($value): void {
                $brandQuery->where('metadata->normalized_brand->name', $value)
                    ->orWhere('metadata->normalized_brand_name', $value)
                    ->orWhere('metadata->normalized_brand->slug', $value);

                if (ctype_digit($value)) {
                    $brandQuery->orWhere('metadata->normalized_brand_id', (int) $value)
                        ->orWhere('metadata->normalized_brand->id', (int) $value);
                }
            });
        }

        if (! $skip('normalized_category') && $value = $this->stringFilter($request, 'normalized_category')) {
            $query->where(function (Builder $categoryQuery) use ($value): void {
                $categoryQuery->where('metadata->normalized_category->name', $value)
                    ->orWhere('metadata->normalized_category_name', $value)
                    ->orWhere('metadata->normalized_category->slug', $value)
                    ->orWhere('metadata->normalized_category->type', $value);

                if (ctype_digit($value)) {
                    $categoryQuery->orWhere('metadata->normalized_category_id', (int) $value)
                        ->orWhere('metadata->normalized_category->id', (int) $value);
                }
            });
        }

        if (! $skip('source') && $source = $this->stringFilter($request, 'source')) {
            $this->applySourceFilter($query, $source);
        }

        if (! $skip('sync_error') && ($request->boolean('sync_error') || $this->stringFilter($request, 'error') === 'sync_error')) {
            $this->whereHasSyncError($query);
        }

        if (! $skip('readiness') && $readiness = $this->stringFilter($request, 'readiness')) {
            $this->applyReadinessFilter($query, $readiness);
        }
    }

    private function applyReadinessFilter(Builder $query, string $readiness): void
    {
        match ($readiness) {
            'ready' => $query
                ->where('status', 'active')
                ->whereNotNull('measurement_table_id')
                ->whereNotNull('fit_profile')
                ->where('fit_profile', '!=', '')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->where(fn (Builder $subQuery) => $this->whereWithoutSyncError($subQuery))
                ->where(fn (Builder $subQuery) => $this->whereMetadataFlagEnabled($subQuery, 'virtual_try_on_enabled'))
                ->where(fn (Builder $subQuery) => $this->whereMetadataFlagEnabled($subQuery, 'measurement_table_enabled')),
            'pending' => $query->where(function (Builder $subQuery): void {
                $subQuery->where('status', '!=', 'active')
                    ->orWhereNull('measurement_table_id')
                    ->orWhereNull('fit_profile')
                    ->orWhere('fit_profile', '')
                    ->orWhereNull('category')
                    ->orWhere('category', '')
                    ->orWhere(fn (Builder $syncQuery) => $this->whereHasSyncError($syncQuery))
                    ->orWhere(fn (Builder $activationQuery) => $this->whereMetadataFlagDisabled($activationQuery, 'virtual_try_on_enabled'))
                    ->orWhere(fn (Builder $activationQuery) => $this->whereMetadataFlagDisabled($activationQuery, 'measurement_table_enabled'));
            }),
            'without_measurement_table' => $query->whereNull('measurement_table_id'),
            'without_modeling' => $query->where(fn (Builder $subQuery) => $subQuery->whereNull('fit_profile')->orWhere('fit_profile', '')),
            'without_category' => $query->where(fn (Builder $subQuery) => $subQuery->whereNull('category')->orWhere('category', '')),
            'sync_error' => $this->whereHasSyncError($query),
            'inactive', 'disabled' => $query->where('status', 'inactive'),
            default => null,
        };
    }

    private function applySourceFilter(Builder $query, string $source): void
    {
        match ($source) {
            'bigshop' => $query->where(function (Builder $subQuery): void {
                $subQuery->where('metadata->source', 'bigshop')
                    ->orWhereNotNull('metadata->bigshop_last_sync_at');
            }),
            'import' => $query->where(function (Builder $subQuery): void {
                $subQuery->where('metadata->source', 'import')
                    ->orWhereNotNull('metadata->last_imported_at');
            }),
            'manual' => $query->where(function (Builder $subQuery): void {
                $subQuery->whereNull('metadata')
                    ->orWhere('metadata->source', 'manual')
                    ->orWhere(function (Builder $manualQuery): void {
                        $manualQuery->whereNull('metadata->source')
                            ->whereNull('metadata->last_imported_at')
                            ->whereNull('metadata->bigshop_last_sync_at');
                    });
            }),
            default => $query->where('metadata->source', $source),
        };
    }

    private function tabCounts(Builder $query): array
    {
        $readyQuery = clone $query;
        $pendingQuery = clone $query;
        $withoutTableQuery = clone $query;
        $syncErrorQuery = clone $query;
        $inactiveQuery = clone $query;

        $this->applyReadinessFilter($readyQuery, 'ready');
        $this->applyReadinessFilter($pendingQuery, 'pending');
        $this->applyReadinessFilter($withoutTableQuery, 'without_measurement_table');
        $this->whereHasSyncError($syncErrorQuery);

        return [
            'all' => (clone $query)->count(),
            'ready' => $readyQuery->count(),
            'pending' => $pendingQuery->count(),
            'without_measurement_table' => $withoutTableQuery->count(),
            'sync_error' => $syncErrorQuery->count(),
            'inactive' => $inactiveQuery->where('status', 'inactive')->count(),
        ];
    }

    private function filterOptions(Builder $query): array
    {
        $products = (clone $query)
            ->select(['category', 'gender', 'fit_profile', 'status', 'metadata'])
            ->latest('id')
            ->limit(3000)
            ->get();

        return [
            'categories' => $this->optionValues($products->pluck('category')),
            'normalized_categories' => $this->optionValues($products->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_category.name') ?: data_get($product->metadata ?? [], 'normalized_category_name'))),
            'brands' => $this->optionValues($products->map(fn (Product $product) => data_get($product->metadata ?? [], 'brand'))),
            'normalized_brands' => $this->optionValues($products->map(fn (Product $product) => data_get($product->metadata ?? [], 'normalized_brand.name') ?: data_get($product->metadata ?? [], 'normalized_brand_name'))),
            'genders' => $this->optionValues($products->pluck('gender')),
            'age_groups' => $this->optionValues($products->map(fn (Product $product) => data_get($product->metadata ?? [], 'age_group'))),
            'modelings' => $this->optionValues($products->pluck('fit_profile')),
            'sources' => $this->optionValues($products->map(fn (Product $product) => $this->sourceForProduct($product))),
            'statuses' => $this->optionValues($products->pluck('status')),
        ];
    }

    private function optionValues(Collection $values): array
    {
        return $values
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sortBy(fn (string $value): string => Str::lower($value))
            ->values()
            ->all();
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

    private function whereHasSyncError(Builder $query): void
    {
        $query->where(function (Builder $subQuery): void {
            $subQuery->whereNotNull('metadata->sync_error')
                ->orWhereNotNull('metadata->last_sync_error')
                ->orWhereNotNull('metadata->import_error')
                ->orWhere('metadata->sync.status', 'error')
                ->orWhere('metadata->last_sync.status', 'error');
        });
    }

    private function whereWithoutSyncError(Builder $query): void
    {
        $query->where(function (Builder $subQuery): void {
            $subQuery->whereNull('metadata')
                ->orWhere(function (Builder $cleanQuery): void {
                    $cleanQuery->whereNull('metadata->sync_error')
                        ->whereNull('metadata->last_sync_error')
                        ->whereNull('metadata->import_error')
                        ->where(fn (Builder $statusQuery) => $statusQuery->whereNull('metadata->sync.status')->orWhere('metadata->sync.status', '!=', 'error'))
                        ->where(fn (Builder $statusQuery) => $statusQuery->whereNull('metadata->last_sync.status')->orWhere('metadata->last_sync.status', '!=', 'error'));
                });
        });
    }

    private function whereMetadataFlagEnabled(Builder $query, string $key): void
    {
        $path = "metadata->activation->{$key}";

        $query->where(function (Builder $subQuery) use ($path): void {
            $subQuery->whereNull($path)
                ->orWhere($path, true)
                ->orWhere($path, 1)
                ->orWhere($path, '1')
                ->orWhere($path, 'true');
        });
    }

    private function whereMetadataFlagDisabled(Builder $query, string $key): void
    {
        $path = "metadata->activation->{$key}";

        $query->where(function (Builder $subQuery) use ($path): void {
            $subQuery->where($path, false)
                ->orWhere($path, 0)
                ->orWhere($path, '0')
                ->orWhere($path, 'false');
        });
    }

    private function stringFilter(Request $request, string $key): string
    {
        return trim($request->string($key)->toString());
    }

    private function columnData(array $data): array
    {
        return array_intersect_key($data, array_flip(self::PRODUCT_COLUMNS));
    }

    private function metadataForStore(array $data): array
    {
        $metadata = [
            'source' => 'manual',
            'field_sources' => [],
            'activation' => [
                'virtual_try_on_enabled' => (bool) ($data['virtual_try_on_enabled'] ?? true),
                'measurement_table_enabled' => (bool) ($data['measurement_table_enabled'] ?? true),
                'updated_at' => now()->toISOString(),
            ],
        ];

        foreach (self::PRODUCT_METADATA_FIELDS as $field) {
            if (array_key_exists($field, $data) && filled($data[$field])) {
                $metadata[$field] = $data[$field];
            }
        }

        foreach (self::PRODUCT_ORIGIN_FIELDS as $field) {
            if (array_key_exists($field, $data) && filled($data[$field])) {
                $metadata['field_sources'][$field] = 'manual';
            }
        }

        return $metadata;
    }

    private function metadataForUpdate(Product $product, array $data): array
    {
        $metadata = $product->metadata ?? [];
        $metadata['field_sources'] = is_array($metadata['field_sources'] ?? null) ? $metadata['field_sources'] : [];
        $metadata['manual_overrides'] = is_array($metadata['manual_overrides'] ?? null) ? $metadata['manual_overrides'] : [];
        $metadata['activation'] = is_array($metadata['activation'] ?? null) ? $metadata['activation'] : [];
        $manualOverrideFields = [];
        $activationChanges = [];
        $tracksImportedData = $this->sourceForProduct($product) !== 'manual' || filled($metadata['imported_snapshot'] ?? null);

        if ($tracksImportedData && blank($metadata['imported_snapshot'] ?? null)) {
            $metadata['imported_snapshot'] = $this->snapshotFromProduct($product, $metadata);
        }

        foreach (self::PRODUCT_ORIGIN_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $oldValue = $this->productFieldValue($product, $metadata, $field);
            $newValue = $data[$field];

            if (! $this->sameFieldValue($oldValue, $newValue)) {
                $metadata['field_sources'][$field] = 'manual';

                if ($tracksImportedData) {
                    $metadata['manual_overrides'][$field] = [
                        'value' => $newValue,
                        'imported_value' => data_get($metadata, "imported_snapshot.{$field}", $oldValue),
                        'source' => 'manual',
                        'updated_at' => now()->toISOString(),
                    ];
                    $manualOverrideFields[] = $field;
                }
            }
        }

        foreach (self::PRODUCT_METADATA_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if (filled($data[$field])) {
                $metadata[$field] = $data[$field];
            } else {
                unset($metadata[$field]);
            }
        }

        $previousActivation = $this->activationFromMetadata($metadata);

        foreach (['virtual_try_on_enabled', 'measurement_table_enabled'] as $flag) {
            if (! array_key_exists($flag, $data)) {
                continue;
            }

            $enabled = $this->booleanFlag($data[$flag]);
            $metadata['activation'][$flag] = $enabled;
            $metadata['activation']["{$flag}_updated_at"] = now()->toISOString();

            if ($previousActivation[$flag] !== $enabled) {
                $activationChanges[$flag] = [
                    'from' => $previousActivation[$flag],
                    'to' => $enabled,
                ];
            }
        }

        if ($activationChanges !== []) {
            $metadata['activation']['updated_at'] = now()->toISOString();
        }

        if ($manualOverrideFields !== []) {
            $metadata = $this->appendProductHistory($metadata, 'manual_overrides.updated', [
                'fields' => array_values(array_unique($manualOverrideFields)),
            ]);
        }

        if ($activationChanges !== []) {
            $metadata = $this->appendProductHistory($metadata, 'activation.updated', [
                'changes' => $activationChanges,
            ]);
        }

        return [
            'metadata' => $metadata,
            'manual_override_fields' => array_values(array_unique($manualOverrideFields)),
            'activation_changes' => $activationChanges,
        ];
    }

    private function snapshotFromProduct(Product $product, array $metadata): array
    {
        return collect(self::PRODUCT_ORIGIN_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $this->productFieldValue($product, $metadata, $field)])
            ->filter(fn (mixed $value): bool => filled($value))
            ->all();
    }

    private function productFieldValue(Product $product, array $metadata, string $field): mixed
    {
        if (in_array($field, self::PRODUCT_METADATA_FIELDS, true)) {
            return data_get($metadata, $field);
        }

        return $product->{$field};
    }

    private function sameFieldValue(mixed $oldValue, mixed $newValue): bool
    {
        return trim((string) $oldValue) === trim((string) $newValue);
    }

    private function activationFromMetadata(array $metadata): array
    {
        return [
            'virtual_try_on_enabled' => $this->booleanFlag(data_get($metadata, 'activation.virtual_try_on_enabled', true)),
            'measurement_table_enabled' => $this->booleanFlag(data_get($metadata, 'activation.measurement_table_enabled', true)),
        ];
    }

    private function booleanFlag(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function appendProductHistory(array $metadata, string $event, array $details): array
    {
        $history = is_array($metadata['history'] ?? null) ? $metadata['history'] : [];
        array_unshift($history, [
            'event' => $event,
            'source' => 'manual',
            'details' => $details,
            'created_at' => now()->toISOString(),
        ]);

        $metadata['history'] = array_slice($history, 0, 25);

        return $metadata;
    }

    private function logProductUpdateAudits(Request $request, $merchant, Product $product, array $metadataResult): void
    {
        $logger = app(AuditLogger::class);

        if ($metadataResult['activation_changes'] !== []) {
            $logger->log($request, $merchant, 'product.activation_updated', 'products', 'info', [
                'module' => 'products',
                'action' => 'update_activation',
                'merchant_company_id' => $product->merchant_company_id,
                'product_id' => $product->id,
                'changes' => $metadataResult['activation_changes'],
            ], $product);
        }

        if ($metadataResult['manual_override_fields'] !== []) {
            $logger->log($request, $merchant, 'product.manual_override_updated', 'products', 'info', [
                'module' => 'products',
                'action' => 'manual_override',
                'merchant_company_id' => $product->merchant_company_id,
                'product_id' => $product->id,
                'fields' => $metadataResult['manual_override_fields'],
            ], $product);
        }
    }
}
