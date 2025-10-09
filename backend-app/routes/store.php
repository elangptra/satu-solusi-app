<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoreController;

Route::middleware('auth:api')->group(function () {
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/{id}', [StoreController::class, 'show']);
    Route::post('/stores/', [StoreController::class,'store'])->middleware('role:super_admin, merchant');;
    Route::post('/stores/{id}', [StoreController::class, 'update'])->middleware('role:super_admin, merchant');;
    Route::delete('/stores/{id}', [StoreController::class, 'destroy'])->middleware('role:super_admin, merchant');
});