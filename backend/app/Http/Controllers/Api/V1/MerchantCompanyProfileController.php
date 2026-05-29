<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Support\ActiveTenant;
use App\Support\PlatformCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MerchantCompanyProfileController extends Controller
{
    public function update(Request $request)
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);

        abort_if(! $company, 404, 'Empresa ativa não encontrada.');

        $data = $this->validateProfile($request);
        $this->guardDiscountedBigShopChange($company, $data['platform']);

        $company->forceFill([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'],
            'zip_code' => $data['zip_code'],
            'street' => $data['street'],
            'number' => $data['number'],
            'complement' => $data['complement'] ?? null,
            'district' => $data['district'],
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => 'BR',
            'domain' => $data['domain'],
            'platform' => $data['platform'],
            'bigshop_discount_active' => $this->nextBigShopDiscountState($company, $data['platform']),
        ])->save();

        $company->ensureAccessCode();
        $this->renamePlaceholderMerchant($merchant, $company);

        return response()->json([
            'data' => $this->serializeCompany($company->fresh() ?? $company),
        ]);
    }

    public function updatePlatform(Request $request)
    {
        $merchant = app(ActiveTenant::class)->merchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);

        abort_if(! $company, 404, 'Empresa ativa não encontrada.');

        $request->merge([
            'platform' => $request->input('platform') ?: 'custom',
        ]);

        $data = $request->validate([
            'platform' => ['required', 'string', Rule::in(PlatformCatalog::keys())],
        ]);

        if ($company->platform === 'bigshop' && $company->bigshop_discount_active && $data['platform'] !== 'bigshop') {
            throw ValidationException::withMessages([
                'platform' => ['Plano BigShop com desconto ativo: solicite a troca de integração pelo painel.'],
            ]);
        }

        $company->forceFill([
            'platform' => $data['platform'],
            'bigshop_discount_active' => $this->nextBigShopDiscountState($company, $data['platform']),
        ])->save();

        return response()->json([
            'data' => $this->serializeCompany($company->fresh() ?? $company),
        ]);
    }

    private function validateProfile(Request $request): array
    {
        $request->merge([
            'zip_code' => preg_replace('/\D+/', '', (string) $request->input('zip_code')) ?: null,
            'state' => filled($request->input('state'))
                ? mb_strtoupper(trim((string) $request->input('state')))
                : null,
            'domain' => $this->normalizeDomain((string) $request->input('domain')),
            'platform' => $request->input('platform') ?: 'custom',
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:180'],
            'platform' => ['required', 'string', Rule::in(PlatformCatalog::keys())],
            'zip_code' => ['required', 'string', 'size:8'],
            'street' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:40'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
        ]);
    }

    private function renamePlaceholderMerchant(Merchant $merchant, MerchantCompany $company): void
    {
        if (! Str::startsWith((string) $merchant->name, 'Empresa CNPJ ')) {
            return;
        }

        $merchant->forceFill([
            'name' => $company->name,
            'slug' => $this->uniqueMerchantSlug($company->name, $merchant),
        ])->save();
    }

    private function uniqueMerchantSlug(string $name, Merchant $merchant): string
    {
        $base = Str::slug($name) ?: 'lojista';
        $slug = $base;
        $counter = 2;

        while (Merchant::query()->where('slug', $slug)->whereKeyNot($merchant->id)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function normalizeDomain(string $domain): ?string
    {
        $value = Str::of($domain)
            ->lower()
            ->replace(['https://', 'http://'], '')
            ->trim()
            ->trim('/')
            ->toString();

        return $value !== '' ? $value : null;
    }

    private function serializeCompany(MerchantCompany $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'access_code' => $company->access_code,
            'legal_name' => $company->legal_name,
            'document' => $company->document,
            'zip_code' => $company->zip_code,
            'street' => $company->street,
            'number' => $company->number,
            'complement' => $company->complement,
            'district' => $company->district,
            'city' => $company->city,
            'state' => $company->state,
            'country' => $company->country,
            'domain' => $company->domain,
            'platform' => $company->platform,
            'bigshop_discount_active' => (bool) $company->bigshop_discount_active,
            'external_store_id' => $company->external_store_id,
            'status' => $company->status,
            'profile_completed' => $this->companyProfileCompleted($company),
        ];
    }

    private function companyProfileCompleted(MerchantCompany $company): bool
    {
        if (Str::startsWith((string) $company->name, 'Empresa CNPJ ')) {
            return false;
        }

        foreach (['name', 'legal_name', 'document', 'domain', 'zip_code', 'street', 'number', 'district', 'city', 'state'] as $field) {
            if (blank($company->{$field})) {
                return false;
            }
        }

        return true;
    }

    private function guardDiscountedBigShopChange(MerchantCompany $company, string $nextPlatform): void
    {
        if ($company->platform === 'bigshop' && $company->bigshop_discount_active && $nextPlatform !== 'bigshop') {
            throw ValidationException::withMessages([
                'platform' => ['Plano BigShop com desconto ativo: solicite a troca de integração pelo painel.'],
            ]);
        }
    }

    private function nextBigShopDiscountState(MerchantCompany $company, string $nextPlatform): bool
    {
        return $nextPlatform === 'bigshop' && (bool) $company->bigshop_discount_active;
    }
}
