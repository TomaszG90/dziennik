<?php

namespace Tests\Feature;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_stats_returns_data_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2026-01-01',
        ]);

        Place::factory()->create([
            'trip_id' => $trip->id,
            'country_code' => 'PL',
        ]);

        Leg::factory()->create([
            'trip_id' => $trip->id,
            'transport' => 'car',
            'distance' => 100.0,
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'trips_count',
                    'countries',
                    'total_distance',
                    'distance_by_transport',
                    'places_count',
                ],
            ])
            ->assertJsonPath('data.trips_count', 1)
            ->assertJsonPath('data.countries', ['PL'])
            ->assertJsonPath('data.total_distance', 100.0)
            ->assertJsonPath('data.distance_by_transport.car', 100.0)
            ->assertJsonPath('data.places_count', 1);
    }

    public function test_get_stats_returns_401_for_unauthenticated_user(): void
    {
        $response = $this->getJson('/api/v1/stats');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_get_stats_with_year_parameter_filters_correctly(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $trip2025 = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-05',
        ]);

        $trip2026 = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);

        Place::factory()->create([
            'trip_id' => $trip2025->id,
            'country_code' => 'PL',
        ]);

        Place::factory()->create([
            'trip_id' => $trip2026->id,
            'country_code' => 'DE',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/stats?year=2025');

        $response->assertStatus(200)
            ->assertJsonPath('data.trips_count', 1)
            ->assertJsonPath('data.countries', ['PL']);
    }

    public function test_get_stats_validates_year_parameter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/stats?year=1800');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('year');

        $response = $this->withToken($token)->getJson('/api/v1/stats?year=abc');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('year');
    }

    public function test_get_stats_returns_empty_data_for_user_with_no_trips(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.trips_count', 0)
            ->assertJsonPath('data.countries', [])
            ->assertJsonPath('data.total_distance', 0)
            ->assertJsonPath('data.distance_by_transport', [])
            ->assertJsonPath('data.places_count', 0);
    }

    public function test_get_stats_returns_only_users_own_trips(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token1 = $user1->createToken('test-token')->plainTextToken;

        Trip::factory()->create([
            'user_id' => $user2->id,
            'start_date' => '2026-01-01',
        ]);

        $response = $this->withToken($token1)->getJson('/api/v1/stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.trips_count', 0);
    }
}
