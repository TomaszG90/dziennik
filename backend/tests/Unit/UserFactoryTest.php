<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_generates_valid_profile_visibility(): void
    {
        $user = User::factory()->create();

        $this->assertContains($user->profile_visibility, ['public', 'private']);
    }

    public function test_profile_visibility_defaults_to_private(): void
    {
        $attributes = User::factory()->raw();
        unset($attributes['profile_visibility']);

        $user = User::create($attributes);
        $user->refresh();

        $this->assertEquals('private', $user->profile_visibility);
    }

    public function test_public_profile_state_sets_public_visibility(): void
    {
        $user = User::factory()->publicProfile()->create();

        $this->assertEquals('public', $user->profile_visibility);
    }

    public function test_private_profile_state_sets_private_visibility(): void
    {
        $user = User::factory()->privateProfile()->create();

        $this->assertEquals('private', $user->profile_visibility);
    }
}
