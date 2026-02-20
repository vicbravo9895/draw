<?php

use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\InspectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal Empresa Routes (/portal)
|--------------------------------------------------------------------------
*/

// Guest routes (no auth required)
Route::middleware('guest:portal')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('magic-link', [AuthController::class, 'sendMagicLink'])->name('magic-link.send');
    Route::get('magic-link/sent', [AuthController::class, 'magicLinkSent'])->name('magic-link.sent');
    Route::get('magic-link/verify/{viewer}', [AuthController::class, 'verifyMagicLink'])
        ->name('magic-link.verify')
        ->middleware('signed');
});

// Authenticated portal routes (read-only)
Route::middleware(['auth:portal', 'ensure_company_access'])->group(function () {
    Route::get('/', fn () => redirect()->route('portal.dashboard'));

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('inspections', [InspectionController::class, 'index'])->name('inspections.index');
    Route::get('inspections/{inspection}', [InspectionController::class, 'show'])->name('inspections.show');

    Route::get('inspections/{inspection}/export-pdf', [InspectionController::class, 'exportPdf'])->name('inspections.export-pdf');
    Route::get('inspections/{inspection}/export-csv', [InspectionController::class, 'exportCsv'])->name('inspections.export-csv');

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});
