<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_unknown_api_route_returns_404_json(): void
    {
        $response = $this->getJson('/api/v1/nieistniejaca-trasa');

        $response->assertStatus(404)
            ->assertJsonStructure(['message']);
    }
}
