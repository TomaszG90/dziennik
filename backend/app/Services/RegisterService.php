<?php

namespace App\Services;

use App\Models\User;

class RegisterService
{
    /**
     * Register a new user.
     */
    public function register(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'profile_visibility' => $data['profile_visibility'],
        ]);
    }
}
