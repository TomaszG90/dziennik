<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);
        });

        RateLimiter::for('geocode', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('resend-activation', function (Request $request) {
            $email = (string) $request->input('email');
            $response = fn () => response()->json([
                'message' => 'Zbyt wiele prób wysyłki wiadomości. Limit wynosi 20 wiadomości na godzinę. Spróbuj ponownie później.',
            ], 429);

            return [
                Limit::perHour(20)->by($request->ip())->response($response),
                Limit::perHour(20)->by($email !== '' ? $email : $request->ip())->response($response),
            ];
        });
    }
}
