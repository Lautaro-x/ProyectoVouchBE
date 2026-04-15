<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IgdbController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'googleLogin']);
});

Route::get('/games', [ProductController::class, 'games']);
Route::get('/products/relevant', [ProductController::class, 'relevant']);
Route::get('/products/{id}/review-form', [ProductController::class, 'reviewForm']);
Route::get('/products/{type}/{slug}', [ProductController::class, 'show']);

Route::middleware(['auth:sanctum', 'not.banned'])->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/{review}/edit-form', [ReviewController::class, 'editForm']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/igdb/search', [IgdbController::class, 'search']);
        Route::post('/igdb/import', [IgdbController::class, 'import']);

        Route::apiResource('genres', Admin\GenreController::class)->except('show');
        Route::put('genres/{genre}/categories', [Admin\GenreController::class, 'syncCategories']);

        Route::apiResource('categories', Admin\CategoryController::class)->except('show');
        Route::apiResource('platforms', Admin\PlatformController::class)->except('show');
        Route::apiResource('products', Admin\ProductController::class)->except('show');
        Route::put('products/{product}/purchase-links', [Admin\ProductController::class, 'purchaseLinks']);

        Route::get('reviews', [Admin\ReviewController::class, 'index']);
        Route::post('reviews/{review}/ban', [Admin\ReviewController::class, 'ban']);
        Route::delete('reviews/{review}/ban', [Admin\ReviewController::class, 'unban']);

        Route::get('users', [Admin\UserController::class, 'index']);
        Route::get('users/{user}', [Admin\UserController::class, 'show']);
        Route::post('users/{user}/ban', [Admin\UserController::class, 'ban']);
        Route::delete('users/{user}/ban', [Admin\UserController::class, 'unban']);
        Route::patch('users/{user}/role', [Admin\UserController::class, 'updateRole']);
    });
});
