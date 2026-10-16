<?php

use App\Http\Controllers\StockRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('stock-requests/items', [StockRequestController::class, 'items'])->name('stock-requests.items');
    Route::resource('stock-requests', StockRequestController::class)->except('destroy');
    Route::post('stock-requests/{stockRequest}/submit', [StockRequestController::class, 'submit'])->name('stock-requests.submit');
    Route::post('stock-requests/{stockRequest}/approve', [StockRequestController::class, 'approve'])->name('stock-requests.approve');
    Route::post('stock-requests/{stockRequest}/reject', [StockRequestController::class, 'reject'])->name('stock-requests.reject');
    Route::post('stock-requests/{stockRequest}/cancel', [StockRequestController::class, 'cancel'])->name('stock-requests.cancel');
    Route::post('stock-requests/{stockRequest}/execute', [StockRequestController::class, 'execute'])->name('stock-requests.execute');
    Route::get('approval-requests', [StockRequestController::class, 'index'])->middleware('can:access-administration')->name('approval-requests');
    Route::get('stock-movements', [StockRequestController::class, 'movements'])->name('stock-movements');
});
