<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Leg
 */

class LegResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'from_place_id' => $this->from_place_id,
            'to_place_id' => $this->to_place_id,
            'transport' => $this->transport,
            'distance' => $this->distance,
            'geometry' => $this->geometry,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
