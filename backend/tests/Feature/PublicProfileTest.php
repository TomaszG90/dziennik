<?php

namespace Tests\Feature;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_users_endpoint_returns_only_public_profiles(): void
    {
        $publicUser = User::factory()->publicProfile()->create([
            'name' => 'Public User',
            'privacy_settings' => array_merge(
                User::DEFAULT_PRIVACY_SETTINGS,
                ['name' => true]
            ),
        ]);

        User::factory()->privateProfile()->create([
            'name' => 'Private User',
        ]);

        $response = $this->getJson('/api/v1/public/users');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $publicUser->id,
                'name' => 'Public User',
            ])
            ->assertJsonMissing([
                'name' => 'Private User',
            ]);
    }

    public function test_public_users_search_matches_only_fields_enabled_for_public_visibility(): void
    {
        $matchingUser = User::factory()->publicProfile()->create([
            'name' => 'Visible Name',
            'first_name' => 'Searchable',
            'privacy_settings' => array_merge(
                User::DEFAULT_PRIVACY_SETTINGS,
                ['firstName' => true]
            ),
        ]);

        User::factory()->publicProfile()->create([
            'name' => 'Hidden Searchable Name',
            'privacy_settings' => User::DEFAULT_PRIVACY_SETTINGS,
        ]);

        $response = $this->getJson('/api/v1/public/users?search=Searchable');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingUser->id)
            ->assertJsonPath('data.0.first_name', 'Searchable')
            ->assertJsonMissing(['name' => 'Hidden Searchable Name']);
    }

    public function test_public_profile_returns_allowed_user_data(): void
    {
        $user = User::factory()->publicProfile()->create([
            'name' => 'Public User',
            'email' => 'public@example.com',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'city' => 'Warsaw',
            'privacy_settings' => [
                'name' => true,
                'email' => false,
                'firstName' => true,
                'lastName' => true,
                'city' => true,
                'birthDate' => false,
                'avatar' => false,
            ],
        ]);

        $response = $this->getJson("/api/v1/public/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Public User')
            ->assertJsonPath('data.first_name', 'John')
            ->assertJsonPath('data.last_name', 'Smith')
            ->assertJsonPath('data.city', 'Warsaw')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.privacy_settings');
    }

    public function test_public_profile_hides_fields_disabled_in_privacy_settings(): void
    {
        $user = User::factory()->publicProfile()->create([
            'name' => 'Hidden Name',
            'first_name' => 'Hidden First Name',
            'last_name' => 'Hidden Last Name',
            'city' => 'Hidden City',
            'birth_date' => '1990-01-01',
            'avatar_path' => '1.png',
            'privacy_settings' => User::DEFAULT_PRIVACY_SETTINGS,
        ]);

        $response = $this->getJson("/api/v1/public/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.name')
            ->assertJsonMissingPath('data.first_name')
            ->assertJsonMissingPath('data.last_name')
            ->assertJsonMissingPath('data.city')
            ->assertJsonMissingPath('data.birth_date')
            ->assertJsonMissingPath('data.avatar_path');
    }

    public function test_private_profile_returns_not_found(): void
    {
        $user = User::factory()->privateProfile()->create();

        $this->getJson("/api/v1/public/users/{$user->id}")
            ->assertNotFound();
    }

    public function test_nonexistent_profile_returns_not_found(): void
    {
        $this->getJson('/api/v1/public/users/999999')
            ->assertNotFound();
    }

    public function test_public_profile_returns_its_trips(): void
    {
        $user = User::factory()->publicProfile()->create();

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'name' => 'Public Trip',
        ]);

        $response = $this->getJson("/api/v1/public/users/{$user->id}/trips");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $trip->id,
                'name' => 'Public Trip',
            ]);
    }

    public function test_private_profile_trips_return_not_found(): void
    {
        $user = User::factory()->privateProfile()->create();

        $this->getJson("/api/v1/public/users/{$user->id}/trips")
            ->assertNotFound();
    }

    public function test_public_profile_map_returns_trips_places_and_legs(): void
    {
        $user = User::factory()->publicProfile()->create();

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'name' => 'Map Trip',
        ]);

        $fromPlace = Place::factory()->create([
            'trip_id' => $trip->id,
            'name' => 'Warsaw',
            'latitude' => 52.2297,
            'longitude' => 21.0122,
            'position' => 1,
            'country_code' => 'PL',
        ]);

        $toPlace = Place::factory()->create([
            'trip_id' => $trip->id,
            'name' => 'Berlin',
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'position' => 2,
            'country_code' => 'DE',
        ]);

        $leg = Leg::factory()->create([
            'trip_id' => $trip->id,
            'from_place_id' => $fromPlace->id,
            'to_place_id' => $toPlace->id,
            'transport' => 'car',
            'distance' => 575,
        ]);

        $response = $this->getJson("/api/v1/public/users/{$user->id}/map");

        $response->assertOk()
            ->assertJsonPath('data.trips.0.id', $trip->id)
            ->assertJsonPath('data.places.0.name', 'Warsaw')
            ->assertJsonPath('data.legs.0.id', $leg->id);
    }

    public function test_private_profile_map_returns_not_found(): void
    {
        $user = User::factory()->privateProfile()->create();

        $this->getJson("/api/v1/public/users/{$user->id}/map")
            ->assertNotFound();
    }

    public function test_public_profile_returns_its_statistics(): void
    {
        $user = User::factory()->publicProfile()->create();

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
        ]);

        $place = Place::factory()->create([
            'trip_id' => $trip->id,
            'country_code' => 'PL',
        ]);

        Leg::factory()->create([
            'trip_id' => $trip->id,
            'from_place_id' => $place->id,
            'to_place_id' => $place->id,
            'transport' => 'car',
            'distance' => 100,
        ]);

        $response = $this->getJson("/api/v1/public/users/{$user->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.trips_count', 1)
            ->assertJsonPath('data.countries', ['PL'])
            ->assertJsonPath('data.total_distance', 100)
            ->assertJsonPath('data.places_count', 1);
    }

    public function test_private_profile_statistics_return_not_found(): void
    {
        $user = User::factory()->privateProfile()->create();

        $this->getJson("/api/v1/public/users/{$user->id}/stats")
            ->assertNotFound();
    }

    public function test_public_endpoints_do_not_require_authentication(): void
    {
        $user = User::factory()->publicProfile()->create();

        $this->getJson('/api/v1/public/users')->assertOk();
        $this->getJson("/api/v1/public/users/{$user->id}")->assertOk();
        $this->getJson("/api/v1/public/users/{$user->id}/trips")->assertOk();
        $this->getJson("/api/v1/public/users/{$user->id}/map")->assertOk();
        $this->getJson("/api/v1/public/users/{$user->id}/stats")->assertOk();
    }
}
