<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesMerchant
{
    protected function currentMerchant(Request $request): Merchant
    {
        $merchant = $request->user()?->merchants()->first();

        if (! $merchant) {
            throw new NotFoundHttpException('Lojista nao encontrado para o usuario autenticado.');
        }

        return $merchant;
    }

    protected function merchantCompany(Merchant $merchant, ?int $companyId): ?MerchantCompany
    {
        if (! $companyId) {
            return $merchant->companies()->orderBy('id')->first();
        }

        return $merchant->companies()->whereKey($companyId)->firstOrFail();
    }

    protected function scopedProduct(Merchant $merchant, Product $product): Product
    {
        if ((int) $product->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Produto nao encontrado.');
        }

        return $product;
    }

    protected function scopedVariant(Product $product, ProductVariant $variant): ProductVariant
    {
        if ((int) $variant->product_id !== (int) $product->id) {
            throw new NotFoundHttpException('Variacao nao encontrada.');
        }

        return $variant;
    }

    protected function scopedMeasurementTable(Merchant $merchant, MeasurementTable $measurementTable): MeasurementTable
    {
        if ((int) $measurementTable->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Tabela de medidas nao encontrada.');
        }

        return $measurementTable;
    }
}
