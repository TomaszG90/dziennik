<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_visibility_is_mass_assignable(): void
    {
        $user = User::factory()->create([
            'profile_visibility' => 'public',
        ]);

        $this->assertEquals('public', $user->profile_visibility);
    }

    public function test_profile_visibility_accepts_valid_values(): void
    {
        $publicUser = User::factory()->create(['profile_visibility' => 'public']);
        $privateUser = User::factory()->create(['profile_visibility' => 'private']);

        $this->assertEquals('public', $publicUser->profile_visibility);
        $this->assertEquals('private', $privateUser->profile_visibility);
    }

    public function test_profile_visibility_is_cast_to_string(): void
    {
        $user = User::factory()->create(['profile_visibility' => 'public']);

        $this->assertIsString($user->profile_visibility);
    }
}
