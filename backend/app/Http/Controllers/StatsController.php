<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatsRequest;
use App\Http\Resources\StatsResource;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(
        private StatsService $statsService
    ) {}

    /**
     * Return statistics for the authenticated user's trips.
     *
     * @response array{data: array{
     *   trips_count: int,
     *   countries: list<string>,
     *   total_distance: float,
     *   distance_by_transport: array<string, float>,
     *   places_count: int
     * }}
     */
    public function index(StatsRequest $request): JsonResponse
    {
        $year = $request->input('year') ? (int) $request->input('year') : null;
        $stats = $this->statsService->calculateStats($request->user(), $year);

        return (new StatsResource($stats))->response();
    }
}
