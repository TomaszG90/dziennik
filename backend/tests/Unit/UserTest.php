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

    public function test_profile_fields_and_privacy_settings_are_mass_assignable_and_cast(): void
    {
        $privacySettings = [
            'name' => true,
            'email' => false,
            'firstName' => true,
            'lastName' => false,
            'city' => true,
            'birthDate' => false,
            'avatar' => true,
        ];

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'city' => 'Legnica',
            'birth_date' => '1990-04-15',
            'avatar_path' => '3.png',
            'privacy_settings' => $privacySettings,
        ]);

        $this->assertSame('Jane', $user->first_name);
        $this->assertSame('Doe', $user->last_name);
        $this->assertSame('Legnica', $user->city);
        $this->assertSame('1990-04-15', $user->birth_date->format('Y-m-d'));
        $this->assertSame('3.png', $user->avatar_path);
        $this->assertSame($privacySettings, $user->privacy_settings);
    }

    public function test_factory_uses_private_defaults_for_all_profile_fields(): void
    {
        $user = User::factory()->create();

        $this->assertSame(User::DEFAULT_PRIVACY_SETTINGS, $user->privacy_settings);
        $this->assertNull($user->avatar_path);
    }
}
