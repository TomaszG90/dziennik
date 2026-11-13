<?php

namespace Database\Seeders;

use App\Exceptions\RoutingProviderException;
use App\Models\FriendRequest;
use App\Models\Place;
use App\Models\TripInvitation;
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
            $this->seedAdditionalUsers();
            $this->seedAdditionalTrips();
            $this->seedFriendRelationships();
            $this->seedTripInvitations();
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
     * Seed 25 public users with random privacy settings and 5 private users.
     */
    private function seedAdditionalUsers(): void
    {
        // Skip if already seeded (check by count)
        $additionalPublicCount = User::where('email', 'like', 'user_%@demo.com')
            ->where('profile_visibility', 'public')
            ->count();
        $additionalPrivateCount = User::where('email', 'like', 'private_%@demo.com')
            ->where('profile_visibility', 'private')
            ->count();

        if ($additionalPublicCount >= 25 && $additionalPrivateCount >= 5) {
            return;
        }

        // Create 25 public users with random privacy settings
        for ($i = 1; $i <= 25 && $additionalPublicCount < 25; $i++) {
            User::factory()->publicProfile()->create([
                'email' => "user_{$i}@demo.com",
                'privacy_settings' => $this->randomPrivacySettings(),
            ]);
            $additionalPublicCount++;
        }

        // Create 5 private users
        for ($i = 1; $i <= 5 && $additionalPrivateCount < 5; $i++) {
            User::factory()->privateProfile()->create([
                'email' => "private_{$i}@demo.com",
            ]);
            $additionalPrivateCount++;
        }
    }

    /**
     * Generate random privacy settings with some fields enabled.
     *
     * @return array<string, bool>
     */
    private function randomPrivacySettings(): array
    {
        $settings = User::DEFAULT_PRIVACY_SETTINGS;

        // Randomly enable some fields for public users
        foreach ($settings as $key => $value) {
            $settings[$key] = fake()->boolean(70); // 70% chance to enable each field
        }

        return $settings;
    }

    /**
     * Seed 10 additional trips for test1 and test2 users.
     */
    private function seedAdditionalTrips(): void
    {
        $user1 = User::where('email', 'test1@gmail.com')->first();
        $user2 = User::where('email', 'test2@gmail.com')->first();

        if (! $user1 || ! $user2) {
            return;
        }

        // Skip if already seeded
        if ($user1->trips()->count() >= 11 && $user2->trips()->count() >= 11) {
            return;
        }

        // 10 trips for test1
        $tripsForUser1 = [
            [
                'name' => 'Wakacje we Włoszech',
                'start_date' => '2024-07-01',
                'end_date' => '2024-07-14',
                'description' => 'Podróż po Włoszech',
                'places' => [
                    ['name' => 'Mediolan', 'latitude' => 45.4642, 'longitude' => 9.1900, 'country_code' => 'IT'],
                    ['name' => 'Rzym', 'latitude' => 41.9028, 'longitude' => 12.4964, 'country_code' => 'IT'],
                    ['name' => 'Neapol', 'latitude' => 40.8518, 'longitude' => 14.2681, 'country_code' => 'IT'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Weekend w Pradze',
                'start_date' => '2024-08-10',
                'end_date' => '2024-08-12',
                'description' => 'Krótki wyjazd do Pragi',
                'places' => [
                    ['name' => 'Wrocław', 'latitude' => 51.1079, 'longitude' => 17.0385, 'country_code' => 'PL'],
                    ['name' => 'Praga', 'latitude' => 50.0755, 'longitude' => 14.4378, 'country_code' => 'CZ'],
                    ['name' => 'Wrocław', 'latitude' => 51.1079, 'longitude' => 17.0385, 'country_code' => 'PL'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Noclegi w Alpach',
                'start_date' => '2024-09-05',
                'end_date' => '2024-09-12',
                'description' => 'Wspinaczka w Alpach',
                'places' => [
                    ['name' => 'Innsbruck', 'latitude' => 47.2692, 'longitude' => 11.4041, 'country_code' => 'AT'],
                    ['name' => 'Zürich', 'latitude' => 47.3769, 'longitude' => 8.5417, 'country_code' => 'CH'],
                    ['name' => 'Genua', 'latitude' => 44.4056, 'longitude' => 8.9463, 'country_code' => 'IT'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'train'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Podróż do Japonii',
                'start_date' => '2025-03-01',
                'end_date' => '2025-03-15',
                'description' => 'Odkrywanie Japonii',
                'places' => [
                    ['name' => 'Tokio', 'latitude' => 35.6762, 'longitude' => 139.6503, 'country_code' => 'JP'],
                    ['name' => 'Kioto', 'latitude' => 35.0116, 'longitude' => 135.7681, 'country_code' => 'JP'],
                    ['name' => 'Osaka', 'latitude' => 34.6937, 'longitude' => 135.5023, 'country_code' => 'JP'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'train'],
                    ['from' => 2, 'to' => 3, 'transport' => 'train'],
                ],
            ],
            [
                'name' => 'Wyprawa do Norwegii',
                'start_date' => '2025-06-01',
                'end_date' => '2025-06-10',
                'description' => 'Fjordy norweskie',
                'places' => [
                    ['name' => 'Oslo', 'latitude' => 59.9139, 'longitude' => 10.7522, 'country_code' => 'NO'],
                    ['name' => 'Bergen', 'latitude' => 60.3913, 'longitude' => 5.3221, 'country_code' => 'NO'],
                    ['name' => 'Tromsø', 'latitude' => 69.6492, 'longitude' => 18.9553, 'country_code' => 'NO'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'plane'],
                ],
            ],
            [
                'name' => 'Weekend w Paryżu',
                'start_date' => '2024-11-15',
                'end_date' => '2024-11-17',
                'description' => 'Romantyczny weekend',
                'places' => [
                    ['name' => 'Bruksela', 'latitude' => 50.8503, 'longitude' => 4.3517, 'country_code' => 'BE'],
                    ['name' => 'Paryż', 'latitude' => 48.8566, 'longitude' => 2.3522, 'country_code' => 'FR'],
                    ['name' => 'Bruksela', 'latitude' => 50.8503, 'longitude' => 4.3517, 'country_code' => 'BE'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'train'],
                    ['from' => 2, 'to' => 3, 'transport' => 'train'],
                ],
            ],
            [
                'name' => 'Podróż po Hiszpanii',
                'start_date' => '2025-04-01',
                'end_date' => '2025-04-14',
                'description' => 'Odkrywanie Hiszpanii',
                'places' => [
                    ['name' => 'Madryt', 'latitude' => 40.4168, 'longitude' => -3.7038, 'country_code' => 'ES'],
                    ['name' => 'Barcelona', 'latitude' => 41.3851, 'longitude' => 2.1734, 'country_code' => 'ES'],
                    ['name' => 'Walencja', 'latitude' => 39.4699, 'longitude' => -0.3763, 'country_code' => 'ES'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Wycieczka do Grecji',
                'start_date' => '2025-07-01',
                'end_date' => '2025-07-10',
                'description' => 'Greckie wyspy',
                'places' => [
                    ['name' => 'Ateny', 'latitude' => 37.9838, 'longitude' => 23.7275, 'country_code' => 'GR'],
                    ['name' => 'Santorini', 'latitude' => 36.3932, 'longitude' => 25.4615, 'country_code' => 'GR'],
                    ['name' => 'Mykonos', 'latitude' => 37.4467, 'longitude' => 25.3289, 'country_code' => 'GR'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'plane'],
                    ['from' => 2, 'to' => 3, 'transport' => 'plane'],
                ],
            ],
            [
                'name' => 'Podróż do USA',
                'start_date' => '2025-08-01',
                'end_date' => '2025-08-21',
                'description' => 'Wakacje w Ameryce',
                'places' => [
                    ['name' => 'Nowy Jork', 'latitude' => 40.7128, 'longitude' => -74.0060, 'country_code' => 'US'],
                    ['name' => 'Los Angeles', 'latitude' => 34.0522, 'longitude' => -118.2437, 'country_code' => 'US'],
                    ['name' => 'San Francisco', 'latitude' => 37.7749, 'longitude' => -122.4194, 'country_code' => 'US'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'plane'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Weekend w Londynie',
                'start_date' => '2024-12-20',
                'end_date' => '2024-12-22',
                'description' => 'Świąteczny Londyn',
                'places' => [
                    ['name' => 'Amsterdam', 'latitude' => 52.3676, 'longitude' => 4.9041, 'country_code' => 'NL'],
                    ['name' => 'Londyn', 'latitude' => 51.5074, 'longitude' => -0.1278, 'country_code' => 'GB'],
                    ['name' => 'Amsterdam', 'latitude' => 52.3676, 'longitude' => 4.9041, 'country_code' => 'NL'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'train'],
                    ['from' => 2, 'to' => 3, 'transport' => 'train'],
                ],
            ],
        ];

        // 10 trips for test2
        $tripsForUser2 = [
            [
                'name' => 'Podróż do Czech',
                'start_date' => '2024-05-01',
                'end_date' => '2024-05-05',
                'description' => 'Weekend w Czechach',
                'places' => [
                    ['name' => 'Wrocław', 'latitude' => 51.1079, 'longitude' => 17.0385, 'country_code' => 'PL'],
                    ['name' => 'Praga', 'latitude' => 50.0755, 'longitude' => 14.4378, 'country_code' => 'CZ'],
                    ['name' => 'Czeski Krumau', 'latitude' => 48.7964, 'longitude' => 14.3289, 'country_code' => 'CZ'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Wycieczka do Słowacji',
                'start_date' => '2024-06-15',
                'end_date' => '2024-06-18',
                'description' => 'Tatry słowackie',
                'places' => [
                    ['name' => 'Kraków', 'latitude' => 50.0647, 'longitude' => 19.9450, 'country_code' => 'PL'],
                    ['name' => 'Poprad', 'latitude' => 49.0614, 'longitude' => 20.2910, 'country_code' => 'SK'],
                    ['name' => 'Banska Bystrzyca', 'latitude' => 48.7371, 'longitude' => 19.1465, 'country_code' => 'SK'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Podróż do Austrii',
                'start_date' => '2024-07-20',
                'end_date' => '2024-07-25',
                'description' => 'Wakacje w Austrii',
                'places' => [
                    ['name' => 'Wiedeń', 'latitude' => 48.2082, 'longitude' => 16.3738, 'country_code' => 'AT'],
                    ['name' => 'Salzburg', 'latitude' => 47.8095, 'longitude' => 13.0550, 'country_code' => 'AT'],
                    ['name' => 'Hallstatt', 'latitude' => 47.5622, 'longitude' => 13.6493, 'country_code' => 'AT'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Weekend w Berlinie',
                'start_date' => '2024-09-01',
                'end_date' => '2024-09-03',
                'description' => 'Krótki wyjazd do Berlina',
                'places' => [
                    ['name' => 'Poznań', 'latitude' => 52.4064, 'longitude' => 16.9252, 'country_code' => 'PL'],
                    ['name' => 'Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050, 'country_code' => 'DE'],
                    ['name' => 'Poznań', 'latitude' => 52.4064, 'longitude' => 16.9252, 'country_code' => 'PL'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'train'],
                    ['from' => 2, 'to' => 3, 'transport' => 'train'],
                ],
            ],
            [
                'name' => 'Podróż do Portugalii',
                'start_date' => '2025-05-01',
                'end_date' => '2025-05-10',
                'description' => 'Odkrywanie Portugalii',
                'places' => [
                    ['name' => 'Lizbona', 'latitude' => 38.7223, 'longitude' => -9.1393, 'country_code' => 'PT'],
                    ['name' => 'Porto', 'latitude' => 41.1579, 'longitude' => -8.6291, 'country_code' => 'PT'],
                    ['name' => 'Sintra', 'latitude' => 38.7970, 'longitude' => -9.3860, 'country_code' => 'PT'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Wycieczka do Danii',
                'start_date' => '2025-06-15',
                'end_date' => '2025-06-20',
                'description' => 'Kopenhaga i okolice',
                'places' => [
                    ['name' => 'Hamburg', 'latitude' => 53.5511, 'longitude' => 9.9937, 'country_code' => 'DE'],
                    ['name' => 'Kopenhaga', 'latitude' => 55.6761, 'longitude' => 12.5683, 'country_code' => 'DK'],
                    ['name' => 'Malmö', 'latitude' => 55.6050, 'longitude' => 13.0038, 'country_code' => 'SE'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'train'],
                    ['from' => 2, 'to' => 3, 'transport' => 'train'],
                ],
            ],
            [
                'name' => 'Podróż do Chorwacji',
                'start_date' => '2025-07-01',
                'end_date' => '2025-07-10',
                'description' => 'Chorwackie wybrzeże',
                'places' => [
                    ['name' => 'Zagrzeb', 'latitude' => 45.8150, 'longitude' => 15.9819, 'country_code' => 'HR'],
                    ['name' => 'Split', 'latitude' => 43.5081, 'longitude' => 16.4402, 'country_code' => 'HR'],
                    ['name' => 'Dubrownik', 'latitude' => 42.6384, 'longitude' => 18.1081, 'country_code' => 'HR'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Weekend w Wiedniu',
                'start_date' => '2024-10-01',
                'end_date' => '2024-10-03',
                'description' => 'Krótki wyjazd do Wiednia',
                'places' => [
                    ['name' => 'Bratysława', 'latitude' => 48.1486, 'longitude' => 17.1077, 'country_code' => 'SK'],
                    ['name' => 'Wiedeń', 'latitude' => 48.2082, 'longitude' => 16.3738, 'country_code' => 'AT'],
                    ['name' => 'Bratysława', 'latitude' => 48.1486, 'longitude' => 17.1077, 'country_code' => 'SK'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'train'],
                    ['from' => 2, 'to' => 3, 'transport' => 'train'],
                ],
            ],
            [
                'name' => 'Podróż do Bułgarii',
                'start_date' => '2025-08-01',
                'end_date' => '2025-08-10',
                'description' => 'Bułgarskie wybrzeże',
                'places' => [
                    ['name' => 'Sofia', 'latitude' => 42.6977, 'longitude' => 23.3219, 'country_code' => 'BG'],
                    ['name' => 'Płowdiw', 'latitude' => 42.1718, 'longitude' => 24.7778, 'country_code' => 'BG'],
                    ['name' => 'Warna', 'latitude' => 43.2141, 'longitude' => 27.9147, 'country_code' => 'BG'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
            [
                'name' => 'Wycieczka do Rumunii',
                'start_date' => '2025-09-01',
                'end_date' => '2025-09-07',
                'description' => 'Transylwania i okolice',
                'places' => [
                    ['name' => 'Bukareszt', 'latitude' => 44.4268, 'longitude' => 26.1025, 'country_code' => 'RO'],
                    ['name' => 'Sibiu', 'latitude' => 45.7983, 'longitude' => 24.1256, 'country_code' => 'RO'],
                    ['name' => 'Brasov', 'latitude' => 45.5960, 'longitude' => 25.5897, 'country_code' => 'RO'],
                ],
                'legs' => [
                    ['from' => 1, 'to' => 2, 'transport' => 'car'],
                    ['from' => 2, 'to' => 3, 'transport' => 'car'],
                ],
            ],
        ];

        // Create trips for user1
        foreach ($tripsForUser1 as $tripData) {
            $this->createDemoTrip(
                $user1,
                [
                    'name' => $tripData['name'],
                    'start_date' => $tripData['start_date'],
                    'end_date' => $tripData['end_date'],
                    'description' => $tripData['description'],
                ],
                $tripData['places'],
                $tripData['legs']
            );
        }

        // Create trips for user2
        foreach ($tripsForUser2 as $tripData) {
            $this->createDemoTrip(
                $user2,
                [
                    'name' => $tripData['name'],
                    'start_date' => $tripData['start_date'],
                    'end_date' => $tripData['end_date'],
                    'description' => $tripData['description'],
                ],
                $tripData['places'],
                $tripData['legs']
            );
        }
    }

    /**
     * Seed friend request relationships between demo users.
     */
    private function seedFriendRelationships(): void
    {
        $user1 = User::where('email', 'test1@gmail.com')->first();
        $user2 = User::where('email', 'test2@gmail.com')->first();

        if (! $user1 || ! $user2) {
            return;
        }

        FriendRequest::firstOrCreate(
            ['sender_id' => $user1->id, 'recipient_id' => $user2->id],
            ['status' => 'accepted']
        );
    }

    /**
     * Seed trip invitations between demo users.
     */
    private function seedTripInvitations(): void
    {
        $user1 = User::where('email', 'test1@gmail.com')->first();
        $user2 = User::where('email', 'test2@gmail.com')->first();

        if (! $user1 || ! $user2) {
            return;
        }

        $trip = $user1->trips()->first();

        if (! $trip) {
            return;
        }

        TripInvitation::firstOrCreate(
            [
                'trip_id' => $trip->id,
                'inviter_id' => $user1->id,
                'invitee_id' => $user2->id,
            ],
            ['status' => 'accepted']
        );
    }

    /**
     * Distance and geometry for a demo leg.
     *
     * For car and plane transport the seeder delegates to LegCalculationService,
     * which calls the routing API for cars and computes a haversine arc for flights.
     * Other transports (train, walking, etc.) fall back to a straight-line
     * approximation because no routing provider is configured for them.
     *
     * If the routing API is unavailable, car legs also fall back to a straight line.
     *
     * @return array{
     *     distance: float,
     *     geometry: array{type: string, coordinates: list<array{0: float, 1: float}>}
     * }
     */
    private function legAttributes(Place $from, Place $to, string $transport): array
    {
        if (in_array($transport, ['car', 'plane'], true)) {
            try {
                return app(LegCalculationService::class)->calculate($from, $to, $transport);
            } catch (RoutingProviderException $e) {
                // Routing API unavailable; fall back to straight-line approximation.
            }
        }

        $fromLatitude = (float) $from->latitude;
        $fromLongitude = (float) $from->longitude;
        $toLatitude = (float) $to->latitude;
        $toLongitude = (float) $to->longitude;

        $distanceService = app(DistanceService::class);

        $distance = $distanceService->calculateStraightLineDistance(
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
