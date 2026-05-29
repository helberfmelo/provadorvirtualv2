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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ResolvesMerchant;

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
        ]);

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

        $product->update($data);

        return new ProductResource($product->refresh()->load(['company', 'measurementTable', 'variants']));
    }

    public function bulkLinkMeasurementTable(BulkLinkProductMeasurementTableRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $data = $request->validated();
        $measurementTable = $this->resolveMeasurementTable($merchant->id, $company, (int) $data['measurement_table_id']);
        $productIds = collect($data['product_ids'])->map(fn (mixed $id): int => (int) $id)->values();
        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->whereIn('id', $productIds)
            ->get();

        Product::query()
            ->whereKey($products->pluck('id'))
            ->update(['measurement_table_id' => $measurementTable?->id]);

        $updatedProducts = Product::query()
            ->whereKey($products->pluck('id'))
            ->with(['company', 'measurementTable'])
            ->withCount('variants')
            ->orderByDesc('id')
            ->get();

        return ProductResource::collection($updatedProducts)->additional([
            'summary' => [
                'requested' => $productIds->count(),
                'updated' => $updatedProducts->count(),
                'measurement_table_id' => $measurementTable?->id,
            ],
        ]);
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
                ->where(fn (Builder $subQuery) => $this->whereWithoutSyncError($subQuery)),
            'pending' => $query->where(function (Builder $subQuery): void {
                $subQuery->where('status', '!=', 'active')
                    ->orWhereNull('measurement_table_id')
                    ->orWhereNull('fit_profile')
                    ->orWhere('fit_profile', '')
                    ->orWhereNull('category')
                    ->orWhere('category', '')
                    ->orWhere(fn (Builder $syncQuery) => $this->whereHasSyncError($syncQuery));
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
            'brands' => $this->optionValues($products->map(fn (Product $product) => data_get($product->metadata ?? [], 'brand'))),
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

    private function stringFilter(Request $request, string $key): string
    {
        return trim($request->string($key)->toString());
    }
}
