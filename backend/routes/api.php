<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TripController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);
        Route::apiResource('trips', TripController::class);
        Route::get('/trips/{trip}/places', [PlaceController::class, 'index']);
        Route::post('/trips/{trip}/places', [PlaceController::class, 'store']);

        Route::patch('/trips/{trip}/places/reorder', [PlaceController::class, 'reorder']);

        Route::get('/trips/{trip}/places/{place}', [PlaceController::class, 'show']);
        Route::patch('/trips/{trip}/places/{place}', [PlaceController::class, 'update']);
        Route::delete('/trips/{trip}/places/{place}', [PlaceController::class, 'destroy']);
    });
});
