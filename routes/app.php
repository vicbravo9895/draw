<?php

use App\Http\Controllers\App\CompanyController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\DefectTagController;
use App\Http\Controllers\App\InspectionController;
use App\Http\Controllers\App\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Backoffice Routes (/app)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:web', 'verified'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Companies (requires companies.view permission)
    Route::middleware('permission:companies.view')->group(function () {
        Route::resource('companies', CompanyController::class);
    });

    // Inspections
    Route::post('inspections/{inspection}/start', [InspectionController::class, 'start'])->name('inspections.start');
    Route::post('inspections/{inspection}/complete', [InspectionController::class, 'complete'])->name('inspections.complete');
    Route::get('inspections/{inspection}/export-pdf', [InspectionController::class, 'exportPdf'])->name('inspections.export-pdf');
    Route::get('inspections/{inspection}/export-csv', [InspectionController::class, 'exportCsv'])->name('inspections.export-csv');
    Route::get('inspections/{inspection}/capture', [InspectionController::class, 'capture'])->name('inspections.capture');
    Route::post('inspections/{inspection}/items', [InspectionController::class, 'storeItem'])->name('inspections.store-item');
    Route::put('inspections/{inspection}/items/{item}', [InspectionController::class, 'updateItem'])->name('inspections.update-item');
    Route::delete('inspections/{inspection}/items/{item}', [InspectionController::class, 'destroyItem'])->name('inspections.destroy-item');
    Route::resource('inspections', InspectionController::class);

    // Users (requires users.view permission)
    Route::middleware('permission:users.view')->group(function () {
        Route::resource('users', UserController::class);
    });

    // Defect Tags (requires defect_tags.manage permission)
    Route::middleware('permission:defect_tags.manage')->group(function () {
        Route::resource('defect-tags', DefectTagController::class)->except(['show']);
    });
});
