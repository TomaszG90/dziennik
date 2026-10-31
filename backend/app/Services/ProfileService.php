<?php

namespace App\Services;

use App\Models\User;

class ProfileService
{
    /**
     * Get the authenticated user's profile.
     */
    public function getProfile(User $user): User
    {
        return $user->fresh();
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh();
    }
}
