<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('dashboard'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    foreach (['inventory', 'storage-areas', 'stock-movements', 'approval-requests', 'documents', 'reports'] as $module) {
        Route::get($module, ModuleController::class)->name($module);
    }
    Route::get('administration', ModuleController::class)
        ->middleware('can:access-administration')->name('administration');
});

require __DIR__.'/settings.php';
