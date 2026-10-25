<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\DeliveryTaskController;
use App\Http\Controllers\Api\V1\DriverAppController;
use App\Http\Controllers\Api\V1\FleetController;
use App\Http\Controllers\Api\V1\TransportTripController;
use App\Http\Controllers\Api\V1\FulfillmentController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReturnController;
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
            'identity', 'organizations', 'catalog', 'pricing', 'inventory',
            'orders', 'fulfillment', 'shipments', 'returns', 'fleet', 'transport',
            'delivery_tasks', 'finance', 'marketing', 'ai',
        ],
        'status' => 'order-to-delivery-foundation',
    ]));

    Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Driver Companion endpoints are scoped to active driver profiles linked
        // to the authenticated user. They do not require fleet-admin permissions.
        Route::prefix('driver')->middleware('throttle:120,1')->group(function (): void {
            Route::get('/me', [DriverAppController::class, 'me']);
            Route::get('/vehicles', [DriverAppController::class, 'vehicles']);
            Route::get('/tasks/summary', [DriverAppController::class, 'taskSummary']);
            Route::get('/tasks', [DriverAppController::class, 'tasks']);
            Route::get('/tasks/{task}', [DriverAppController::class, 'showTask']);
            Route::get('/tasks/{task}/events', [DriverAppController::class, 'taskEvents']);
            Route::post('/tasks/{task}/evidence', [DriverAppController::class, 'uploadTaskEvidence']);
            Route::post('/tasks/{task}/events', [DriverAppController::class, 'updateTask']);
            Route::get('/shifts/current', [DriverAppController::class, 'currentShift']);
            Route::post('/shifts/start', [DriverAppController::class, 'startShift']);
            Route::post('/shifts/{shift}/end', [DriverAppController::class, 'endShift']);
        });

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

        Route::get('/organizations/{organization}/invoices', [InvoiceController::class, 'index']);
        Route::post('/organizations/{organization}/orders/{order}/invoice', [InvoiceController::class, 'issueFromOrder']);
        Route::get('/organizations/{organization}/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('/organizations/{organization}/payments', [PaymentController::class, 'index']);
        Route::post('/organizations/{organization}/payments', [PaymentController::class, 'store']);
        Route::post('/organizations/{organization}/payments/{payment}/confirm', [PaymentController::class, 'confirm']);

        Route::get('/organizations/{organization}/returns', [ReturnController::class, 'index']);
        Route::post('/organizations/{organization}/orders/{order}/returns', [ReturnController::class, 'requestForOrder']);
        Route::get('/organizations/{organization}/returns/{returnCase}', [ReturnController::class, 'show']);
        Route::post('/organizations/{organization}/returns/{returnCase}/approve', [ReturnController::class, 'approve']);
        Route::post('/organizations/{organization}/returns/{returnCase}/reject', [ReturnController::class, 'reject']);
        Route::post('/organizations/{organization}/returns/{returnCase}/items/{returnItem}/receive', [ReturnController::class, 'receiveItem']);
        Route::post('/organizations/{organization}/returns/{returnCase}/close', [ReturnController::class, 'close']);

        Route::get('/organizations/{organization}/drivers', [FleetController::class, 'drivers']);
        Route::post('/organizations/{organization}/drivers', [FleetController::class, 'storeDriver']);
        Route::get('/organizations/{organization}/vehicles', [FleetController::class, 'vehicles']);
        Route::post('/organizations/{organization}/vehicles', [FleetController::class, 'storeVehicle']);

        Route::get('/organizations/{organization}/transport-trips', [TransportTripController::class, 'index']);
        Route::post('/organizations/{organization}/transport-trips', [TransportTripController::class, 'store']);
        Route::get('/organizations/{organization}/transport-trips/{trip}', [TransportTripController::class, 'show']);
        Route::post('/organizations/{organization}/transport-trips/{trip}/assign', [TransportTripController::class, 'assign']);
        Route::post('/organizations/{organization}/transport-trips/{trip}/depart', [TransportTripController::class, 'depart']);
        Route::post('/organizations/{organization}/transport-trips/{trip}/complete', [TransportTripController::class, 'complete']);
        Route::post('/organizations/{organization}/transport-trips/{trip}/cancel', [TransportTripController::class, 'cancel']);

        Route::get('/organizations/{organization}/delivery-tasks', [DeliveryTaskController::class, 'index']);
        Route::post('/organizations/{organization}/delivery-tasks', [DeliveryTaskController::class, 'store']);
        Route::get('/organizations/{organization}/delivery-tasks/{task}', [DeliveryTaskController::class, 'show']);
        Route::post('/organizations/{organization}/delivery-tasks/{task}/assign', [DeliveryTaskController::class, 'assign']);
        Route::post('/organizations/{organization}/delivery-tasks/{task}/events', [DeliveryTaskController::class, 'updateStatus']);

        Route::get('/organizations/{organization}/campaigns', [CampaignController::class, 'index']);
        Route::post('/organizations/{organization}/campaigns', [CampaignController::class, 'store']);
        Route::get('/organizations/{organization}/campaigns/{campaign}', [CampaignController::class, 'show']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/channels', [CampaignController::class, 'createChannel']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/creatives', [CampaignController::class, 'createCreative']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/submit', [CampaignController::class, 'submitForReview']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/approve', [CampaignController::class, 'approveCampaign']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/creatives/{creative}/submit', [CampaignController::class, 'submitCreativeForReview']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/creatives/{creative}/approve', [CampaignController::class, 'approveCreative']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/channels/{channel}/publish', [CampaignController::class, 'publishChannel']);
        Route::post('/organizations/{organization}/campaigns/{campaign}/channels/{channel}/metrics', [CampaignController::class, 'recordMetrics']);
        Route::get('/organizations/{organization}/campaigns/{campaign}/metrics', [CampaignController::class, 'metrics']);
    });
});
