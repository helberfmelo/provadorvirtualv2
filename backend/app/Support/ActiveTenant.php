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

        $merchant = $merchantId
            ? $user?->merchants()->whereKey($merchantId)->first()
            : $user?->merchants()->orderBy('merchants.id')->first();

        if (! $merchant) {
            throw new NotFoundHttpException('Lojista nao encontrado para o usuario autenticado.');
        }

        return $merchant;
    }

    public function company(Request $request, ?Merchant $merchant = null): ?MerchantCompany
    {
        $merchant ??= $this->merchant($request);
        $companyId = $this->abilityId($request, 'company');

        if ($companyId) {
            return $merchant->companies()->whereKey($companyId)->first();
        }

        return $merchant->companies()->orderBy('id')->first();
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
