<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IgdbController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\UserReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'googleLogin'])->middleware('throttle:10,1');
});

Route::get('/games', [ProductController::class, 'games']);
Route::get('/public/card/{user}', [PublicCardController::class, 'show']);
Route::get('/products/relevant', [ProductController::class, 'relevant']);
Route::get('/products/{id}/review-form', [ProductController::class, 'reviewForm']);
Route::get('/products/{product}/reviews', [ProductReviewController::class, 'index']);
Route::get('/products/{type}/{slug}', [ProductController::class, 'show']);

Route::middleware(['auth:sanctum', 'not.banned'])->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/user/profile', [UserProfileController::class, 'show']);
    Route::put('/user/profile', [UserProfileController::class, 'update']);
    Route::get('/user/profile/card', [UserProfileController::class, 'cardData']);
    Route::get('/user/reviews/games', [UserReviewController::class, 'games']);
    Route::get('/user/badges', [BadgeController::class, 'progress']);
    Route::post('/user/badges/{badge}/claim', [BadgeController::class, 'claim']);
    Route::delete('/user/badges/{badge}', [BadgeController::class, 'remove']);
    Route::get('/surveys/active', [SurveyController::class, 'active']);
    Route::post('/surveys/{survey}/respond', [SurveyController::class, 'respond']);
    Route::get('/announcements/active', [AnnouncementController::class, 'active']);
    Route::post('/user/follow/{user}', [FollowController::class, 'follow']);
    Route::delete('/user/follow/{user}', [FollowController::class, 'unfollow']);
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

        Route::apiResource('surveys', Admin\SurveyController::class);
        Route::get('surveys/{survey}/results', [Admin\SurveyController::class, 'results']);
        Route::apiResource('announcements', Admin\AnnouncementController::class);

        Route::get('users', [Admin\UserController::class, 'index']);
        Route::get('users/{user}', [Admin\UserController::class, 'show']);
        Route::post('users/{user}/ban', [Admin\UserController::class, 'ban']);
        Route::delete('users/{user}/ban', [Admin\UserController::class, 'unban']);
        Route::patch('users/{user}/role', [Admin\UserController::class, 'updateRole']);
        Route::post('users/{user}/badge/verify', [Admin\UserController::class, 'grantVerified']);
        Route::delete('users/{user}/badge/verify', [Admin\UserController::class, 'revokeVerified']);
    });
});
