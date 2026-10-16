<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ItemImageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('inventory', [CatalogController::class, 'index'])->name('inventory');
    Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
    Route::middleware('can:access-administration')->group(function () {
        Route::get('catalog/{kind}/create', [CatalogController::class, 'form'])->whereIn('kind', ['items', 'categories', 'assets']);
        Route::get('catalog/{kind}/{record}/edit', [CatalogController::class, 'form'])->whereIn('kind', ['items', 'categories', 'assets'])->whereNumber('record');
        Route::post('catalog/{kind}', [CatalogController::class, 'save'])->whereIn('kind', ['items', 'categories', 'assets']);
        Route::put('catalog/{kind}/{record}', [CatalogController::class, 'save'])->whereIn('kind', ['items', 'categories', 'assets'])->whereNumber('record');
        Route::put('inventory/{item}/threshold', [CatalogController::class, 'threshold']);
        Route::post('catalog/{kind}/{record}/images', [ItemImageController::class, 'store'])->whereIn('kind', ['items', 'assets'])->whereNumber('record');
        Route::patch('item-images/{image}', [ItemImageController::class, 'update']);
        Route::delete('item-images/{image}', [ItemImageController::class, 'destroy']);
    });
    Route::get('inventory/{item}', [CatalogController::class, 'show'])->name('items.show');
    Route::get('assets/{asset}', [CatalogController::class, 'asset'])->name('assets.show');
    Route::get('storage-areas/{storageArea}/inventory', [CatalogController::class, 'area']);
    Route::get('item-images/{image}', [ItemImageController::class, 'show'])->name('item-images.show');
});
