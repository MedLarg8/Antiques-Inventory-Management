<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\MyStorageAreaController;
use App\Http\Controllers\StorageAreaController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('dashboard'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    foreach (['stock-movements', 'approval-requests', 'documents', 'reports'] as $module) {
        Route::get($module, ModuleController::class)->name($module);
    }
    Route::get('administration', ModuleController::class)
        ->middleware('can:access-administration')->name('administration');

    Route::middleware('can:access-administration')->group(function () {
        Route::resource('cities', CityController::class)->except(['show', 'destroy']);
        Route::resource('storage-areas', StorageAreaController::class)->except(['show', 'destroy']);
        Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
        Route::put('storage-areas/{storage_area}/assignment', [AssignmentController::class, 'update'])->name('assignments.update');
        Route::resource('users', EmployeeController::class)->except(['destroy']);
        Route::put('users/{user}/role', [EmployeeController::class, 'role'])->name('users.role');
        Route::put('users/{user}/status', [EmployeeController::class, 'status'])->name('users.status');
        Route::post('users/{user}/setup-link', [EmployeeController::class, 'setupLink'])->middleware('throttle:6,1')->name('users.setup-link');
        Route::post('users/{user}/invite', [EmployeeController::class, 'invite'])->middleware('throttle:6,1')->name('users.invite');
    });
    Route::get('storage-areas/{storage_area}', [StorageAreaController::class, 'show'])->name('storage-areas.show');
    Route::get('my-storage-area', MyStorageAreaController::class)->middleware('can:view-own-storage')->name('my-storage-area');
});

require __DIR__.'/settings.php';
require __DIR__.'/inventory.php';
