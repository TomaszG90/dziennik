<?php

namespace Tests\Feature;

use App\Mail\AccountActivationEmail;
use App\Models\User;
use App\Services\AccountActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_activation_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Podróżnik Jan',
            'email' => 'jan@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'profile_visibility' => 'public',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'jan@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        Mail::assertSent(AccountActivationEmail::class, function (AccountActivationEmail $mail) use ($user): bool {
            return $mail->hasTo('jan@example.com')
                && $mail->user->id === $user->id
                && str_contains($mail->activationUrl, "/api/v1/auth/verify-email/{$user->id}/");
        });
    }

    public function test_account_can_be_activated_via_signed_url_redirecting_to_frontend(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'podroznik@example.com',
        ]);

        $activationService = app(AccountActivationService::class);
        $url = $activationService->generateActivationUrl($user);

        $response = $this->get($url);

        $response->assertRedirect(rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/').'/auth?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_account_can_be_activated_via_signed_url_returning_json(): void
    {
        $user = User::factory()->unverified()->create();

        $activationService = app(AccountActivationService::class);
        $url = $activationService->generateActivationUrl($user);

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJson([
                'message' => 'Konto zostało pomyślnie aktywowane.',
            ]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_activating_with_invalid_signature_fails(): void
    {
        $user = User::factory()->unverified()->create();

        $activationService = app(AccountActivationService::class);
        $url = $activationService->generateActivationUrl($user).'tampered';

        $response = $this->getJson($url);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Link aktywacyjny jest nieprawidłowy lub wygasł.',
            ]);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_activating_already_verified_account(): void
    {
        $user = User::factory()->create();

        $activationService = app(AccountActivationService::class);
        $url = $activationService->generateActivationUrl($user);

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJson([
                'message' => 'Konto zostało już wcześniej aktywowane.',
            ]);
    }

    public function test_resending_activation_email_for_unverified_user(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'resend@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/email/resend', [
            'email' => 'resend@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Jeśli konto z podanym adresem istnieje i nie zostało jeszcze aktywowane, wysłaliśmy nowy link aktywacyjny.',
            ]);

        Mail::assertSent(AccountActivationEmail::class, function (AccountActivationEmail $mail): bool {
            return $mail->hasTo('resend@example.com');
        });
    }

    public function test_resending_activation_email_does_not_send_for_verified_user(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'verified@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/email/resend', [
            'email' => 'verified@example.com',
        ]);

        $response->assertOk();
        Mail::assertNothingSent();
    }

    public function test_full_flow_registration_blocks_login_until_activated(): void
    {
        Mail::fake();

        // 1. Register
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Flow User',
            'email' => 'flow@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'profile_visibility' => 'private',
        ])->assertCreated();

        $user = User::where('email', 'flow@example.com')->firstOrFail();

        // 2. Login fails before activation
        $this->postJson('/api/v1/auth/login', [
            'email' => 'flow@example.com',
            'password' => 'Password123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // 3. Activate account
        $activationService = app(AccountActivationService::class);
        $url = $activationService->generateActivationUrl($user);

        $this->getJson($url)->assertOk();

        // 4. Login succeeds after activation
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'flow@example.com',
            'password' => 'Password123!',
        ])->assertOk();

        $this->assertNotEmpty($loginResponse->json('data.token'));
    }
}
