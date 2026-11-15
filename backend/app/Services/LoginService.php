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
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        if (! $this->auth->guard()->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('The provided credentials are incorrect.'),
            ]);
        }

        /** @var User $user */
        $user = $this->auth->guard()->user();

        if (! $user->hasVerifiedEmail()) {
            $this->auth->guard()->logout();

            throw ValidationException::withMessages([
                'email' => __('Konto nie jest jeszcze aktywne, sprawdź swoją skrzynkę e-mail lub kliknij w ten link aby wysłać jeszcze raz'),
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
