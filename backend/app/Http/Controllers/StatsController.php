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

    public function index(StatsRequest $request): JsonResponse
    {
        $year = $request->input('year') ? (int) $request->input('year') : null;
        $stats = $this->statsService->calculateStats($request->user(), $year);

        return (new StatsResource($stats))->response();
    }
}
