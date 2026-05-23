<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BigShopActivationController;
use App\Http\Controllers\Api\V1\BigShopIntegrationController;
use App\Http\Controllers\Api\V1\DemoProductController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\MeasurementTableController;
use App\Http\Controllers\Api\V1\MeasurementTemplateController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\WidgetInstallController;
use App\Models\MeasurementTable;
use App\Models\PlatformConnection;
use App\Models\Product;
use App\Models\RecommendationLog;
use App\Models\WidgetInstall;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/demo/product-test', [DemoProductController::class, 'show']);
    Route::post('/public/recommendations/config-check', [RecommendationController::class, 'configCheck']);
    Route::post('/public/recommendations', [RecommendationController::class, 'store']);
    Route::post('/public/recommendations/{recommendationLog}/feedback', [RecommendationController::class, 'feedback']);
    Route::post('/public/bigshop/activate', BigShopActivationController::class);

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/merchant/overview', function () {
            $merchant = request()->user()->merchants()->firstOrFail();

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
        Route::apiResource('measurement-tables', MeasurementTableController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('products.variants', ProductVariantController::class)
            ->only(['store', 'update', 'destroy']);
        Route::post('/recommendations/config-check', [RecommendationController::class, 'configCheck']);
        Route::post('/recommendations', [RecommendationController::class, 'store']);
    });
});
