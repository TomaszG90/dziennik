<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        if (array_key_exists('privacy_settings', $data)) {
            $data['privacy_settings'] = array_merge(
                $user->privacy_settings ?? User::DEFAULT_PRIVACY_SETTINGS,
                $data['privacy_settings']
            );
        }
        $user->update($data);

        return $user->fresh();
    }
}
