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
     *
     * @return void
     */
    public function logout(): void
    {
        $user = $this->auth->user();

        if ($user) {
            $user->currentAccessToken()->delete();
        }

        $this->auth->guard('web')->logout();
    }
}
