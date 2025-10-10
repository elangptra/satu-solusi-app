<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

Route::middleware('auth:api')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/products/store/{storeId}', [ProductController::class, 'getByStoreId']);
    Route::post('/products', [ProductController::class, 'store'])->middleware('role:merchant');
    Route::post('/products/{id}', [ProductController::class, 'update'])->middleware('role:merchant');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('role:merchant');
});
