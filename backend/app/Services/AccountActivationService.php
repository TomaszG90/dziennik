<?php

namespace App\Services;

use App\Mail\AccountActivationEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AccountActivationService
{
    /**
     * Send activation email with signed verification link.
     */
    public function sendActivationEmail(User $user): void
    {
        $activationUrl = $this->generateActivationUrl($user);

        Mail::to($user->email)->send(new AccountActivationEmail($user, $activationUrl));
    }

    /**
     * Generate a signed activation URL valid for 24 hours.
     */
    public function generateActivationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
    }

    /**
     * Resend activation email for an unverified user.
     */
    public function resendActivationEmail(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user || $user->hasVerifiedEmail()) {
            return false;
        }

        $this->sendActivationEmail($user);

        return true;
    }
}
