<?php

namespace Tests\Unit;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceTest extends TestCase
{
    use RefreshDatabase;

    private function createTrip(): Trip
    {
        $user = User::factory()->create();

        return $user->trips()->create([
            'name' => 'Test trip',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'description' => 'Trip for Place tests',
        ]);
    }

    private function createPlace(Trip $trip, array $attributes = []): Place
    {
        return Place::create(array_merge([
            'trip_id' => $trip->id,
            'name' => 'Test place',
            'latitude' => 51.1234567,
            'longitude' => 16.1234567,
            'position' => 1,
            'country_code' => 'PL',
        ], $attributes));
    }

    public function test_place_belongs_to_trip(): void
    {
        $trip = $this->createTrip();
        $place = $this->createPlace($trip);

        $this->assertInstanceOf(Trip::class, $place->trip);
        $this->assertEquals($trip->id, $place->trip->id);
    }

    public function test_place_has_outgoing_and_incoming_legs(): void
    {
        $trip = $this->createTrip();
        $place = $this->createPlace($trip, ['position' => 1]);
        $destination = $this->createPlace($trip, [
            'name' => 'Destination',
            'position' => 2,
        ]);

        $outgoingLeg = Leg::create([
            'trip_id' => $trip->id,
            'from_place_id' => $place->id,
            'to_place_id' => $destination->id,
            'transport' => 'car',
            'distance' => 12.50,
            'position' => 1,
        ]);

        $incomingLeg = Leg::create([
            'trip_id' => $trip->id,
            'from_place_id' => $destination->id,
            'to_place_id' => $place->id,
            'transport' => 'train',
            'distance' => 20.00,
            'position' => 2,
        ]);

        $this->assertTrue($place->outgoingLegs->contains('id', $outgoingLeg->id));
        $this->assertTrue($place->incomingLegs->contains('id', $incomingLeg->id));
    }

    public function test_place_attributes_are_persisted_and_cast_correctly(): void
    {
        $trip = $this->createTrip();
        $place = $this->createPlace($trip);

        $place->refresh();

        $this->assertDatabaseHas('places', [
            'id' => $place->id,
            'trip_id' => $trip->id,
            'name' => 'Test place',
            'country_code' => 'PL',
            'position' => 1,
        ]);

        $this->assertIsInt($place->position);
        $this->assertIsString($place->latitude);
        $this->assertIsString($place->longitude);
        $this->assertEquals('51.1234567', $place->latitude);
        $this->assertEquals('16.1234567', $place->longitude);
    }
}
