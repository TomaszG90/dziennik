<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\TripInvitation;
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

        $user = $request->user();

        // Pobierz własne wycieczki
        $ownTrips = $user->trips()->orderBy('start_date')->get();

        // Pobierz wycieczki gdzie użytkownik jest gościem (zaakceptowane zaproszenia)
        $guestTrips = TripInvitation::where('invitee_id', $user->id)
            ->where('status', 'accepted')
            ->with('trip')
            ->get()
            ->pluck('trip')
            ->filter()
            ->unique('id')
            ->values();

        // Połącz i posortuj
        $allTrips = $ownTrips->merge($guestTrips)
            ->unique('id')
            ->sortBy('start_date')
            ->values();

        // Oznacz wycieczki gdzie użytkownik jest gościem
        $guestTripIds = $guestTrips->pluck('id')->toArray();

        return response()->json([
            'data' => $allTrips->map(function ($trip) use ($guestTripIds) {
                $data = (new TripResource($trip))->toArray(request());
                $data['is_guest'] = in_array($trip->id, $guestTripIds);

                return $data;
            }),
        ], Response::HTTP_OK);
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
    public function show(Request $request, Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);

        $user = $request->user();
        $isGuest = $user->id !== $trip->user_id;

        $data = (new TripResource($trip))->toArray($request);
        $data['is_guest'] = $isGuest;

        return response()->json(['data' => $data], Response::HTTP_OK);
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

    /**
     * Copy a trip to the authenticated user's trips.
     */
    public function copy(Request $request, Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);

        $user = $request->user();

        // Skopiuj wycieczkę
        $newTrip = $user->trips()->create([
            'name' => $trip->name.' (kopia)',
            'start_date' => $trip->start_date,
            'end_date' => $trip->end_date,
            'description' => $trip->description,
        ]);

        // Skopiuj punkty
        $places = $trip->places()->orderBy('position')->get();
        $placeIdMap = [];

        foreach ($places as $place) {
            $newPlace = $newTrip->places()->create([
                'name' => $place->name,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'position' => $place->position,
                'country_code' => $place->country_code,
            ]);
            $placeIdMap[$place->id] = $newPlace->id;
        }

        // Skopiuj odcinki
        $legs = $trip->legs()->orderBy('position')->get();

        foreach ($legs as $leg) {
            $newFromId = $placeIdMap[$leg->from_place_id] ?? null;
            $newToId = $placeIdMap[$leg->to_place_id] ?? null;

            if ($newFromId && $newToId) {
                $newTrip->legs()->create([
                    'from_place_id' => $newFromId,
                    'to_place_id' => $newToId,
                    'transport' => $leg->transport,
                    'distance' => $leg->distance,
                    'geometry' => $leg->geometry,
                    'position' => $leg->position,
                ]);
            }
        }

        return (new TripResource($newTrip))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
