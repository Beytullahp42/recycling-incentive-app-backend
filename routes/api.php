<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;

Route::get('/ping', function () {
    return response()->json(['message' => 'Pong']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/admin/login', [AuthController::class, 'adminLogin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/password', [AuthController::class, 'updatePassword']);
    Route::put('/email', [AuthController::class, 'updateEmail']);
    Route::delete('/account', [AuthController::class, 'deleteAccount']);

    Route::post('/profile', [\App\Http\Controllers\ProfileController::class, 'store']);
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update']);
    Route::get('/profile/me', [\App\Http\Controllers\ProfileController::class, 'me']);
    Route::get('/profile/{id}', [\App\Http\Controllers\ProfileController::class, 'show']);
    Route::get('/profile/username/{username}', [\App\Http\Controllers\ProfileController::class, 'showByUsername']);

    Route::post('/start-session', [\App\Http\Controllers\TransactionController::class, 'startSession']);

    Route::middleware('isAdmin')->group(function () {
        Route::get('/admin/ping', function () {
            return response()->json(['message' => 'Pong']);
        });
        Route::post('/admin/logout', [AuthController::class, 'adminLogout']);
        Route::put('/admin/profile/{username}', [\App\Http\Controllers\ProfileController::class, 'adminUpdate']);

        Route::post('/recyclable-items', [\App\Http\Controllers\RecyclableItemController::class, 'store']);
        Route::put('/recyclable-items/{id}', [\App\Http\Controllers\RecyclableItemController::class, 'update']);
        Route::delete('/recyclable-items/{id}', [\App\Http\Controllers\RecyclableItemController::class, 'destroy']);

        Route::post('/recyclable-item-categories', [\App\Http\Controllers\RecyclableItemCategoryController::class, 'store']);
        Route::put('/recyclable-item-categories/{id}', [\App\Http\Controllers\RecyclableItemCategoryController::class, 'update']);
        Route::delete('/recyclable-item-categories/{id}', [\App\Http\Controllers\RecyclableItemCategoryController::class, 'destroy']);

        Route::post('/recycling-bins', [\App\Http\Controllers\RecyclingBinController::class, 'store']);
        Route::put('/recycling-bins/{id}', [\App\Http\Controllers\RecyclingBinController::class, 'update']);
        Route::delete('/recycling-bins/{id}', [\App\Http\Controllers\RecyclingBinController::class, 'destroy']);
    });
});

Route::get('/recyclable-items', [\App\Http\Controllers\RecyclableItemController::class, 'index']);
Route::get('/recyclable-items/{id}', [\App\Http\Controllers\RecyclableItemController::class, 'show']);

Route::get('/recyclable-item-categories', [\App\Http\Controllers\RecyclableItemCategoryController::class, 'index']);
Route::get('/recyclable-item-categories/{id}', [\App\Http\Controllers\RecyclableItemCategoryController::class, 'show']);

Route::get('/recycling-bins', [\App\Http\Controllers\RecyclingBinController::class, 'index']);
Route::get('/recycling-bins/{id}', [\App\Http\Controllers\RecyclingBinController::class, 'show']);
