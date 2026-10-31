<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TripController extends Controller
{
    /**
     * List trips belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Trip::class);

        $trips = $request->user()
            ->trips()
            ->orderBy('start_date')
            ->get();

        return response()->json($trips, Response::HTTP_OK);
    }

    /**
     * Create a trip for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Trip::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
        ]);

        $trip = $request->user()->trips()->create($validated);

        return response()->json($trip, Response::HTTP_CREATED);
    }

    /**
     * Show a trip.
     */
    public function show(Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);

        return response()->json($trip, Response::HTTP_OK);
    }

    /**
     * Update a trip.
     */
    public function update(Request $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $trip->update($validated);

        return response()->json($trip->fresh(), Response::HTTP_OK);
    }

    /**
     * Delete a trip.
     */
    public function destroy(Trip $trip): JsonResponse
    {
        Gate::authorize('delete', $trip);

        $trip->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
