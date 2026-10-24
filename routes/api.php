<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FulfillmentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PriceListController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\WarehouseController;
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
            'pricing',
            'inventory',
            'orders',
            'fulfillment',
            'shipments',
            'finance',
            'marketing',
            'ai',
        ],
        'status' => 'order-to-delivery-foundation',
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

        Route::get('/organizations/{organization}/stores', [StoreController::class, 'index']);
        Route::post('/organizations/{organization}/stores', [StoreController::class, 'store']);

        Route::get('/organizations/{organization}/products', [ProductController::class, 'index']);
        Route::post('/organizations/{organization}/products', [ProductController::class, 'store']);
        Route::post('/organizations/{organization}/products/{product}/variants', [ProductController::class, 'storeVariant']);
        Route::post('/organizations/{organization}/products/{product}/publish', [ProductController::class, 'publish']);

        Route::get('/organizations/{organization}/price-lists', [PriceListController::class, 'index']);
        Route::post('/organizations/{organization}/price-lists', [PriceListController::class, 'store']);
        Route::post('/organizations/{organization}/price-lists/{priceList}/items', [PriceListController::class, 'upsertItem']);

        Route::get('/organizations/{organization}/warehouses', [WarehouseController::class, 'index']);
        Route::post('/organizations/{organization}/warehouses', [WarehouseController::class, 'store']);
        Route::post('/organizations/{organization}/inventory/adjustments', [InventoryController::class, 'adjust']);

        Route::get('/organizations/{organization}/orders', [OrderController::class, 'index']);
        Route::post('/organizations/{organization}/orders', [OrderController::class, 'store']);
        Route::get('/organizations/{organization}/orders/{order}', [OrderController::class, 'show']);
        Route::post('/organizations/{organization}/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('/organizations/{organization}/orders/{order}/fulfillments', [FulfillmentController::class, 'createForOrder']);

        Route::post('/organizations/{organization}/fulfillments/{fulfillment}/pack', [FulfillmentController::class, 'pack']);
        Route::post('/organizations/{organization}/fulfillments/{fulfillment}/shipments', [ShipmentController::class, 'store']);
        Route::get('/organizations/{organization}/shipments/{shipment}', [ShipmentController::class, 'show']);
        Route::post('/organizations/{organization}/shipments/{shipment}/events', [ShipmentController::class, 'updateStatus']);
    });
});
