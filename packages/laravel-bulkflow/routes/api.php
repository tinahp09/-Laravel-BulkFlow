<?php

use BulkFlow\Http\Controllers\ImportRunController;
use Illuminate\Support\Facades\Route;

Route::prefix('bulkflow')->group(static function (): void {
    Route::get('imports', [ImportRunController::class, 'index']);
    Route::get('imports/{run}', [ImportRunController::class, 'show']);
    Route::get('imports/{run}/failures/report', [ImportRunController::class, 'downloadFailureReport']);
    Route::get('imports/{run}/failures', [ImportRunController::class, 'failures']);
    Route::post('imports/{run}/cancel', [ImportRunController::class, 'cancel']);
    Route::post('imports/{run}/retry-failures', [ImportRunController::class, 'retryFailures']);
});
