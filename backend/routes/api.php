<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public catalog
|--------------------------------------------------------------------------
*/

Route::get('categories', [CatalogController::class, 'categories']);
Route::get('brands', [CatalogController::class, 'brands']);
Route::get('products', [CatalogController::class, 'products']);
Route::get('products/{slug}', [CatalogController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Auth (Sanctum personal access tokens)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('verify-email', [AuthController::class, 'verify']);

    // Google OAuth
    Route::get('google/config', [AuthController::class, 'googleConfig']);
    Route::get('google/redirect', [AuthController::class, 'googleRedirect'])->middleware('throttle:auth');
    Route::get('google/callback', [AuthController::class, 'googleCallback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('resend-verification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:auth');
    });
});

/*
|--------------------------------------------------------------------------
| Checkout (test only — never charges a real card)
|--------------------------------------------------------------------------
*/

Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{reference}', [OrderController::class, 'show']);

    Route::get('favorites', [FavoriteController::class, 'index']);
    Route::post('favorites/{product}', [FavoriteController::class, 'store']);
    Route::delete('favorites/{product}', [FavoriteController::class, 'destroy']);
});
