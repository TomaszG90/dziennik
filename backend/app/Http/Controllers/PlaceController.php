<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderPlacesRequest;
use App\Http\Requests\StorePlaceRequest;
use App\Http\Requests\UpdatePlaceRequest;
use App\Http\Resources\PlaceResource;
use App\Models\Place;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PlaceController extends Controller
{
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

        $place = $trip->places()->create([
            ...$request->validated(),
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

        $place->update($request->validated());

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

    private function ensurePlaceBelongsToTrip(Trip $trip, Place $place): void
    {
        abort_unless(
            $place->trip_id === $trip->id,
            Response::HTTP_NOT_FOUND
        );
    }
}
