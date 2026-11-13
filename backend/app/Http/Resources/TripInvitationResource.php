<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'inviter_id' => $this->inviter_id,
            'invitee_id' => $this->invitee_id,
            'status' => $this->status,
            'invitee' => $this->whenLoaded('invitee', function () {
                return new PublicUserResource($this->invitee);
            }),
            'inviter' => $this->whenLoaded('inviter', function () {
                return new PublicUserResource($this->inviter);
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
