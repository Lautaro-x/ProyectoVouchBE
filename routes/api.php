<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IgdbController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'googleLogin']);
});

Route::middleware(['auth:sanctum', 'not.banned'])->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/igdb/search', [IgdbController::class, 'search']);
        Route::post('/igdb/import', [IgdbController::class, 'import']);

        Route::apiResource('genres', Admin\GenreController::class)->except('show');
        Route::put('genres/{genre}/categories', [Admin\GenreController::class, 'syncCategories']);

        Route::apiResource('categories', Admin\CategoryController::class)->except('show');
        Route::apiResource('platforms', Admin\PlatformController::class)->except('show');
        Route::apiResource('products', Admin\ProductController::class)->except('show');

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
