<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IgdbController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\PublicCardController;
use App\Http\Controllers\UserConsentController;
use App\Http\Controllers\UserFollowerController;
use App\Http\Controllers\VerificationRequestController;
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
Route::get('/products/trailers', [ProductController::class, 'trailers']);
Route::get('/products/{id}/review-form', [ProductController::class, 'reviewForm']);
Route::get('/products/{product}/reviews', [ProductReviewController::class, 'index']);
Route::get('/products/{type}/{slug}', [ProductController::class, 'show']);

Route::middleware(['auth:sanctum', 'not.banned'])->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/user/profile', [UserProfileController::class, 'show']);
    Route::put('/user/profile', [UserProfileController::class, 'update'])->middleware('throttle:20,1');
    Route::get('/user/consents', [UserConsentController::class, 'show']);
    Route::patch('/user/consents', [UserConsentController::class, 'update'])->middleware('throttle:20,1');
    Route::get('/user/profile/card', [UserProfileController::class, 'cardData']);
    Route::get('/user/reviews/games', [UserReviewController::class, 'games']);
    Route::get('/user/followers', [UserFollowerController::class, 'index']);
    Route::get('/user/verify-request', [VerificationRequestController::class, 'show']);
    Route::post('/user/verify-request', [VerificationRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/user/badges', [BadgeController::class, 'progress']);
    Route::post('/user/badges/{badge}/claim', [BadgeController::class, 'claim'])->middleware('throttle:10,1');
    Route::delete('/user/badges/{badge}', [BadgeController::class, 'remove']);
    Route::get('/surveys/active', [SurveyController::class, 'active']);
    Route::post('/surveys/{survey}/respond', [SurveyController::class, 'respond'])->middleware('throttle:5,1');
    Route::get('/announcements/active', [AnnouncementController::class, 'active']);
    Route::post('/user/follow/{user}', [FollowController::class, 'follow'])->middleware('throttle:30,1');
    Route::delete('/user/follow/{user}', [FollowController::class, 'unfollow'])->middleware('throttle:30,1');
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/reviews/{review}/edit-form', [ReviewController::class, 'editForm']);
    Route::get('/reviews/{review}/share-data', [ReviewController::class, 'shareData']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->middleware('throttle:10,1');

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/igdb/search', [IgdbController::class, 'search']);
        Route::post('/igdb/import', [IgdbController::class, 'import']);
        Route::post('/igdb/import-recent', [IgdbController::class, 'importRecent']);
        Route::post('/products/{product}/sync-igdb', [IgdbController::class, 'syncProduct']);

        Route::apiResource('genres', Admin\GenreController::class)->except('show');
        Route::put('genres/{genre}/categories', [Admin\GenreController::class, 'syncCategories']);

        Route::apiResource('categories', Admin\CategoryController::class)->except('show');
        Route::apiResource('platforms', Admin\PlatformController::class)->except('show');
        Route::apiResource('products', Admin\ProductController::class)->except('show');
        Route::put('products/{product}/purchase-links', [Admin\ProductController::class, 'purchaseLinks']);

        Route::get('reviews', [Admin\ReviewController::class, 'index']);
        Route::post('reviews/{review}/ban', [Admin\ReviewController::class, 'ban'])->middleware('throttle:30,1');
        Route::delete('reviews/{review}/ban', [Admin\ReviewController::class, 'unban'])->middleware('throttle:30,1');

        Route::apiResource('surveys', Admin\SurveyController::class);
        Route::get('surveys/{survey}/results', [Admin\SurveyController::class, 'results']);
        Route::apiResource('announcements', Admin\AnnouncementController::class);

        Route::get('trailer-section', [Admin\CustomTrailerController::class, 'show']);
        Route::put('trailer-section', [Admin\CustomTrailerController::class, 'update'])->middleware('throttle:20,1');
        Route::post('trailer-section/items', [Admin\CustomTrailerController::class, 'storeItem'])->middleware('throttle:30,1');
        Route::delete('trailer-section/items/{customTrailerItem}', [Admin\CustomTrailerController::class, 'destroyItem'])->middleware('throttle:30,1');

        Route::get('verify-requests', [Admin\VerificationRequestController::class, 'index']);
        Route::post('verify-requests/{verificationRequest}/approve', [Admin\VerificationRequestController::class, 'approve'])->middleware('throttle:20,1');
        Route::post('verify-requests/{verificationRequest}/reject', [Admin\VerificationRequestController::class, 'reject'])->middleware('throttle:20,1');

        Route::get('users', [Admin\UserController::class, 'index']);
        Route::get('users/{user}', [Admin\UserController::class, 'show']);
        Route::post('users/{user}/ban', [Admin\UserController::class, 'ban'])->middleware('throttle:30,1');
        Route::delete('users/{user}/ban', [Admin\UserController::class, 'unban'])->middleware('throttle:30,1');
        Route::patch('users/{user}/role', [Admin\UserController::class, 'updateRole'])->middleware('throttle:10,1');
        Route::post('users/{user}/badge/verify', [Admin\UserController::class, 'grantVerified'])->middleware('throttle:10,1');
        Route::delete('users/{user}/badge/verify', [Admin\UserController::class, 'revokeVerified'])->middleware('throttle:10,1');
    });
});
