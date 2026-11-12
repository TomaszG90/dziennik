<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicUserResource;
use App\Http\Resources\StatsResource;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PublicProfileController extends Controller
{
    public function __construct(
        private readonly StatsService $statsService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->where('profile_visibility', 'public')
            ->orderBy('id')
            ->paginate(20);

        return PublicUserResource::collection($users);
    }

    public function show(User $user): PublicUserResource
    {
        $this->ensurePublicProfile($user);

        return new PublicUserResource($user);
    }

    public function trips(User $user): AnonymousResourceCollection
    {
        $this->ensurePublicProfile($user);

        $trips = $user->trips()
            ->orderBy('start_date')
            ->get();

        return TripResource::collection($trips);
    }

    public function map(User $user): JsonResponse
    {
        $this->ensurePublicProfile($user);

        $trips = $user->trips()
            ->with([
                'places' => fn ($query) => $query->orderBy('position'),
                'legs' => fn ($query) => $query->orderBy('position'),
            ])
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

    public function stats(Request $request, User $user): StatsResource
    {
        $this->ensurePublicProfile($user);

        $validated = $request->validate([
            'year' => ['sometimes', 'integer', 'min:1901', 'max:'.now()->year],
        ]);

        $year = isset($validated['year']) ? (int) $validated['year'] : null;

        $stats = $this->statsService->calculateStats($user, $year);

        return new StatsResource($stats);
    }

    private function ensurePublicProfile(User $user): void
    {
        abort_unless(
            $user->profile_visibility === 'public',
            Response::HTTP_NOT_FOUND
        );
    }
}
