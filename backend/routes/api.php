<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DemoProductController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MeasurementTableController;
use App\Http\Controllers\Api\V1\MeasurementTemplateController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Models\MeasurementTable;
use App\Models\Product;
use App\Models\RecommendationLog;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/demo/product-test', [DemoProductController::class, 'show']);
    Route::post('/public/recommendations/config-check', [RecommendationController::class, 'configCheck']);
    Route::post('/public/recommendations', [RecommendationController::class, 'store']);
    Route::post('/public/recommendations/{recommendationLog}/feedback', [RecommendationController::class, 'feedback']);

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
                    'recommendations_today' => RecommendationLog::query()
                        ->where('merchant_id', $merchant->id)
                        ->whereDate('created_at', now()->toDateString())
                        ->count(),
                ],
            ]);
        });
        Route::get('/measurement-templates', [MeasurementTemplateController::class, 'index']);
        Route::apiResource('measurement-tables', MeasurementTableController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('products.variants', ProductVariantController::class)
            ->only(['store', 'update', 'destroy']);
        Route::post('/recommendations/config-check', [RecommendationController::class, 'configCheck']);
        Route::post('/recommendations', [RecommendationController::class, 'store']);
    });
});
