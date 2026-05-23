<?php

namespace App\Support;

use App\Models\Merchant;
use App\Models\MerchantCompany;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActiveTenant
{
    public function merchant(Request $request): Merchant
    {
        $user = $request->user();
        $merchantId = $this->abilityId($request, 'merchant');
        $query = $user?->merchants();

        if ($query && ! in_array($user->role, ['admin', 'support'], true)) {
            $query->where(function ($innerQuery): void {
                $innerQuery->where('merchant_user.status', 'active')
                    ->orWhereNull('merchant_user.status');
            });
        }

        $merchant = $merchantId
            ? $query?->whereKey($merchantId)->first()
            : $query?->orderBy('merchants.id')->first();

        if (! $merchant) {
            throw new NotFoundHttpException('Lojista nao encontrado para o usuario autenticado.');
        }

        return $merchant;
    }

    public function company(Request $request, ?Merchant $merchant = null): ?MerchantCompany
    {
        $merchant ??= $this->merchant($request);
        $companyId = $this->abilityId($request, 'company');

        $company = $companyId
            ? $merchant->companies()->whereKey($companyId)->first()
            : $merchant->companies()->orderBy('id')->first();

        if (! $company) {
            return null;
        }

        if (($company->status ?? 'active') === 'inactive') {
            throw new NotFoundHttpException('Empresa inativa ou indisponivel.');
        }

        $user = $request->user();
        if ($user && ! in_array($user->role, ['admin', 'support'], true)) {
            $activeMerchant = $user->merchants()
                ->whereKey($merchant->id)
                ->where(function ($query): void {
                    $query->where('merchant_user.status', 'active')
                        ->orWhereNull('merchant_user.status');
                })
                ->first();
            $pivotCompanyId = $activeMerchant?->pivot?->merchant_company_id;

            if ($pivotCompanyId && (int) $pivotCompanyId !== (int) $company->id) {
                throw new NotFoundHttpException('Empresa nao encontrada para o usuario autenticado.');
            }
        }

        return $company;
    }

    public function abilityId(Request $request, string $prefix): ?int
    {
        $needle = $prefix.':';
        $abilities = $request->user()?->currentAccessToken()?->abilities ?? [];

        foreach ($abilities as $ability) {
            if (str_starts_with((string) $ability, $needle)) {
                $value = (int) substr((string) $ability, strlen($needle));

                return $value > 0 ? $value : null;
            }
        }

        return null;
    }
}
