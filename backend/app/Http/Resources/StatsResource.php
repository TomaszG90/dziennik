<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatsResource extends JsonResource
{
    /**
     * @return array{
     *     trips_count: int,
     *     countries: list<string>,
     *     total_distance: float,
     *     distance_by_transport: \stdClass,
     *     places_count: int
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var list<string> $countries */
        $countries = $this->resource['countries'];

        /** @var array<string, float> $distanceByTransport */
        $distanceByTransport = $this->resource['distance_by_transport'];

        return [
            'trips_count' => (int) $this->resource['trips_count'],
            'countries' => $countries,
            'total_distance' => (float) $this->resource['total_distance'],
            'distance_by_transport' => (object) $distanceByTransport,
            'places_count' => (int) $this->resource['places_count'],
        ];
    }
}
