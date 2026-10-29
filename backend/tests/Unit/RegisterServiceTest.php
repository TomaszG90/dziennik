<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\RegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user(): void
    {
        $service = new RegisterService;
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'profile_visibility' => 'public',
        ];

        $user = $service->register($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('public', $user->profile_visibility);
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_register_hashes_password(): void
    {
        $service = new RegisterService;
        $data = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'profile_visibility' => 'private',
        ];

        $user = $service->register($data);

        $this->assertNotEquals('password123', $user->password);
        $this->assertNotEmpty($user->password);
    }

    public function test_register_with_private_visibility(): void
    {
        $service = new RegisterService;
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'profile_visibility' => 'private',
        ];

        $user = $service->register($data);

        $this->assertEquals('private', $user->profile_visibility);
    }
}
