<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeocodeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource['name'] ?? null,
            'latitude' => $this->resource['latitude'] ?? null,
            'longitude' => $this->resource['longitude'] ?? null,
            'country_code' => $this->resource['country_code'] ?? null,
        ];
    }
}
