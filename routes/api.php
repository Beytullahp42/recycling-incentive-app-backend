<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecyclableItemController;
use App\Http\Controllers\RecyclableItemCategoryController;
use App\Http\Controllers\RecyclingBinController;
use App\Http\Controllers\RecyclingSessionController;
use App\Http\Controllers\LeaderboardController;


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

    Route::get('/leaderboard/current-season', [LeaderboardController::class, 'currentSeason']);
    Route::get('/leaderboard/all-time', [LeaderboardController::class, 'allTime']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/password', [AuthController::class, 'updatePassword']);
    Route::put('/email', [AuthController::class, 'updateEmail']);
    Route::delete('/account', [AuthController::class, 'deleteAccount']);

    Route::post('/profile', [ProfileController::class, 'store']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/profile/me', [ProfileController::class, 'me']);
    Route::get('/profile/{id}', [ProfileController::class, 'show']);
    Route::get('/profile/username/{username}', [ProfileController::class, 'showByUsername']);

    Route::post('/start-session', [TransactionController::class, 'startSession']);
    Route::post('/submit-item', [TransactionController::class, 'submitItem']);
    Route::post('/upload-proof', [TransactionController::class, 'uploadProof']);
    Route::post('/end-session', [TransactionController::class, 'endSession']);

    Route::middleware('isAdmin')->group(function () {
        Route::get('/admin/ping', function () {
            return response()->json(['message' => 'Pong']);
        });
        Route::post('/admin/logout', [AuthController::class, 'adminLogout']);
        Route::put('/admin/profile/{username}', [ProfileController::class, 'adminUpdate']);

        Route::get('/recyclable-items', [RecyclableItemController::class, 'index']);
        Route::get('/recyclable-items/{id}', [RecyclableItemController::class, 'show']);
        Route::post('/recyclable-items', [RecyclableItemController::class, 'store']);
        Route::put('/recyclable-items/{id}', [RecyclableItemController::class, 'update']);
        Route::delete('/recyclable-items/{id}', [RecyclableItemController::class, 'destroy']);

        Route::get('/recyclable-item-categories', [RecyclableItemCategoryController::class, 'index']);
        Route::get('/recyclable-item-categories/{id}', [RecyclableItemCategoryController::class, 'show']);
        Route::post('/recyclable-item-categories', [RecyclableItemCategoryController::class, 'store']);
        Route::put('/recyclable-item-categories/{id}', [RecyclableItemCategoryController::class, 'update']);
        Route::delete('/recyclable-item-categories/{id}', [RecyclableItemCategoryController::class, 'destroy']);

        Route::get('/recycling-bins', [RecyclingBinController::class, 'index']);
        Route::get('/recycling-bins/{id}', [RecyclingBinController::class, 'show']);
        Route::post('/recycling-bins', [RecyclingBinController::class, 'store']);
        Route::put('/recycling-bins/{id}', [RecyclingBinController::class, 'update']);
        Route::delete('/recycling-bins/{id}', [RecyclingBinController::class, 'destroy']);

        Route::get('/recycling-sessions', [RecyclingSessionController::class, 'index']);
        Route::get('/recycling-sessions/{id}', [RecyclingSessionController::class, 'show']);
        Route::put('/recycling-sessions/{id}', [RecyclingSessionController::class, 'setStatus']);
    });
});
