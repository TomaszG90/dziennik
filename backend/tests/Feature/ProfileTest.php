<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_profile_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'profile_visibility' => 'public',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'profile_visibility',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'profile_visibility' => 'public',
                ],
            ]);

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data'));
    }

    public function test_get_profile_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertStatus(401);
    }

    public function test_get_profile_returns_correct_user_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'profile_visibility' => 'private',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Jane Smith')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.profile_visibility', 'private');
    }

    public function test_update_profile_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/me', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_profile_can_update_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'test@example.com',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'test@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_profile_cannot_update_email(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'old@example.com',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'email' => 'new@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
        ]);
    }

    public function test_update_profile_rejects_email_field_even_when_null(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/v1/me', ['email' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
        ]);
    }

    public function test_profile_service_does_not_update_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        app(ProfileService::class)->updateProfile($user, [
            'email' => 'new@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
        ]);
    }

    public function test_update_profile_can_update_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('OldPassword123!'),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_update_profile_can_update_visibility(): void
    {
        $user = User::factory()->create([
            'profile_visibility' => 'public',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'profile_visibility' => 'private',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.profile_visibility', 'private');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'profile_visibility' => 'private',
        ]);
    }

    public function test_update_profile_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'password' => 'NewPassword123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_update_profile_rejects_mismatched_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPassword123!')]);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'password' => 'NewPassword123!',
                'password_confirmation' => 'Different123!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_update_profile_rejects_weak_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('OldPassword123!')]);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_update_profile_rejects_invalid_visibility(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'profile_visibility' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_visibility']);
    }

    public function test_update_profile_can_update_multiple_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'old@example.com',
            'profile_visibility' => 'public',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withToken($token)
            ->patchJson('/api/v1/me', [
                'name' => 'New Name',
                'profile_visibility' => 'private',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'old@example.com')
            ->assertJsonPath('data.profile_visibility', 'private');
    }
}
