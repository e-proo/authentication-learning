<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('platform:about', function (): void {
    $this->info(config('app.name').' — modular logistics and commerce platform');
    $this->line('API: /api/v1');
})->purpose('Display platform information');
