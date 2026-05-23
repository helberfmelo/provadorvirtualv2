<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesMerchant;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWidgetInstallRequest;
use App\Http\Resources\WidgetInstallResource;
use App\Models\MerchantCompany;
use App\Models\WidgetInstall;
use App\Services\Audit\AuditLogger;
use App\Support\ActiveTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WidgetInstallController extends Controller
{
    use ResolvesMerchant;

    public function show(Request $request)
    {
        $merchant = $this->currentMerchant($request);
        $company = app(ActiveTenant::class)->company($request, $merchant);
        $install = $this->resolveInstall($merchant, $company);

        return new WidgetInstallResource($install->load('company'));
    }

    public function update(UpdateWidgetInstallRequest $request)
    {
        $merchant = $this->currentMerchant($request);
        $activeCompany = app(ActiveTenant::class)->company($request, $merchant);
        $install = $this->resolveInstall($merchant, $activeCompany);
        $data = $request->validated();

        abort_if(
            $activeCompany?->platform === 'bigshop'
            && array_key_exists('platform', $data)
            && $data['platform'] !== 'bigshop',
            403,
            'Sua empresa contratou o plano BigShop. O widget pode ser instalado apenas na BigShop.'
        );

        if ($activeCompany?->platform === 'bigshop') {
            $data['platform'] = 'bigshop';
            $data['merchant_company_id'] = $activeCompany->id;
        }

        if (array_key_exists('merchant_company_id', $data)) {
            $data['merchant_company_id'] = $this->merchantCompany($merchant, $data['merchant_company_id'])?->id;
        }

        if (array_key_exists('theme', $data)) {
            $data['theme'] = array_filter($data['theme'] ?? [], fn ($value): bool => filled($value));
        }

        if (array_key_exists('allowed_domains', $data)) {
            $data['allowed_domains'] = collect($data['allowed_domains'])
                ->map(fn (string $domain): string => Str::of($domain)->lower()->trim()->toString())
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $install->update(Arr::only($data, [
            'merchant_company_id',
            'platform',
            'allowed_domains',
            'theme',
            'is_active',
        ]));

        app(AuditLogger::class)->log($request, $merchant, 'widget_install.updated', 'widget', 'info', [
            'platform' => $install->platform,
            'is_active' => $install->is_active,
            'allowed_domains_count' => count($install->allowed_domains ?? []),
        ], $install);

        return new WidgetInstallResource($install->refresh()->load('company'));
    }

    private function resolveInstall($merchant, ?MerchantCompany $company = null): WidgetInstall
    {
        $company ??= $merchant->companies()->orderBy('id')->first();

        $install = WidgetInstall::query()->firstOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'merchant_company_id' => $company?->id,
                'public_key' => 'pv_'.Str::lower(Str::random(24)),
                'platform' => $company?->platform ?? 'custom',
                'allowed_domains' => array_values(array_filter([
                    $company?->domain,
                    'localhost',
                    '127.0.0.1',
                ])),
                'theme' => [
                    'primary' => '#0f172a',
                    'secondary' => '#ff4d5e',
                    'accent' => '#ff7a1a',
                    'background' => '#ffffff',
                    'text' => '#111827',
                    'font_family' => 'Manrope, Inter, Arial, sans-serif',
                    'font_size' => '14',
                    'font_weight' => '800',
                    'button_radius' => '8',
                ],
                'is_active' => true,
            ]
        );

        if ($company?->platform === 'bigshop' && $install->platform !== 'bigshop') {
            $install->forceFill([
                'merchant_company_id' => $company->id,
                'platform' => 'bigshop',
            ])->save();
        }

        return $install;
    }
}
