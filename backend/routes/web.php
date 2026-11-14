<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)
    ->withoutMiddleware('web');

Route::get('/metrics', MetricsController::class)
    ->withoutMiddleware('web');

Route::get('/', function () {
    return view('welcome');
});
