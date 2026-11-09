<?php

namespace Tests\Unit;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private StatsService $statsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statsService = new StatsService();
    }

    public function test_calculate_stats_for_all_time(): void
    {
        $user = User::factory()->create();

        $trip1 = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-05',
        ]);

        $trip2 = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-10',
        ]);

        $place1 = Place::factory()->create([
            'trip_id' => $trip1->id,
            'country_code' => 'PL',
        ]);

        $place2 = Place::factory()->create([
            'trip_id' => $trip2->id,
            'country_code' => 'DE',
        ]);

        Leg::factory()->create([
            'trip_id' => $trip1->id,
            'transport' => 'car',
            'distance' => 100.5,
        ]);

        Leg::factory()->create([
            'trip_id' => $trip2->id,
            'transport' => 'plane',
            'distance' => 500.0,
        ]);

        $stats = $this->statsService->calculateStats($user);

        $this->assertEquals(2, $stats['trips_count']);
        $this->assertCount(2, $stats['countries']);
        $this->assertContains('PL', $stats['countries']);
        $this->assertContains('DE', $stats['countries']);
        $this->assertEquals(600.5, $stats['total_distance']);
        $this->assertEquals(['car' => 100.5, 'plane' => 500.0], $stats['distance_by_transport']);
        $this->assertEquals(2, $stats['places_count']);
    }

    public function test_calculate_stats_for_specific_year(): void
    {
        $user = User::factory()->create();

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

        Leg::factory()->create([
            'trip_id' => $trip2025->id,
            'transport' => 'car',
            'distance' => 100.0,
        ]);

        Leg::factory()->create([
            'trip_id' => $trip2026->id,
            'transport' => 'plane',
            'distance' => 500.0,
        ]);

        $stats2025 = $this->statsService->calculateStats($user, 2025);

        $this->assertEquals(1, $stats2025['trips_count']);
        $this->assertCount(1, $stats2025['countries']);
        $this->assertContains('PL', $stats2025['countries']);
        $this->assertEquals(100.0, $stats2025['total_distance']);
        $this->assertEquals(['car' => 100.0], $stats2025['distance_by_transport']);
        $this->assertEquals(1, $stats2025['places_count']);

        $stats2026 = $this->statsService->calculateStats($user, 2026);

        $this->assertEquals(1, $stats2026['trips_count']);
        $this->assertCount(1, $stats2026['countries']);
        $this->assertContains('DE', $stats2026['countries']);
        $this->assertEquals(500.0, $stats2026['total_distance']);
        $this->assertEquals(['plane' => 500.0], $stats2026['distance_by_transport']);
        $this->assertEquals(1, $stats2026['places_count']);
    }

    public function test_calculate_stats_for_user_with_no_trips(): void
    {
        $user = User::factory()->create();

        $stats = $this->statsService->calculateStats($user);

        $this->assertEquals(0, $stats['trips_count']);
        $this->assertEmpty($stats['countries']);
        $this->assertEquals(0, $stats['total_distance']);
        $this->assertEmpty($stats['distance_by_transport']);
        $this->assertEquals(0, $stats['places_count']);
    }

    public function test_calculate_stats_filters_trips_by_year_correctly(): void
    {
        $user = User::factory()->create();

        $tripSpanningYears = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2025-12-25',
            'end_date' => '2026-01-05',
        ]);

        Place::factory()->create([
            'trip_id' => $tripSpanningYears->id,
            'country_code' => 'PL',
        ]);

        Leg::factory()->create([
            'trip_id' => $tripSpanningYears->id,
            'transport' => 'train',
            'distance' => 200.0,
        ]);

        $stats2025 = $this->statsService->calculateStats($user, 2025);
        $stats2026 = $this->statsService->calculateStats($user, 2026);

        $this->assertEquals(1, $stats2025['trips_count']);
        $this->assertEquals(1, $stats2026['trips_count']);
    }

    public function test_calculate_stats_groups_distance_by_transport(): void
    {
        $user = User::factory()->create();

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2026-01-01',
        ]);

        Leg::factory()->create([
            'trip_id' => $trip->id,
            'transport' => 'car',
            'distance' => 100.0,
        ]);

        Leg::factory()->create([
            'trip_id' => $trip->id,
            'transport' => 'car',
            'distance' => 50.0,
        ]);

        Leg::factory()->create([
            'trip_id' => $trip->id,
            'transport' => 'plane',
            'distance' => 500.0,
        ]);

        Leg::factory()->create([
            'trip_id' => $trip->id,
            'transport' => 'train',
            'distance' => 200.0,
        ]);

        $stats = $this->statsService->calculateStats($user);

        $this->assertEquals(150.0, $stats['distance_by_transport']['car']);
        $this->assertEquals(500.0, $stats['distance_by_transport']['plane']);
        $this->assertEquals(200.0, $stats['distance_by_transport']['train']);
    }
}
