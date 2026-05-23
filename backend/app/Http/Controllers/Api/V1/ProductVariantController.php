<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductVariantRequest;
use App\Http\Requests\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController extends Controller
{
    use ResolvesMerchant;

    public function store(StoreProductVariantRequest $request, Product $product)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedProduct($merchant, $product, $company);
        $data = $request->validated();

        $variant = $product->variants()->create([
            'merchant_id' => $merchant->id,
            'merchant_company_id' => $product->merchant_company_id,
            'external_variant_id' => $data['external_variant_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'size_label' => $data['size_label'],
            'color' => $data['color'] ?? null,
            'price' => $data['price'] ?? null,
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return (new ProductVariantResource($variant))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedProduct($merchant, $product, $company);
        $this->scopedVariant($product, $variant);

        $variant->update($request->validated());

        return new ProductVariantResource($variant->refresh());
    }

    public function destroy(Request $request, Product $product, ProductVariant $variant)
    {
        $merchant = $this->currentMerchant($request);
        $company = $this->currentCompany($request, $merchant);
        $this->scopedProduct($merchant, $product, $company);
        $this->scopedVariant($product, $variant);
        $variant->delete();

        return response()->json([
            'message' => 'Variacao removida com sucesso.',
        ]);
    }
}
