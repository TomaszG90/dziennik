<?php

namespace Database\Seeders;

use App\Models\Place;
use App\Models\User;
use App\Services\DistanceService;
use App\Services\LegCalculationService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Password shared by both demo accounts.
     */
    private const DEMO_PASSWORD = 'haslo123';

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

        DB::transaction(function (): void {
            $this->seedDemoUser1WithTrip();
            $this->seedDemoUser2WithTrip();
        });
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
        $user = $this->seedDemoUser([
            'name' => 'Użytkownik Testowy 1',
            'email' => 'test1@gmail.com',
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'city' => 'Legnica',
            'birth_date' => '1990-05-14',
            'avatar_path' => '1.png',
            'profile_visibility' => 'public',
        ]);

        if ($user->trips()->exists()) {
            return;
        }

        $this->createDemoTrip(
            $user,
            [
                'name' => 'Podróż na Majorkę',
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-10',
                'description' => 'Wycieczka z Legnicy na Majorkę przez Berlin',
            ],
            [
                ['name' => 'Legnica', 'latitude' => 51.2076, 'longitude' => 15.9425, 'country_code' => 'PL'],
                ['name' => 'Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050, 'country_code' => 'DE'],
                ['name' => 'Palma de Mallorca', 'latitude' => 39.5694, 'longitude' => 2.6502, 'country_code' => 'ES'],
                ['name' => 'Valldemossa', 'latitude' => 39.7150, 'longitude' => 2.6250, 'country_code' => 'ES'],
                ['name' => 'Sóller', 'latitude' => 39.7650, 'longitude' => 2.7100, 'country_code' => 'ES'],
                ['name' => 'Formentor', 'latitude' => 39.9167, 'longitude' => 3.1333, 'country_code' => 'ES'],
                ['name' => 'Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050, 'country_code' => 'DE'],
                ['name' => 'Legnica', 'latitude' => 51.2076, 'longitude' => 15.9425, 'country_code' => 'PL'],
            ],
            [
                ['from' => 1, 'to' => 2, 'transport' => 'car'],
                ['from' => 2, 'to' => 3, 'transport' => 'plane'],
                ['from' => 3, 'to' => 4, 'transport' => 'car'],
                ['from' => 4, 'to' => 5, 'transport' => 'car'],
                ['from' => 5, 'to' => 6, 'transport' => 'car'],
                ['from' => 6, 'to' => 3, 'transport' => 'car'],
                ['from' => 3, 'to' => 7, 'transport' => 'plane'],
                ['from' => 7, 'to' => 8, 'transport' => 'car'],
            ]
        );
    }

    /**
     * Seed demo user 2 with a shorter trip in another year.
     */
    private function seedDemoUser2WithTrip(): void
    {
        $user = $this->seedDemoUser([
            'name' => 'Użytkownik Testowy 2',
            'email' => 'test2@gmail.com',
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'city' => 'Wrocław',
            'birth_date' => '1988-11-02',
            'avatar_path' => '2.png',
            'profile_visibility' => 'private',
        ]);

        if ($user->trips()->exists()) {
            return;
        }

        $this->createDemoTrip(
            $user,
            [
                'name' => 'Weekend w Krakowie',
                'start_date' => '2025-03-14',
                'end_date' => '2025-03-16',
                'description' => 'Krótki wyjazd pociągiem',
            ],
            [
                ['name' => 'Wrocław', 'latitude' => 51.1079, 'longitude' => 17.0385, 'country_code' => 'PL'],
                ['name' => 'Kraków', 'latitude' => 50.0647, 'longitude' => 19.9450, 'country_code' => 'PL'],
                ['name' => 'Wieliczka', 'latitude' => 49.9867, 'longitude' => 20.0643, 'country_code' => 'PL'],
                ['name' => 'Wrocław', 'latitude' => 51.1079, 'longitude' => 17.0385, 'country_code' => 'PL'],
            ],
            [
                ['from' => 1, 'to' => 2, 'transport' => 'train'],
                ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ['from' => 3, 'to' => 4, 'transport' => 'train'],
            ]
        );
    }

    /**
     * Create or refresh a demo account, so seeding is idempotent and repairs
     * rows created before the profile columns were filled in.
     *
     * The plain password is hashed by the `hashed` cast on the model.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedDemoUser(array $attributes): User
    {
        return User::updateOrCreate(
            ['email' => $attributes['email']],
            [
                ...$attributes,
                'password' => self::DEMO_PASSWORD,
                'email_verified_at' => now(),
                'privacy_settings' => User::DEFAULT_PRIVACY_SETTINGS,
            ]
        );
    }

    /**
     * Create a trip with its places and legs.
     *
     * @param  array<string, mixed>  $tripAttributes
     * @param  list<array{name: string, latitude: float, longitude: float, country_code: string}>  $places
     * @param  list<array{from: int, to: int, transport: string}>  $legs
     */
    private function createDemoTrip(
        User $user,
        array $tripAttributes,
        array $places,
        array $legs
    ): void {
        $trip = $user->trips()->create($tripAttributes);

        $createdPlaces = [];

        foreach ($places as $index => $place) {
            $createdPlaces[$index + 1] = $trip->places()->create([
                ...$place,
                'position' => $index + 1,
            ]);
        }

        foreach ($legs as $index => $leg) {
            $from = $createdPlaces[$leg['from']];
            $to = $createdPlaces[$leg['to']];

            $trip->legs()->create([
                'from_place_id' => $from->id,
                'to_place_id' => $to->id,
                'transport' => $leg['transport'],
                ...$this->legAttributes($from, $to, $leg['transport']),
                'position' => $index + 1,
            ]);
        }
    }

    /**
     * Distance and geometry for a demo leg.
     *
     * Seeding never calls an external provider: flights use the same haversine
     * distance and arc as the API, while car and other legs are approximated
     * locally with a straight line. A car distance computed this way is a
     * haversine value until the leg is recalculated through the routes API.
     *
     * @return array{
     *     distance: float,
     *     geometry: array{type: string, coordinates: list<array{0: float, 1: float}>}
     * }
     */
    private function legAttributes(Place $from, Place $to, string $transport): array
    {
        if ($transport === 'plane') {
            return app(LegCalculationService::class)->calculate($from, $to, $transport);
        }

        $fromLatitude = (float) $from->latitude;
        $fromLongitude = (float) $from->longitude;
        $toLatitude = (float) $to->latitude;
        $toLongitude = (float) $to->longitude;

        $distanceService = app(DistanceService::class);

        $distance = $transport === 'car'
            ? $distanceService->calculateHaversineDistance(
                $fromLatitude,
                $fromLongitude,
                $toLatitude,
                $toLongitude
            )
            : $distanceService->calculateStraightLineDistance(
                $fromLatitude,
                $fromLongitude,
                $toLatitude,
                $toLongitude
            );

        return [
            'distance' => $distance,
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [$fromLongitude, $fromLatitude],
                    [$toLongitude, $toLatitude],
                ],
            ],
        ];
    }
}
