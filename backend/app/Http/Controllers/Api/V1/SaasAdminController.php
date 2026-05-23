<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\ImportJob;
use App\Models\IntegrationEvent;
use App\Models\Merchant;
use App\Models\MerchantCompany;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\RecommendationLog;
use App\Models\WidgetInstall;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SaasAdminController extends Controller
{
    public function overview(Request $request): array
    {
        $this->ensureAdmin($request);

        return [
            'data' => [
                'summary' => [
                    'merchants' => Merchant::query()->count(),
                    'trialing_merchants' => Merchant::query()->where('billing_status', 'trialing')->count(),
                    'companies' => MerchantCompany::query()->count(),
                    'products' => Product::query()->count(),
                    'active_widgets' => WidgetInstall::query()->where('is_active', true)->count(),
                    'configured_integrations' => PlatformConnection::query()->whereIn('status', ['configured', 'connected'])->count(),
                    'recommendations_7d' => RecommendationLog::query()->where('created_at', '>=', now()->subDays(7))->count(),
                    'ai_uses_7d' => AiUsageLog::query()->where('created_at', '>=', now()->subDays(7))->count(),
                    'failed_imports_7d' => ImportJob::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
                    'failed_integrations_7d' => IntegrationEvent::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count(),
                ],
            ],
        ];
    }

    public function merchants(Request $request): array
    {
        $this->ensureAdmin($request);

        $merchants = Merchant::query()
            ->withCount(['companies', 'products', 'measurementTables', 'widgetInstalls', 'platformConnections'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return [
            'data' => $merchants->map(fn (Merchant $merchant): array => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'slug' => $merchant->slug,
                'billing_status' => $merchant->billing_status,
                'trial_ends_at' => $merchant->trial_ends_at?->toDateString(),
                'companies_count' => $merchant->companies_count,
                'products_count' => $merchant->products_count,
                'measurement_tables_count' => $merchant->measurement_tables_count,
                'widget_installs_count' => $merchant->widget_installs_count,
                'platform_connections_count' => $merchant->platform_connections_count,
                'recommendations_7d' => RecommendationLog::query()
                    ->where('merchant_id', $merchant->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'last_recommendation_at' => RecommendationLog::query()
                    ->where('merchant_id', $merchant->id)
                    ->latest('id')
                    ->first()?->created_at?->toISOString(),
            ]),
        ];
    }

    private function ensureAdmin(Request $request): void
    {
        if (! in_array($request->user()?->role, ['admin', 'support'], true)) {
            throw new HttpException(403, 'Acesso restrito ao painel SaaS.');
        }
    }
}
