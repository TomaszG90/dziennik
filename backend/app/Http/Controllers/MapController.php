<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MapController extends Controller
{
    /**
     * Return all map data for the authenticated user's trips.
     *
     * @response array{data: array{
     *   trips: list<array{id: int, name: string, start_date: string|null, end_date: string|null}>,
     *   places: list<array{id: int, trip_id: int, trip_name: string, name: string, latitude: string|null, longitude: string|null, position: int, country_code: string|null}>,
     *   legs: list<array{id: int, trip_id: int, trip_name: string, from_place_id: int, to_place_id: int, transport: string, distance: string|null, geometry: array{type: 'LineString', coordinates: list<array{0: float, 1: float}>}|null, position: int}>
     * }}
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Trip::class);

        $trips = $request->user()
            ->trips()
            ->with(['places', 'legs'])
            ->orderBy('start_date')
            ->get();

        $places = [];
        $legs = [];

        foreach ($trips as $trip) {
            foreach ($trip->places as $place) {
                $places[] = [
                    'id' => $place->id,
                    'trip_id' => $trip->id,
                    'trip_name' => $trip->name,
                    'name' => $place->name,
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                    'position' => $place->position,
                    'country_code' => $place->country_code,
                ];
            }

            foreach ($trip->legs as $leg) {
                $legs[] = [
                    'id' => $leg->id,
                    'trip_id' => $trip->id,
                    'trip_name' => $trip->name,
                    'from_place_id' => $leg->from_place_id,
                    'to_place_id' => $leg->to_place_id,
                    'transport' => $leg->transport,
                    'distance' => $leg->distance,
                    'geometry' => $leg->geometry,
                    'position' => $leg->position,
                ];
            }
        }

        return response()->json([
            'data' => [
                'trips' => $trips->map(fn (Trip $trip) => [
                    'id' => $trip->id,
                    'name' => $trip->name,
                    'start_date' => $trip->start_date?->toDateString(),
                    'end_date' => $trip->end_date?->toDateString(),
                ])->values(),
                'places' => $places,
                'legs' => $legs,
            ],
        ], Response::HTTP_OK);
    }
}
