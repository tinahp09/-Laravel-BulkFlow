<?php

use BulkFlow\Http\Controllers\ImportRunController;
use BulkFlow\Http\Controllers\ImportProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('bulkflow')->group(static function (): void {
    Route::get('import-profiles', [ImportProfileController::class, 'index']);
    Route::post('import-profiles/{profile}/uploads', [ImportProfileController::class, 'upload']);
    Route::post('import-profiles/{profile}/imports', [ImportProfileController::class, 'start']);
    Route::get('import-profiles/{profile}/mapping-templates', [ImportProfileController::class, 'templates']);
    Route::post('import-profiles/{profile}/mapping-templates', [ImportProfileController::class, 'storeTemplate']);
    Route::delete('import-profiles/{profile}/mapping-templates/{template}', [ImportProfileController::class, 'deleteTemplate']);
    Route::get('imports', [ImportRunController::class, 'index']);
    Route::get('imports/{run}', [ImportRunController::class, 'show']);
    Route::get('imports/{run}/failures/report', [ImportRunController::class, 'downloadFailureReport']);
    Route::get('imports/{run}/failures', [ImportRunController::class, 'failures']);
    Route::post('imports/{run}/cancel', [ImportRunController::class, 'cancel']);
    Route::post('imports/{run}/retry-failures', [ImportRunController::class, 'retryFailures']);
});
