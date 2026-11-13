<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $otherUser = $this->sender_id === $request->user()->id ? $this->recipient : $this->sender;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'user' => new PublicUserResource($otherUser),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
