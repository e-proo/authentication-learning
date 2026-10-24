<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'api_version' => 'v1',
    ]));

    Route::get('/about', fn () => response()->json([
        'name' => config('app.name'),
        'modules' => [
            'identity',
            'organizations',
            'catalog',
            'inventory',
            'orders',
            'logistics',
            'finance',
            'marketing',
            'ai',
        ],
        'status' => 'foundation',
    ]));

    Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::post('/organizations', [OrganizationController::class, 'store']);
        Route::get('/organizations/{organization}/products', [ProductController::class, 'index']);
        Route::post('/organizations/{organization}/products', [ProductController::class, 'store']);
    });
});
