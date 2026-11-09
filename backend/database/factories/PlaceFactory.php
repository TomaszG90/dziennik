<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'name' => fake()->city(),
            'latitude' => fake()->latitude(-90, 90),
            'longitude' => fake()->longitude(-180, 180),
            'position' => fake()->numberBetween(1, 100),
            'country_code' => fake()->countryCode(),
        ];
    }
}
