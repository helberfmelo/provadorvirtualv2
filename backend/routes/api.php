<?php

use App\Http\Controllers\Api\V1\AiMeasurementAssistantController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BigShopActivationController;
use App\Http\Controllers\Api\V1\BigShopIntegrationController;
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
use App\Http\Controllers\Api\V1\SaasEmailController;
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
    Route::post('/public/recommendations/config-check', [RecommendationController::class, 'configCheck'])
        ->middleware(['widget.origin', 'throttle:60,1']);
    Route::post('/public/recommendations', [RecommendationController::class, 'store'])
        ->middleware(['widget.origin', 'throttle:60,1']);
    Route::post('/public/recommendations/{recommendationLog}/feedback', [RecommendationController::class, 'feedback'])
        ->middleware(['widget.origin', 'throttle:120,1']);
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
    Route::post('/public/bigshop/activate', BigShopActivationController::class)
        ->middleware('throttle:20,1');

    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/merchant/overview', function (Request $request) {
            $merchant = app(ActiveTenant::class)->merchant($request);

            return response()->json([
                'summary' => [
                    'products' => Product::query()->where('merchant_id', $merchant->id)->count(),
                    'measurement_tables' => MeasurementTable::query()->where('merchant_id', $merchant->id)->count(),
                    'widget_status' => 'demo-ready',
                    'widget_active' => WidgetInstall::query()
                        ->where('merchant_id', $merchant->id)
                        ->where('is_active', true)
                        ->exists(),
                    'integrations_configured' => PlatformConnection::query()
                        ->where('merchant_id', $merchant->id)
                        ->whereIn('status', ['configured', 'connected'])
                        ->count(),
                    'recommendations_today' => RecommendationLog::query()
                        ->where('merchant_id', $merchant->id)
                        ->whereDate('created_at', now()->toDateString())
                        ->count(),
                ],
            ]);
        });
        Route::get('/measurement-templates', [MeasurementTemplateController::class, 'index']);
        Route::get('/widget-install', [WidgetInstallController::class, 'show']);
        Route::patch('/widget-install', [WidgetInstallController::class, 'update']);
        Route::get('/integrations', [IntegrationController::class, 'index']);
        Route::patch('/integrations/{platform}', [IntegrationController::class, 'update']);
        Route::post('/integrations/bigshop/probe', [BigShopIntegrationController::class, 'probe']);
        Route::post('/integrations/bigshop/sync', [BigShopIntegrationController::class, 'sync']);
        Route::get('/imports', [ImportController::class, 'index']);
        Route::post('/imports/preview', [ImportController::class, 'preview']);
        Route::post('/imports', [ImportController::class, 'store']);
        Route::get('/imports/{importJob}', [ImportController::class, 'show']);
        Route::get('/ai/status', [AiMeasurementAssistantController::class, 'status']);
        Route::post('/ai/measurement-table-suggestions', [AiMeasurementAssistantController::class, 'suggest']);
        Route::get('/analytics/recommendations', [AnalyticsController::class, 'recommendations']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/go-live/readiness', GoLiveReadinessController::class);
        Route::get('/saas/overview', [SaasAdminController::class, 'overview']);
        Route::get('/saas/merchants', [SaasAdminController::class, 'merchants']);
        Route::get('/saas/companies', [SaasAdminController::class, 'companies']);
        Route::post('/saas/companies', [SaasAdminController::class, 'storeCompany']);
        Route::patch('/saas/companies/{company}', [SaasAdminController::class, 'updateCompany']);
        Route::get('/saas/email-settings', [SaasEmailController::class, 'showSettings']);
        Route::patch('/saas/email-settings', [SaasEmailController::class, 'updateSettings']);
        Route::get('/saas/transactional-emails', [SaasEmailController::class, 'templates']);
        Route::post('/saas/transactional-emails', [SaasEmailController::class, 'storeTemplate']);
        Route::patch('/saas/transactional-emails/{transactionalEmail}', [SaasEmailController::class, 'updateTemplate']);
        Route::apiResource('measurement-tables', MeasurementTableController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('products.variants', ProductVariantController::class)
            ->only(['store', 'update', 'destroy']);
        Route::post('/recommendations/config-check', [RecommendationController::class, 'configCheck']);
        Route::post('/recommendations', [RecommendationController::class, 'store']);
    });
});
