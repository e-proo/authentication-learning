<?php

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
});
