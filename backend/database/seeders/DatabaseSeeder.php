<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

    /**
     * Seed demo user 1 with a trip from Legnica to Mallorca.
     */
    private function seedDemoUser1WithTrip(): void
    {
        if (User::where('email', 'test1@gmail.com')->exists()) {
            return;
        }

        $user = User::factory()->create([
            'name' => 'Użytkownik Testowy 1',
            'email' => 'test1@gmail.com',
            'password' => Hash::make('haslo123'),
            'email_verified_at' => now(),
            'profile_visibility' => 'public',
        ]);

        $trip = $user->trips()->create([
            'name' => 'Podróż na Majorkę',
            'start_date' => '2024-06-01',
            'end_date' => '2024-06-10',
            'description' => 'Wycieczka z Legnicy na Majorkę przez Berlin',
        ]);

        $places = [
            ['name' => 'Legnica', 'latitude' => 51.2076, 'longitude' => 15.9425, 'country_code' => 'PL', 'position' => 1],
            ['name' => 'Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050, 'country_code' => 'DE', 'position' => 2],
            ['name' => 'Palma de Mallorca', 'latitude' => 39.5694, 'longitude' => 2.6502, 'country_code' => 'ES', 'position' => 3],
            ['name' => 'Valldemossa', 'latitude' => 39.7150, 'longitude' => 2.6250, 'country_code' => 'ES', 'position' => 4],
            ['name' => 'Sóller', 'latitude' => 39.7650, 'longitude' => 2.7100, 'country_code' => 'ES', 'position' => 5],
            ['name' => 'Formentor', 'latitude' => 39.9167, 'longitude' => 3.1333, 'country_code' => 'ES', 'position' => 6],
            ['name' => 'Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050, 'country_code' => 'DE', 'position' => 7],
            ['name' => 'Legnica', 'latitude' => 51.2076, 'longitude' => 15.9425, 'country_code' => 'PL', 'position' => 8],
        ];

        $createdPlaces = [];
        foreach ($places as $placeData) {
            $createdPlaces[] = $trip->places()->create($placeData);
        }

        $legs = [
            ['from_place_id' => $createdPlaces[0]->id, 'to_place_id' => $createdPlaces[1]->id, 'transport' => 'car', 'position' => 1],
            ['from_place_id' => $createdPlaces[1]->id, 'to_place_id' => $createdPlaces[2]->id, 'transport' => 'plane', 'position' => 2],
            ['from_place_id' => $createdPlaces[2]->id, 'to_place_id' => $createdPlaces[3]->id, 'transport' => 'car', 'position' => 3],
            ['from_place_id' => $createdPlaces[3]->id, 'to_place_id' => $createdPlaces[4]->id, 'transport' => 'car', 'position' => 4],
            ['from_place_id' => $createdPlaces[4]->id, 'to_place_id' => $createdPlaces[5]->id, 'transport' => 'car', 'position' => 5],
            ['from_place_id' => $createdPlaces[2]->id, 'to_place_id' => $createdPlaces[6]->id, 'transport' => 'plane', 'position' => 6],
            ['from_place_id' => $createdPlaces[6]->id, 'to_place_id' => $createdPlaces[7]->id, 'transport' => 'car', 'position' => 7],
        ];

        foreach ($legs as $legData) {
            $trip->legs()->create($legData);
        }
    }

    /**
     * Seed demo user 2 without trips.
     */
    private function seedDemoUser2(): void
    {
        if (User::where('email', 'test2@gmail.com')->exists()) {
            return;
        }

        User::factory()->create([
            'name' => 'Użytkownik Testowy 2',
            'email' => 'test2@gmail.com',
            'password' => Hash::make('haslo123'),
            'email_verified_at' => now(),
            'profile_visibility' => 'public',
        ]);
    }
}
