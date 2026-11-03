<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class RegisterService
{
    /**
     * Register a new user.
     */
    public function register(array $data): User
    {
        $avatars = glob(public_path('avatars/*.png'));

        if ($avatars === false || $avatars === []) {
            throw new RuntimeException('No avatar images found in public/avatars.');
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'profile_visibility' => $data['profile_visibility'],
            'avatar_path' => basename($avatars[random_int(0, count($avatars) - 1)]),
        ]);
    }
}
