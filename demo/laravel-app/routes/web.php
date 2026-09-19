<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use App\Http\Controllers\DemoImportController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('bulkflow')->withoutMiddleware([ValidateCsrfToken::class, PreventRequestForgery::class])->group(function (): void {
    Route::post('demo-imports/upload', [DemoImportController::class, 'upload']);
    Route::post('demo-imports', [DemoImportController::class, 'start']);
});
