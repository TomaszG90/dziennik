<?php

namespace Tests\Unit;

use App\Services\LogoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_works_without_authenticated_user(): void
    {
        $service = new LogoutService();

        $this->expectNotToPerformAssertions();

        $service->logout();
    }
}
