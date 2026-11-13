<?php

namespace Tests\Feature;

use App\Mail\InviteFriendEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_requires_authentication(): void
    {
        $this->postJson('/api/v1/invite', ['email' => 'test@example.com'])
            ->assertUnauthorized();
    }

    public function test_invite_validates_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/invite', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->actingAs($user)->postJson('/api/v1/invite', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_invite_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->publicProfile()->create([
            'name' => 'Jan Kowalski',
            'city' => 'Warszawa',
        ]);

        $this->actingAs($user)->postJson('/api/v1/invite', [
            'email' => 'friend@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'Zaproszenie zostało wysłane.');

        Mail::assertSent(InviteFriendEmail::class, function ($mail) use ($user) {
            return $mail->hasTo('friend@example.com')
                && $mail->inviter->is($user);
        });
    }
}
