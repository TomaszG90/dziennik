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
        return [
            'transport' => fake()->randomElement(['car', 'plane', 'train', 'other']),
            'distance' => fake()->randomFloat(2, 0, 5000),
            'position' => fake()->numberBetween(1, 100),
        ];
    }
}
