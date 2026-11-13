<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class PublicUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = $this->privacy_settings ?? [];

        return [
            'id' => $this->id,
            'name' => $this->when(
                $settings['name'] ?? false,
                $this->name
            ),
            'first_name' => $this->when(
                $settings['firstName'] ?? false,
                $this->first_name
            ),
            'last_name' => $this->when(
                $settings['lastName'] ?? false,
                $this->last_name
            ),
            'city' => $this->when(
                $settings['city'] ?? false,
                $this->city
            ),
            'birth_date' => $this->when(
                $settings['birthDate'] ?? false,
                $this->birth_date?->format('Y-m-d')
            ),
            'avatar_path' => $this->when(
                $settings['avatar'] ?? false,
                $this->avatar_path
            ),
            'created_at' => $this->created_at,
        ];
    }
}
