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
            'street' => $this->resource['street'] ?? null,
            'housenumber' => $this->resource['housenumber'] ?? null,
            'postcode' => $this->resource['postcode'] ?? null,
            'city' => $this->resource['city'] ?? null,
            'district' => $this->resource['district'] ?? null,
            'county' => $this->resource['county'] ?? null,
            'state' => $this->resource['state'] ?? null,
            'country' => $this->resource['country'] ?? null,
            'country_code' => $this->resource['country_code'] ?? null,
        ];
    }
}
