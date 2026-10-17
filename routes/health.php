<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Deliberately outside the web stack: no session, authentication or cache access.
Route::get('/up', fn () => response()->json(['status' => 'ok'])->header('Cache-Control', 'no-store'))->name('health');
Route::get('/ready/database', function () {
    DB::connection()->select('select 1');

    return response()->json(['status' => 'ready'])->header('Cache-Control', 'no-store');
})->name('database.readiness');
