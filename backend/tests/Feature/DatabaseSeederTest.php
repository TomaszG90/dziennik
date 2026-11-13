<?php

namespace Tests\Feature;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_test_user_with_private_visibility(): void
    {
        $this->seed();

        $testUser = User::where('email', 'test@example.com')->first();

        $this->assertNotNull($testUser);
        $this->assertEquals('private', $testUser->profile_visibility);
    }

    public function test_seeder_creates_public_user_with_public_visibility(): void
    {
        $this->seed();

        $publicUser = User::where('email', 'public@example.com')->first();

        $this->assertNotNull($publicUser);
        $this->assertEquals('public', $publicUser->profile_visibility);
    }

    public function test_seeder_creates_complete_demo_profiles(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.heigit.org/openrouteservice/v2/directions/*' => Http::response([], 503),
        ]);

        $this->seed();

        foreach (['test1@gmail.com', 'test2@gmail.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            $this->assertTrue(Hash::check('haslo123', $user->password));
            $this->assertNotNull($user->email_verified_at);
            $this->assertNotNull($user->first_name);
            $this->assertNotNull($user->last_name);
            $this->assertNotNull($user->city);
            $this->assertNotNull($user->birth_date);
            $this->assertContains($user->avatar_path, [
                '1.png', '2.png', '3.png', '4.png', '5.png',
                '6.png', '7.png', '8.png', '9.png', '10.png',
            ]);
            $this->assertSame(User::DEFAULT_PRIVACY_SETTINGS, $user->privacy_settings);
        }

        $this->assertSame('public', User::where('email', 'test1@gmail.com')->value('profile_visibility'));
        $this->assertSame('private', User::where('email', 'test2@gmail.com')->value('profile_visibility'));
    }

    public function test_seeder_creates_demo_trip_from_legnica_to_mallorca(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.heigit.org/openrouteservice/v2/directions/*' => Http::response([], 503),
        ]);

        $this->seed();

        $user = User::where('email', 'test1@gmail.com')->firstOrFail();
        $trip = $user->trips()->where('name', 'Podróż na Majorkę')->firstOrFail();

        $this->assertSame('Podróż na Majorkę', $trip->name);
        $this->assertSame('2024-06-01', $trip->start_date->toDateString());
        $this->assertSame('2024-06-10', $trip->end_date->toDateString());

        $places = $trip->places()->get();
        $legs = $trip->legs()->get();

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $places->pluck('position')->all());
        $this->assertSame(
            ['PL', 'DE', 'ES', 'ES', 'ES', 'ES', 'DE', 'PL'],
            $places->pluck('country_code')->all()
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $legs->pluck('position')->all());
        $this->assertSame(
            ['car', 'plane', 'car', 'car', 'car', 'car', 'plane', 'car'],
            $legs->pluck('transport')->all()
        );

        foreach ($legs as $leg) {
            $this->assertNotNull($leg->distance);
            $this->assertGreaterThan(0, (float) $leg->distance);
            $this->assertSame('LineString', $leg->geometry['type']);
            $this->assertGreaterThanOrEqual(2, count($leg->geometry['coordinates']));
        }

        $planeLegs = $legs->where('transport', 'plane');

        $this->assertCount(2, $planeLegs);

        foreach ($planeLegs as $planeLeg) {
            $this->assertGreaterThan(2, count($planeLeg->geometry['coordinates']));
        }
    }

    public function test_seeder_creates_second_demo_trip_in_another_year(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.heigit.org/openrouteservice/v2/directions/*' => Http::response([], 503),
        ]);

        $this->seed();

        $user = User::where('email', 'test2@gmail.com')->firstOrFail();
        $trip = $user->trips()->where('name', 'Weekend w Krakowie')->firstOrFail();

        $this->assertSame('Weekend w Krakowie', $trip->name);
        $this->assertSame(2025, $trip->start_date->year);
        $this->assertCount(4, $trip->places()->get());
        $this->assertContains('train', $trip->legs()->get()->pluck('transport')->all());

        foreach ($trip->legs()->get() as $leg) {
            $this->assertNotNull($leg->distance);
            $this->assertGreaterThan(0, (float) $leg->distance);
            $this->assertSame('LineString', $leg->geometry['type']);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        Http::fake([
            'api.heigit.org/openrouteservice/v2/directions/*' => Http::response([], 503),
        ]);

        $this->seed();
        $this->seed();

        $this->assertSame(1, User::where('email', 'test@example.com')->count());
        $this->assertSame(1, User::where('email', 'public@example.com')->count());
        $this->assertSame(1, User::where('email', 'test1@gmail.com')->count());
        $this->assertSame(1, User::where('email', 'test2@gmail.com')->count());
        $this->assertSame(34, User::count());
        $this->assertSame(22, Trip::count());
    }

    public function test_seeder_repairs_demo_account_without_profile_data(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.heigit.org/openrouteservice/v2/directions/*' => Http::response([], 503),
        ]);

        $stale = User::factory()->create([
            'email' => 'test1@gmail.com',
            'first_name' => null,
            'last_name' => null,
            'city' => null,
            'birth_date' => null,
        ]);

        $this->seed();

        $stale->refresh();

        $this->assertNotNull($stale->first_name);
        $this->assertNotNull($stale->last_name);
        $this->assertNotNull($stale->city);
        $this->assertNotNull($stale->birth_date);
        $mallorcaTrip = $stale->trips()->where('name', 'Podróż na Majorkę')->firstOrFail();
        $this->assertSame(8, $mallorcaTrip->places()->count());
    }
}
