<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class LogoutService
{
    /**
     * Logout the current user and revoke the current token.
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            $user->currentAccessToken()->delete();
        }

        Auth::guard('web')->logout();
    }
}
