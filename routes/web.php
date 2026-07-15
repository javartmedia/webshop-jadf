<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\ProductController as FrontendProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Frontend Routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [FrontendProductController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [FrontendProductController::class, 'show'])->name('products.show');
Route::get('/categories/{slug}', [FrontendProductController::class, 'byCategory'])->name('categories.show');
Route::get('/brands/{slug}', [FrontendProductController::class, 'byBrand'])->name('brands.show');
Route::get('/flash-sale', [FrontendProductController::class, 'flashSale'])->name('flash-sale');
Route::get('/search', [FrontendProductController::class, 'search'])->name('search');
Route::get('/pages/{slug}', [HomeController::class, 'page'])->name('pages.show');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'sendContact']);
Route::post('/newsletter/subscribe', [HomeController::class, 'newsletterSubscribe'])->name('newsletter.subscribe');

// Admin Routes
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Products
    Route::resource('products', AdminProductController::class);
    Route::post('/products/bulk-action', [AdminProductController::class, 'bulkAction'])->name('products.bulk-action');

    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('/orders/{order}/payment', [AdminOrderController::class, 'updatePaymentStatus'])->name('orders.update-payment');

    // Categories
    Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class);

    // Brands
    Route::resource('brands', \App\Http\Controllers\Admin\BrandController::class);

    // Series
    Route::resource('series', \App\Http\Controllers\Admin\SeriesController::class);

    // Vouchers
    Route::resource('vouchers', \App\Http\Controllers\Admin\VoucherController::class);

    // Flash Sales
    Route::resource('flash-sales', \App\Http\Controllers\Admin\FlashSaleController::class);

    // Users
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/status', [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])->name('users.toggle-status');

    // Reviews
    Route::get('/reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/approve', [\App\Http\Controllers\Admin\ReviewController::class, 'approve'])->name('reviews.approve');
    Route::post('/reviews/{review}/reply', [\App\Http\Controllers\Admin\ReviewController::class, 'reply'])->name('reviews.reply');

    // CMS
    Route::resource('sliders', \App\Http\Controllers\Admin\SliderController::class);
    Route::resource('pages', \App\Http\Controllers\Admin\PageController::class);

    // Settings
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');

    // Reports
    Route::get('/reports/sales', [\App\Http\Controllers\Admin\ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/products', [\App\Http\Controllers\Admin\ReportController::class, 'products'])->name('reports.products');
    Route::get('/reports/customers', [\App\Http\Controllers\Admin\ReportController::class, 'customers'])->name('reports.customers');
});
