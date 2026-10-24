<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'message' => 'Logistics and Commerce Platform API',
    'health' => url('/api/v1/health'),
]));
