<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLegRequest;
use App\Http\Requests\UpdateLegRequest;
use App\Http\Resources\LegResource;
use App\Models\Leg;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LegController extends Controller
{
    public function index(Trip $trip): JsonResponse
    {
        Gate::authorize('view', $trip);

        return LegResource::collection(
            $trip->legs()->orderBy('position')->get()
        )->response()->setStatusCode(Response::HTTP_OK);
    }

    public function store(StoreLegRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        $position = ((int) $trip->legs()->max('position')) + 1;

        $leg = $trip->legs()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return (new LegResource($leg))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Trip $trip, Leg $leg): JsonResponse
    {
        Gate::authorize('view', $trip);
        $this->ensureLegBelongsToTrip($trip, $leg);

        return (new LegResource($leg))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function update(
        UpdateLegRequest $request,
        Trip $trip,
        Leg $leg
    ): JsonResponse {
        Gate::authorize('update', $trip);
        $this->ensureLegBelongsToTrip($trip, $leg);

        $leg->update($request->validated());

        return (new LegResource($leg->refresh()))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Trip $trip, Leg $leg): JsonResponse
    {
        Gate::authorize('update', $trip);
        $this->ensureLegBelongsToTrip($trip, $leg);

        $leg->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureLegBelongsToTrip(Trip $trip, Leg $leg): void
    {
        abort_unless(
            $leg->trip_id === $trip->id,
            Response::HTTP_NOT_FOUND
        );
    }
}
