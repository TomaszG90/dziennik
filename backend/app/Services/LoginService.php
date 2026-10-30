<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Validation\ValidationException;

class LoginService
{
    public function __construct(
        private AuthManager $auth
    ) {}

    /**
     * Login a user and create a Sanctum token.
     *
     * @param array $credentials
     * @return array
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        if (!$this->auth->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('The provided credentials are incorrect.'),
            ]);
        }

        $user = $this->auth->user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
