<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TripController extends Controller
{
    public function years(Request $request): JsonResponse
    {
        $dates = $this->accessibleTripsQuery($request->user())
            ->get(['start_date', 'end_date']);

        $years = $dates
            ->flatMap(fn (Trip $trip) => [$trip->start_date, $trip->end_date])
            ->filter()
            ->map(fn ($date) => (int) $date->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        return response()->json(['data' => $years], Response::HTTP_OK);
    }

    /**
     * List trips accessible to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Trip::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:9999'],
            'status' => ['sometimes', 'in:all,planned,past'],
        ]);
        $user = $request->user();
        $today = now()->toDateString();

        $trips = $this->accessibleTripsQuery($user)
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $search = mb_strtolower($search);
                $query->where(function (Builder $query) use ($search) {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%'.$search.'%']);
                });
            })
            ->when($validated['year'] ?? null, function (Builder $query, int $year) {
                $query->where(function (Builder $query) use ($year) {
                    $query->whereYear('start_date', $year)
                        ->orWhere(function (Builder $query) use ($year) {
                            $query->whereNull('start_date')
                                ->whereYear('end_date', $year);
                        });
                });
            })
            ->when(($validated['status'] ?? 'all') === 'past', function (Builder $query) use ($today) {
                $query->whereRaw('COALESCE(end_date, start_date) < ?', [$today]);
            })
            ->when(($validated['status'] ?? 'all') === 'planned', function (Builder $query) use ($today) {
                $query->where(function (Builder $query) use ($today) {
                    $query->whereNull('start_date')
                        ->whereNull('end_date')
                        ->orWhereRaw('COALESCE(end_date, start_date) >= ?', [$today]);
                });
            })
            ->when(
                ($validated['status'] ?? 'all') === 'planned',
                fn (Builder $query) => $query->orderByRaw('COALESCE(start_date, end_date) ASC'),
                fn (Builder $query) => ($validated['status'] ?? 'all') === 'past'
                    ? $query->orderByRaw('COALESCE(end_date, start_date) DESC')
                    : $query->orderByDesc('start_date'),
            )
            ->orderBy('id')
            ->paginate(min((int) ($validated['per_page'] ?? 5), 5));

        return response()->json([
            'data' => $trips->getCollection()->map(function (Trip $trip) use ($user) {
                $data = (new TripResource($trip))->toArray(request());
                $data['is_guest'] = $trip->user_id !== $user->id;

                return $data;
            }),
            'meta' => [
                'current_page' => $trips->currentPage(),
                'last_page' => $trips->lastPage(),
                'per_page' => $trips->perPage(),
                'total' => $trips->total(),
            ],
        ], Response::HTTP_OK);
    }

    private function accessibleTripsQuery(User $user): Builder
    {
        return Trip::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('invitations', function (Builder $invitations) use ($user) {
                        $invitations->where('invitee_id', $user->id)
                            ->where('status', 'accepted');
                    });
            });
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
