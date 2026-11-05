<?php

namespace Tests\Unit;

use App\Services\DistanceService;
use PHPUnit\Framework\TestCase;

class DistanceServiceTest extends TestCase
{
    private DistanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DistanceService;
    }

    public function test_haversine_distance_between_legnica_and_warsaw(): void
    {
        // Legnica: 51.2064, 15.9319
        // Warszawa: 52.2297, 21.0122
        // Expected distance ~368 km
        $distance = $this->service->calculateHaversineDistance(51.2064, 15.9319, 52.2297, 21.0122);

        $this->assertGreaterThan(360, $distance);
        $this->assertLessThan(375, $distance);
    }

    public function test_straight_line_distance_between_legnica_and_warsaw(): void
    {
        // Legnica: 51.2064, 15.9319
        // Warszawa: 52.2297, 21.0122
        $distance = $this->service->calculateStraightLineDistance(51.2064, 15.9319, 52.2297, 21.0122);

        $this->assertGreaterThan(360, $distance);
        $this->assertLessThan(375, $distance);
    }

    public function test_haversine_distance_for_identical_points(): void
    {
        $distance = $this->service->calculateHaversineDistance(51.2064, 15.9319, 51.2064, 15.9319);

        $this->assertEquals(0.0, $distance);
    }

    public function test_straight_line_distance_for_identical_points(): void
    {
        $distance = $this->service->calculateStraightLineDistance(51.2064, 15.9319, 51.2064, 15.9319);

        $this->assertEquals(0.0, $distance);
    }

    public function test_haversine_distance_for_antipodes(): void
    {
        // North Pole to South Pole
        $distance = $this->service->calculateHaversineDistance(90, 0, -90, 0);

        $this->assertGreaterThan(20000, $distance);
        $this->assertLessThan(20020, $distance);
    }

    public function test_straight_line_distance_for_antipodes(): void
    {
        // North Pole to South Pole
        $distance = $this->service->calculateStraightLineDistance(90, 0, -90, 0);

        $this->assertGreaterThan(20000, $distance);
        $this->assertLessThan(20020, $distance);
    }

    public function test_haversine_distance_for_short_distance(): void
    {
        // Two points close to each other (within 1 km)
        $distance = $this->service->calculateHaversineDistance(51.2064, 15.9319, 51.2100, 15.9350);

        $this->assertGreaterThan(0, $distance);
        $this->assertLessThan(1, $distance);
    }

    public function test_straight_line_distance_for_short_distance(): void
    {
        // Two points close to each other (within 1 km)
        $distance = $this->service->calculateStraightLineDistance(51.2064, 15.9319, 51.2100, 15.9350);

        $this->assertGreaterThan(0, $distance);
        $this->assertLessThan(1, $distance);
    }

    public function test_haversine_throws_exception_for_invalid_latitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Latitude must be between -90 and 90');

        $this->service->calculateHaversineDistance(91, 0, 52.2297, 21.0122);
    }

    public function test_haversine_throws_exception_for_invalid_longitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Longitude must be between -180 and 180');

        $this->service->calculateHaversineDistance(51.2064, 181, 52.2297, 21.0122);
    }

    public function test_straight_line_throws_exception_for_invalid_latitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Latitude must be between -90 and 90');

        $this->service->calculateStraightLineDistance(-91, 0, 52.2297, 21.0122);
    }

    public function test_straight_line_throws_exception_for_invalid_longitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Longitude must be between -180 and 180');

        $this->service->calculateStraightLineDistance(51.2064, -181, 52.2297, 21.0122);
    }

    public function test_haversine_distance_for_equator(): void
    {
        // Points on equator: (0, 0) to (0, 10)
        // ~1113 km per degree of longitude at equator
        $distance = $this->service->calculateHaversineDistance(0, 0, 0, 10);

        $this->assertGreaterThan(1100, $distance);
        $this->assertLessThan(1125, $distance);
    }

    public function test_straight_line_distance_for_equator(): void
    {
        // Points on equator: (0, 0) to (0, 10)
        $distance = $this->service->calculateStraightLineDistance(0, 0, 0, 10);

        $this->assertGreaterThan(1100, $distance);
        $this->assertLessThan(1125, $distance);
    }

    public function test_haversine_distance_returns_float_with_two_decimals(): void
    {
        $distance = $this->service->calculateHaversineDistance(51.2064, 15.9319, 52.2297, 21.0122);

        $this->assertIsFloat($distance);
        $this->assertEquals(round($distance, 2), $distance);
    }

    public function test_straight_line_distance_returns_float_with_two_decimals(): void
    {
        $distance = $this->service->calculateStraightLineDistance(51.2064, 15.9319, 52.2297, 21.0122);

        $this->assertIsFloat($distance);
        $this->assertEquals(round($distance, 2), $distance);
    }

    public function test_haversine_distance_with_negative_coordinates(): void
    {
        // Sydney, Australia: -33.8688, 151.2093
        // Melbourne, Australia: -37.8136, 144.9631
        $distance = $this->service->calculateHaversineDistance(-33.8688, 151.2093, -37.8136, 144.9631);

        $this->assertGreaterThan(700, $distance);
        $this->assertLessThan(750, $distance);
    }

    public function test_straight_line_distance_with_negative_coordinates(): void
    {
        // Sydney, Australia: -33.8688, 151.2093
        // Melbourne, Australia: -37.8136, 144.9631
        $distance = $this->service->calculateStraightLineDistance(-33.8688, 151.2093, -37.8136, 144.9631);

        $this->assertGreaterThan(700, $distance);
        $this->assertLessThan(750, $distance);
    }

    public function test_haversine_distance_with_negative_longitude(): void
    {
        // New York: 40.7128, -74.0060
        // Los Angeles: 34.0522, -118.2437
        $distance = $this->service->calculateHaversineDistance(40.7128, -74.0060, 34.0522, -118.2437);

        $this->assertGreaterThan(3900, $distance);
        $this->assertLessThan(4000, $distance);
    }

    public function test_straight_line_distance_with_negative_longitude(): void
    {
        // New York: 40.7128, -74.0060
        // Los Angeles: 34.0522, -118.2437
        $distance = $this->service->calculateStraightLineDistance(40.7128, -74.0060, 34.0522, -118.2437);

        $this->assertGreaterThan(3900, $distance);
        $this->assertLessThan(4000, $distance);
    }

    public function test_haversine_distance_at_latitude_boundaries(): void
    {
        // North Pole to Equator
        $distance = $this->service->calculateHaversineDistance(90, 0, 0, 0);

        $this->assertGreaterThan(9980, $distance);
        $this->assertLessThan(10020, $distance);
    }

    public function test_straight_line_distance_at_latitude_boundaries(): void
    {
        // North Pole to Equator
        $distance = $this->service->calculateStraightLineDistance(90, 0, 0, 0);

        $this->assertGreaterThan(9980, $distance);
        $this->assertLessThan(10020, $distance);
    }

    public function test_haversine_distance_at_longitude_boundaries(): void
    {
        // Points at 180 and -180 longitude (same meridian)
        $distance = $this->service->calculateHaversineDistance(0, 180, 0, -180);

        $this->assertEquals(0.0, $distance);
    }
}
