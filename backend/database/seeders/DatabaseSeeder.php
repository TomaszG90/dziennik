<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'profile_visibility' => 'private',
        ]);

        $this->seedUser([
            'name' => 'Public User',
            'email' => 'public@example.com',
            'profile_visibility' => 'public',
        ]);
    }

    /**
     * Create the demo user unless it already exists, so seeding is idempotent.
     *
     * @param  array{name: string, email: string, profile_visibility: string}  $attributes
     */
    private function seedUser(array $attributes): void
    {
        if (User::where('email', $attributes['email'])->exists()) {
            return;
        }

        User::factory()->create($attributes);
    }
}
