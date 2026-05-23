<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\MeasurementTable;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ActiveTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesMerchant
{
    protected function currentMerchant(Request $request): Merchant
    {
        return app(ActiveTenant::class)->merchant($request);
    }

    protected function currentCompany(Request $request, ?Merchant $merchant = null): ?MerchantCompany
    {
        return app(ActiveTenant::class)->company($request, $merchant);
    }

    protected function merchantCompany(Merchant $merchant, ?int $companyId): ?MerchantCompany
    {
        if (! $companyId) {
            return $merchant->companies()->orderBy('id')->first();
        }

        return $merchant->companies()->whereKey($companyId)->firstOrFail();
    }

    protected function scopedProduct(Merchant $merchant, Product $product, ?MerchantCompany $company = null): Product
    {
        if ((int) $product->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Produto não encontrado.');
        }

        if ($company && $product->merchant_company_id && (int) $product->merchant_company_id !== (int) $company->id) {
            throw new NotFoundHttpException('Produto não encontrado.');
        }

        return $product;
    }

    protected function scopedVariant(Product $product, ProductVariant $variant): ProductVariant
    {
        if ((int) $variant->product_id !== (int) $product->id) {
            throw new NotFoundHttpException('Variação não encontrada.');
        }

        return $variant;
    }

    protected function scopedMeasurementTable(Merchant $merchant, MeasurementTable $measurementTable, ?MerchantCompany $company = null): MeasurementTable
    {
        if ((int) $measurementTable->merchant_id !== (int) $merchant->id) {
            throw new NotFoundHttpException('Tabela de medidas não encontrada.');
        }

        if ($company && $measurementTable->merchant_company_id && (int) $measurementTable->merchant_company_id !== (int) $company->id) {
            throw new NotFoundHttpException('Tabela de medidas não encontrada.');
        }

        return $measurementTable;
    }

    protected function scopeCompany($query, ?MerchantCompany $company, string $column = 'merchant_company_id', bool $includeShared = true)
    {
        if (! $company) {
            return $query;
        }

        return $query->where(function ($innerQuery) use ($company, $column, $includeShared): void {
            $innerQuery->where($column, $company->id);

            if ($includeShared) {
                $innerQuery->orWhereNull($column);
            }
        });
    }
}
