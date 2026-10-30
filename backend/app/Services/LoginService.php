<?php

namespace App\Services;

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
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        if (! $this->auth->guard()->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('The provided credentials are incorrect.'),
            ]);
        }

        $user = $this->auth->guard()->user();
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
