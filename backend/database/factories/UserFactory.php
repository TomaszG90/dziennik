<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'profile_visibility' => fake()->randomElement(['public', 'private']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'city' => fake()->city(),
            'birth_date' => fake()->date(),
            'avatar_path' => null,
            'privacy_settings' => User::DEFAULT_PRIVACY_SETTINGS,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user's profile is public.
     */
    public function publicProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the user's profile is private.
     */
    public function privateProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_visibility' => 'private',
        ]);
    }
}
