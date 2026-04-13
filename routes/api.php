<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\IgdbController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/google', [AuthController::class, 'googleLogin']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/igdb/search', [IgdbController::class, 'search']);
        Route::post('/igdb/import', [IgdbController::class, 'import']);
    });
});
