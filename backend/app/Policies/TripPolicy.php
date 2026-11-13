<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\TripInvitation;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trip $trip): bool
    {
        // Właściciel może zawsze widzieć
        if ($user->id === $trip->user_id) {
            return true;
        }

        // Gość z zaakceptowanym zaproszeniem może widzieć
        return TripInvitation::where('trip_id', $trip->id)
            ->where('invitee_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $user->id === $trip->user_id;
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $user->id === $trip->user_id;
    }
}
