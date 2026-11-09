<?php

namespace Database\Factories;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leg>
 */
class LegFactory extends Factory
{
    public function definition(): array
    {
        $trip = Trip::factory()->create();
        $place1 = Place::factory()->create(['trip_id' => $trip->id, 'position' => 1]);
        $place2 = Place::factory()->create(['trip_id' => $trip->id, 'position' => 2]);

        return [
            'trip_id' => $trip->id,
            'from_place_id' => $place1->id,
            'to_place_id' => $place2->id,
            'transport' => fake()->randomElement(['car', 'plane', 'train', 'other']),
            'distance' => fake()->randomFloat(2, 0, 5000),
            'position' => 1,
        ];
    }
}
