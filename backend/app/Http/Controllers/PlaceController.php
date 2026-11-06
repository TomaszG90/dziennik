<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderPlacesRequest;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use App\Models\Trip;
use App\Services\CountryCodeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PlaceController extends Controller
{
    public function __construct(
        private CountryCodeResolver $countryCodeResolver
    ) {}

    public function index(Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);

        return PlaceResource::collection(
            $trip->places()->orderBy('position')->get()
        )->response()->setStatusCode(Response::HTTP_OK);
    }

    public function store(StorePlaceRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        $position = ((int) $trip->places()->max('position')) + 1;

        $attributes = $request->validated();
        $attributes['country_code'] = $this->resolveCountryCode($attributes);

        $place = $trip->places()->create([
            ...$attributes,
            'position' => $position,
        ]);

        return (new PlaceResource($place))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Trip $trip, Place $place): JsonResponse
    {
        Gate::authorize('view', $trip);
        $this->ensurePlaceBelongsToTrip($trip, $place);

        return (new PlaceResource($place))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function update(
        UpdatePlaceRequest $request,
        Trip $trip,
        Place $place
    ): JsonResponse {
        Gate::authorize('update', $trip);
        $this->ensurePlaceBelongsToTrip($trip, $place);

        $attributes = $request->validated();

        if ($this->shouldResolveCountryCode($attributes)) {
            $attributes['country_code'] = $this->resolveCountryCode($attributes, $place);
        }

        $place->update($attributes);

        return (new PlaceResource($place->refresh()))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Trip $trip, Place $place): JsonResponse
    {
        Gate::authorize('update', $trip);
        $this->ensurePlaceBelongsToTrip($trip, $place);

        $place->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function reorder(
        ReorderPlacesRequest $request,
        Trip $trip
    ): JsonResponse {
        Gate::authorize('update', $trip);

        $placeIds = $request->validated('place_ids');

        $currentIds = $trip->places()
            ->orderBy('position')
            ->pluck('id')
            ->all();

        sort($currentIds);
        $submittedIds = $placeIds;
        sort($submittedIds);

        if ($currentIds !== $submittedIds) {
            return response()->json([
                'message' => 'The place_ids must contain every place in this trip exactly once.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($trip, $placeIds) {
            $places = $trip->places()
                ->whereIn('id', $placeIds)
                ->get()
                ->keyBy('id');

            // Move positions temporarily to avoid unique-index conflicts.
            $offset = (int) $trip->places()->max('position') + count($placeIds) + 1;

            foreach ($placeIds as $index => $placeId) {
                $places[$placeId]->update([
                    'position' => $offset + $index,
                ]);
            }

            foreach ($placeIds as $index => $placeId) {
                $places[$placeId]->update([
                    'position' => $index + 1,
                ]);
            }
        });

        return PlaceResource::collection(
            $trip->places()->orderBy('position')->get()
        )->response()->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Resolve the country code for the submitted place attributes.
     *
     * A code sent by the client, usually taken from a geocoding result, wins.
     * Otherwise the country is determined from the coordinates, falling back
     * to the stored ones so a partial update can still be resolved.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCountryCode(array $attributes, ?Place $place = null): ?string
    {
        return $this->countryCodeResolver->resolve(
            $attributes['country_code'] ?? null,
            (float) ($attributes['latitude'] ?? $place?->latitude),
            (float) ($attributes['longitude'] ?? $place?->longitude),
        );
    }

    /**
     * Decide whether an update must (re)resolve the stored country code.
     *
     * Only a submitted country code or changed coordinates can invalidate it,
     * so editing the name alone keeps the current value.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function shouldResolveCountryCode(array $attributes): bool
    {
        return array_key_exists('country_code', $attributes)
            || array_key_exists('latitude', $attributes)
            || array_key_exists('longitude', $attributes);
    }

    private function ensurePlaceBelongsToTrip(Trip $trip, Place $place): void
    {
        abort_unless(
            $place->trip_id === $trip->id,
            Response::HTTP_NOT_FOUND
        );
    }
}
