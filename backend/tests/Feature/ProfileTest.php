<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
