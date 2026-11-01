<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

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

        return TripResource::collection($trips)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Create a trip for the authenticated user.
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        Gate::authorize('create', Trip::class);

        $trip = $request->user()
            ->trips()
            ->create($request->validated());

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a trip.
     */
    public function show(Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Update a trip.
     */
    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        $trip->update($request->validated());

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
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
