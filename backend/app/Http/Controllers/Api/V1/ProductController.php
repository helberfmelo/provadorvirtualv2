<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\MeasurementTable;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ResolvesMerchant;

    public function index(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);

        $products = Product::query()
            ->where('merchant_id', $merchant->id)
            ->tap(fn ($query) => $this->scopeCompany($query, $company))
            ->with(['company', 'measurementTable'])
            ->withCount('variants')
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('external_product_id', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->get();

        return ProductResource::collection($products)->additional([
            'summary' => [
                'total' => $products->count(),
                'with_measurement_table' => $products->whereNotNull('measurement_table_id')->count(),
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
}
