<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_test_user_with_private_visibility(): void
    {
        $this->seed();

        $testUser = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($testUser);
        $this->assertEquals('private', $testUser->profile_visibility);
    }

    public function test_seeder_creates_public_user_with_public_visibility(): void
    {
        $this->seed();

        $publicUser = User::where('email', 'public@example.com')->first();

        $this->assertNotNull($publicUser);
        $this->assertEquals('public', $publicUser->profile_visibility);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::where('email', 'test@example.com')->count());
        $this->assertSame(1, User::where('email', 'public@example.com')->count());
        $this->assertSame(2, User::count());
    }
}
