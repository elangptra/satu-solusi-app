<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;

Route::middleware('auth:api')->group(function () {
    Route::get('/carts', [CartController::class, 'index']);
    Route::post('/carts/add-item', [CartController::class, 'addItem']);
    Route::put('/carts/update-item/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/carts/remove-item/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/carts/clear/{cartId}', [CartController::class, 'clearCart']);
});