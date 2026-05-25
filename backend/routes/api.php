<?php

use App\Http\Controllers\Api\V1\AiMeasurementAssistantController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BigShopActivationController;
use App\Http\Controllers\Api\V1\BigShopIntegrationController;
use App\Http\Controllers\Api\V1\BillingSubscriptionController;
use App\Http\Controllers\Api\V1\DemoProductController;
use App\Http\Controllers\Api\V1\GoLiveReadinessController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\MeasurementTableController;
use App\Http\Controllers\Api\V1\MeasurementTemplateController;
use App\Http\Controllers\Api\V1\OperationalStatusController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\PublicCheckoutController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\SaasAdminController;
use App\Http\Controllers\Api\V1\SaasCheckoutController;
use App\Http\Controllers\Api\V1\SaasEmailController;
use App\Http\Controllers\Api\V1\UserAccessController;
use App\Http\Controllers\Api\V1\WidgetInstallController;
use App\Models\MeasurementTable;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\RecommendationLog;
use App\Models\WidgetInstall;
use App\Support\ActiveTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/ops/status', OperationalStatusController::class)->middleware('throttle:60,1');
    Route::get('/demo/storefront', [DemoProductController::class, 'index']);
    Route::get('/demo/storefront/{slug}', [DemoProductController::class, 'show']);
    Route::get('/demo/product-test', [DemoProductController::class, 'show']);
    Route::options('/public/recommendations/{path?}', fn () => response()->noContent())
        ->where('path', '.*')
        ->middleware('widget.origin');
    Route::options('/public/shopper-profiles/{path?}', fn () => response()->noContent())
        ->where('path', '.*')
        ->middleware('widget.origin');
    Route::post('/public/recommendations/config-check', [RecommendationController::class, 'configCheck'])
        ->middleware(['widget.origin', 'throttle:60,1']);
    Route::post('/public/recommendations', [RecommendationController::class, 'store'])
        ->middleware(['widget.origin', 'throttle:60,1']);
    Route::post('/public/recommendations/{recommendationLog}/feedback', [RecommendationController::class, 'feedback'])
        ->middleware(['widget.origin', 'throttle:120,1']);
    Route::post('/public/recommendations/{recommendationLog}/signal', [RecommendationController::class, 'signal'])
        ->middleware(['widget.origin', 'throttle:120,1']);
    Route::post('/public/shopper-profiles/forget', [RecommendationController::class, 'forgetProfile'])
        ->middleware(['widget.origin', 'throttle:30,1']);
    Route::post('/public/company-access', [SaasAdminController::class, 'resolveCompanyAccess'])
        ->middleware('throttle:30,1');
    Route::get('/public/checkout/config', [PublicCheckoutController::class, 'config'])
        ->middleware('throttle:60,1');
    Route::post('/public/checkout', [PublicCheckoutController::class, 'store'])
        ->middleware('throttle:12,1');
    Route::get('/public/checkout/{reference}', [PublicCheckoutController::class, 'show'])
        ->middleware('throttle:60,1');
    Route::post('/webhooks/pagarme', [PublicCheckoutController::class, 'webhook'])
        ->middleware('throttle:120,1');
    Route::post('/webhooks/mercado-pago', [PublicCheckoutController::class, 'webhook'])
        ->defaults('provider', 'mercado_pago')
        ->middleware('throttle:120,1');
    Route::post('/public/bigshop/activate', BigShopActivationController::class)
        ->middleware('throttle:20,1');

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/select-company', [AuthController::class, 'selectCompany']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/merchant/overview', function (Request $request) {
            $merchant = app(ActiveTenant::class)->merchant($request);
            $company = app(ActiveTenant::class)->company($request, $merchant);
            $scopeCompany = function ($query) use ($company): void {
                if ($company) {
                    $query->where(function ($innerQuery) use ($company): void {
                        $innerQuery->where('merchant_company_id', $company->id)
                            ->orWhereNull('merchant_company_id');
                    });
                }
            };

            return response()->json([
                'summary' => [
                    'products' => Product::query()->where('merchant_id', $merchant->id)->tap($scopeCompany)->count(),
                    'measurement_tables' => MeasurementTable::query()->where('merchant_id', $merchant->id)->tap($scopeCompany)->count(),
                    'widget_status' => 'demo-ready',
                    'widget_active' => WidgetInstall::query()
                        ->where('merchant_id', $merchant->id)
                        ->tap($scopeCompany)
                        ->where('is_active', true)
                        ->exists(),
                    'integrations_configured' => PlatformConnection::query()
                        ->where('merchant_id', $merchant->id)
                        ->tap($scopeCompany)
                        ->whereIn('status', ['configured', 'connected'])
                        ->count(),
                    'recommendations_today' => RecommendationLog::query()
                        ->where('merchant_id', $merchant->id)
                        ->tap($scopeCompany)
                        ->whereDate('created_at', now()->toDateString())
                        ->count(),
                ],
            ]);
        })->middleware('portal.permission:merchant,dashboard,view');
        Route::get('/billing/subscription', [BillingSubscriptionController::class, 'show'])
            ->middleware('portal.permission:merchant,dashboard,view');
        Route::patch('/billing/subscription/auto-renewal', [BillingSubscriptionController::class, 'updateAutoRenewal'])
            ->middleware('portal.permission:merchant,dashboard,edit');
        Route::get('/measurement-templates', [MeasurementTemplateController::class, 'index'])
            ->middleware('portal.permission:merchant,measurement_tables,view');
        Route::get('/widget-install', [WidgetInstallController::class, 'show'])
            ->middleware('portal.permission:merchant,widget,view');
        Route::patch('/widget-install', [WidgetInstallController::class, 'update'])
            ->middleware('portal.permission:merchant,widget,edit');
        Route::get('/integrations', [IntegrationController::class, 'index'])
            ->middleware('portal.permission:merchant,integrations,view');
        Route::patch('/integrations/{platform}', [IntegrationController::class, 'update'])
            ->middleware('portal.permission:merchant,integrations,edit');
        Route::post('/integrations/{platform}/sync-xml', [IntegrationController::class, 'syncXml'])
            ->middleware('portal.permission:merchant,integrations,edit');
        Route::post('/integrations/{platform}/validate-install', [IntegrationController::class, 'validateInstall'])
            ->middleware('portal.permission:merchant,integrations,edit');
        Route::get('/integrations/bigshop/activations', [BigShopIntegrationController::class, 'activations'])
            ->middleware('portal.permission:merchant,integrations,view');
        Route::post('/integrations/bigshop/probe', [BigShopIntegrationController::class, 'probe'])
            ->middleware('portal.permission:merchant,integrations,edit');
        Route::post('/integrations/bigshop/sync', [BigShopIntegrationController::class, 'sync'])
            ->middleware('portal.permission:merchant,integrations,edit');
        Route::get('/imports', [ImportController::class, 'index'])
            ->middleware('portal.permission:merchant,imports,view');
        Route::post('/imports/preview', [ImportController::class, 'preview'])
            ->middleware('portal.permission:merchant,imports,edit');
        Route::post('/imports', [ImportController::class, 'store'])
            ->middleware('portal.permission:merchant,imports,edit');
        Route::get('/imports/{importJob}', [ImportController::class, 'show'])
            ->middleware('portal.permission:merchant,imports,view');
        Route::get('/ai/status', [AiMeasurementAssistantController::class, 'status'])
            ->middleware('portal.permission:merchant,ai_assistant,view');
        Route::post('/ai/measurement-table-suggestions', [AiMeasurementAssistantController::class, 'suggest'])
            ->middleware('portal.permission:merchant,ai_assistant,edit');
        Route::get('/analytics/recommendations', [AnalyticsController::class, 'recommendations'])
            ->middleware('portal.permission:merchant,analytics,view');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->middleware('portal.permission:merchant,analytics,view');
        Route::get('/go-live/readiness', GoLiveReadinessController::class)
            ->middleware('portal.permission:merchant,go_live,view');
        Route::get('/merchant/users', [UserAccessController::class, 'merchantIndex'])
            ->middleware('portal.permission:merchant,users,view');
        Route::post('/merchant/users', [UserAccessController::class, 'merchantStore'])
            ->middleware('portal.permission:merchant,users,edit');
        Route::patch('/merchant/users/{user}', [UserAccessController::class, 'merchantUpdate'])
            ->middleware('portal.permission:merchant,users,edit');
        Route::get('/saas/overview', [SaasAdminController::class, 'overview'])
            ->middleware('portal.permission:saas,saas_dashboard,view');
        Route::get('/saas/merchants', [SaasAdminController::class, 'merchants'])
            ->middleware('portal.permission:saas,saas_dashboard,view');
        Route::get('/saas/companies', [SaasAdminController::class, 'companies'])
            ->middleware('portal.permission:saas,saas_companies,view');
        Route::post('/saas/companies', [SaasAdminController::class, 'storeCompany'])
            ->middleware('portal.permission:saas,saas_companies,edit');
        Route::patch('/saas/companies/{company}', [SaasAdminController::class, 'updateCompany'])
            ->middleware('portal.permission:saas,saas_companies,edit');
        Route::get('/saas/users', [UserAccessController::class, 'saasIndex'])
            ->middleware('portal.permission:saas,saas_users,view');
        Route::post('/saas/users', [UserAccessController::class, 'saasStore'])
            ->middleware('portal.permission:saas,saas_users,edit');
        Route::patch('/saas/users/{user}', [UserAccessController::class, 'saasUpdate'])
            ->middleware('portal.permission:saas,saas_users,edit');
        Route::get('/saas/company-users', [UserAccessController::class, 'saasCompanyUsersIndex'])
            ->middleware('portal.permission:saas,saas_company_users,view');
        Route::post('/saas/company-users', [UserAccessController::class, 'saasCompanyUsersStore'])
            ->middleware('portal.permission:saas,saas_company_users,edit');
        Route::patch('/saas/company-users/{user}', [UserAccessController::class, 'saasCompanyUsersUpdate'])
            ->middleware('portal.permission:saas,saas_company_users,edit');
        Route::get('/saas/email-settings', [SaasEmailController::class, 'showSettings'])
            ->middleware('portal.permission:saas,saas_emails,view');
        Route::patch('/saas/email-settings', [SaasEmailController::class, 'updateSettings'])
            ->middleware('portal.permission:saas,saas_emails,edit');
        Route::get('/saas/checkout-settings', [SaasCheckoutController::class, 'show'])
            ->middleware('portal.permission:saas,saas_checkout,view');
        Route::patch('/saas/checkout-settings', [SaasCheckoutController::class, 'update'])
            ->middleware('portal.permission:saas,saas_checkout,edit');
        Route::get('/saas/transactional-emails', [SaasEmailController::class, 'templates'])
            ->middleware('portal.permission:saas,saas_emails,view');
        Route::post('/saas/transactional-emails', [SaasEmailController::class, 'storeTemplate'])
            ->middleware('portal.permission:saas,saas_emails,edit');
        Route::patch('/saas/transactional-emails/{transactionalEmail}', [SaasEmailController::class, 'updateTemplate'])
            ->middleware('portal.permission:saas,saas_emails,edit');
        Route::get('/saas/transactional-email-sends', [SaasEmailController::class, 'sendHistory'])
            ->middleware('portal.permission:saas,saas_emails,view');
        Route::apiResource('measurement-tables', MeasurementTableController::class)
            ->only(['index', 'show'])
            ->middleware('portal.permission:merchant,measurement_tables,view');
        Route::apiResource('measurement-tables', MeasurementTableController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('portal.permission:merchant,measurement_tables,edit');
        Route::apiResource('products', ProductController::class)
            ->only(['index', 'show'])
            ->middleware('portal.permission:merchant,products,view');
        Route::apiResource('products', ProductController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('portal.permission:merchant,products,edit');
        Route::apiResource('products.variants', ProductVariantController::class)
            ->only(['store', 'update', 'destroy'])
            ->middleware('portal.permission:merchant,products,edit');
        Route::post('/recommendations/config-check', [RecommendationController::class, 'configCheck']);
        Route::post('/recommendations', [RecommendationController::class, 'store']);
    });
});
