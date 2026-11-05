<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_a_link_to_the_frontend(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Jeśli konto z podanym adresem istnieje, otrzymasz wiadomość z linkiem do zmiany hasła.',
            ]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost:5173/reset-password?')
                && str_contains($url, 'token='.$notification->token)
                && str_contains($url, 'email=user%40example.com');
        });
    }

    public function test_forgot_password_does_not_disclose_unknown_email_addresses(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Jeśli konto z podanym adresem istnieje, otrzymasz wiadomość z linkiem do zmiany hasła.',
            ]);
        Notification::assertNothingSent();
    }

    public function test_forgot_password_requires_a_valid_email(): void
    {
        $this->postJson('/api/v1/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_password_changes_password_and_invalidates_tokens(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123!']);
        $token = Password::createToken($user);
        $user->createToken('existing-token');

        $response = $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Hasło zostało zmienione.']);

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_rejects_an_expired_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 1)]);

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_rejects_a_token_for_a_different_email(): void
    {
        $tokenOwner = User::factory()->create(['email' => 'owner@example.com']);
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $token = Password::createToken($tokenOwner);

        $this->postJson('/api/v1/reset-password', [
            'email' => $otherUser->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertTrue(Hash::check('password', $tokenOwner->fresh()->password));
        $this->assertTrue(Hash::check('password', $otherUser->fresh()->password));
    }

    public function test_reset_password_token_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $this->postJson('/api/v1/reset-password', $payload)->assertOk();

        $this->postJson('/api/v1/reset-password', [
            ...$payload,
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_forgot_password_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 6) as $attempt) {
            $this->postJson('/api/v1/forgot-password', [
                'email' => 'missing@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/v1/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertTooManyRequests();
    }

    public function test_reset_password_validates_confirmation_and_password_strength(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/reset-password', [
            'email' => $user->email,
            'token' => 'token',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
