<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoginServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_user_and_token_on_success(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $service = new LoginService;
        $result = $service->login([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertIsString($result['token']);
    }

    public function test_login_throws_exception_on_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $service = new LoginService;

        $this->expectException(ValidationException::class);

        $service->login([
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
    }

    public function test_login_throws_exception_on_nonexistent_user(): void
    {
        $service = new LoginService;

        $this->expectException(ValidationException::class);

        $service->login([
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);
    }
}
