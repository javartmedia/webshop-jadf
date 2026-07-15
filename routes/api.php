<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Products (public)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/flash-sale', [ProductController::class, 'flashSaleProducts']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // Categories, Brands, Series
    Route::get('/categories', [ProductController::class, 'categories']);
    Route::get('/brands', [ProductController::class, 'brands']);
    Route::get('/series', [ProductController::class, 'series']);

    // Reviews (public read)
    Route::get('/products/{slug}/reviews', [ReviewController::class, 'productReviews']);

    // Midtrans callback (public)
    Route::post('/midtrans/callback', [OrderController::class, 'midtransCallback']);

    // Shipping (public for guest checkout)
    Route::get('/provinces', [OrderController::class, 'getProvinces']);
    Route::get('/cities', [OrderController::class, 'getCities']);

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {

        // User
        Route::get('/user', [AuthController::class, 'profile']);
        Route::put('/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/user/password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Cart
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::put('/cart/items/{item}', [CartController::class, 'updateItem']);
        Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);
        Route::delete('/cart', [CartController::class, 'clear']);

        // Orders
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::post('/shipping/cost', [OrderController::class, 'calculateShippingCost']);
        Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{orderNumber}/confirm-delivery', [OrderController::class, 'confirmDelivery']);

        // Wishlist
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
        Route::post('/wishlist/check', [WishlistController::class, 'check']);
        Route::delete('/wishlist/{productId}', [WishlistController::class, 'remove']);

        // Reviews
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/my-reviews', [ReviewController::class, 'myReviews']);
    });
});
