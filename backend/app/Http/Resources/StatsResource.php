<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *   trips_count: int,
     *   countries: list<string>,
     *   total_distance: float,
     *   distance_by_transport: array<string, float>,
     *   places_count: int
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'trips_count' => $this->resource['trips_count'],
            'countries' => $this->resource['countries'],
            'total_distance' => $this->resource['total_distance'],
            'distance_by_transport' => $this->resource['distance_by_transport'],
            'places_count' => $this->resource['places_count'],
        ];
    }
}
