<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DemoProductController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::get('/demo/product-test', [DemoProductController::class, 'show']);

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/merchant/overview', function () {
            return response()->json([
                'summary' => [
                    'products' => 1,
                    'measurement_tables' => 1,
                    'widget_status' => 'demo-ready',
                    'recommendations_today' => 0,
                ],
            ]);
        });
    });
});
