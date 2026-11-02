<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeocodeRequest;
use App\Http\Resources\GeocodeResource;
use App\Services\GeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeocodeController extends Controller
{
    public function __construct(
        private GeocodingService $geocodingService
    ) {}

    /**
     * Search for places using geocoding service.
     */
    public function __invoke(GeocodeRequest $request): AnonymousResourceCollection
    {
        $results = $this->geocodingService->search($request->validated('q'));

        return GeocodeResource::collection($results);
    }
}
