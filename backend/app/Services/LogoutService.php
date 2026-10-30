<?php

namespace App\Services;

use Illuminate\Auth\AuthManager;

class LogoutService
{
    public function __construct(
        private AuthManager $auth
    ) {}

    /**
     * Logout the current user and revoke the current token.
     */
    public function logout(): void
    {
        $user = $this->auth->guard()->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        $this->auth->guard('web')->logout();
    }
}
