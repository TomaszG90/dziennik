<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\FriendRequestController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LegController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripInvitationController;
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
    Route::prefix('public')->controller(PublicProfileController::class)->group(function () {
        Route::get('/users', 'index');
        Route::get('/users/{user}', 'show');
        Route::get('/users/{user}/trips', 'trips');
        Route::get('/users/{user}/map', 'map');
        Route::get('/users/{user}/stats', 'stats');
    });

    Route::get('/geocode', GeocodeController::class)->middleware('throttle:geocode');

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:6,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [ProfileController::class, 'show']);
        Route::patch('/me', [ProfileController::class, 'update']);

        Route::get('/stats', [StatsController::class, 'index']);
        Route::get('/map', MapController::class);

        Route::get('/trips/years', [TripController::class, 'years']);
        Route::apiResource('trips', TripController::class);

        // Places
        Route::get('/trips/{trip}/places', [PlaceController::class, 'index']);
        Route::post('/trips/{trip}/places', [PlaceController::class, 'store']);
        Route::patch('/trips/{trip}/places/reorder', [PlaceController::class, 'reorder']);
        Route::get('/trips/{trip}/places/{place}', [PlaceController::class, 'show']);
        Route::patch('/trips/{trip}/places/{place}', [PlaceController::class, 'update']);
        Route::delete('/trips/{trip}/places/{place}', [PlaceController::class, 'destroy']);

        // Legs
        Route::get('/trips/{trip}/legs', [LegController::class, 'index']);
        Route::post('/trips/{trip}/legs', [LegController::class, 'store']);
        Route::get('/trips/{trip}/legs/{leg}', [LegController::class, 'show']);
        Route::patch('/trips/{trip}/legs/{leg}', [LegController::class, 'update']);
        Route::delete('/trips/{trip}/legs/{leg}', [LegController::class, 'destroy']);

        // Friends
        Route::get('/friends', [FriendController::class, 'index']);
        Route::delete('/friends/{user}', [FriendController::class, 'destroy']);

        // Friend Requests
        Route::get('/friend-requests', [FriendRequestController::class, 'index']);
        Route::post('/friend-requests', [FriendRequestController::class, 'store']);
        Route::patch('/friend-requests/{friendRequest}', [FriendRequestController::class, 'update']);
        Route::delete('/friend-requests/{friendRequest}', [FriendRequestController::class, 'destroy']);

        // Invite by email
        Route::post('/invite', [InviteController::class, 'store']);

        // Trip Invitations
        Route::get('/trip-invitations', [TripInvitationController::class, 'myInvitations']);
        Route::get('/trips/{trip}/invitations', [TripInvitationController::class, 'index']);
        Route::post('/trips/{trip}/invitations', [TripInvitationController::class, 'store']);
        Route::patch('/trips/{trip}/invitations/{invitation}', [TripInvitationController::class, 'update']);
        Route::delete('/trips/{trip}/invitations/{invitation}', [TripInvitationController::class, 'destroy']);

        // Copy trip
        Route::post('/trips/{trip}/copy', [TripController::class, 'copy']);
    });
});
