<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaceApiTest extends TestCase
{
    use RefreshDatabase;

    private function createTrip(User $user): Trip
    {
        return $user->trips()->create([
            'name' => 'Test trip',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'description' => 'Test description',
        ]);
    }

    private function createPlace(Trip $trip, array $attributes = []): Place
    {
        return $trip->places()->create(array_merge([
            'name' => 'Test place',
            'latitude' => 51.1000000,
            'longitude' => 17.0000000,
            'position' => 1,
            'country_code' => 'PL',
        ], $attributes));
    }

    private function fakeReverseCountry(string $countryCode): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [
                    [
                        'properties' => ['countrycode' => $countryCode],
                    ],
                ],
            ]),
        ]);
    }

    public function test_user_can_list_places_for_their_trip_in_position_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $second = $this->createPlace($trip, ['name' => 'Second', 'position' => 2]);
        $first = $this->createPlace($trip, ['name' => 'First', 'position' => 1]);

        $response = $this->getJson("/api/v1/trips/{$trip->id}/places");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function test_user_can_create_a_place_for_their_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $this->createPlace($trip, ['position' => 1]);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Wrocław',
            'latitude' => 51.1079,
            'longitude' => 17.0385,
            'country_code' => 'PL',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Wrocław')
            ->assertJsonPath('data.position', 2);

        $this->assertDatabaseHas('places', [
            'trip_id' => $trip->id,
            'name' => 'Wrocław',
            'position' => 2,
        ]);
    }

    public function test_user_can_show_update_and_delete_a_place(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $this->getJson("/api/v1/trips/{$trip->id}/places/{$place->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $place->id);

        $this->patchJson("/api/v1/trips/{$trip->id}/places/{$place->id}", [
            'name' => 'Updated place',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated place');

        $this->deleteJson("/api/v1/trips/{$trip->id}/places/{$place->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('places', ['id' => $place->id]);
    }

    public function test_user_cannot_access_another_users_trip_places(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $trip = $this->createTrip($otherUser);

        $this->getJson("/api/v1/trips/{$trip->id}/places")
            ->assertForbidden();
    }

    public function test_place_must_belong_to_the_trip_in_the_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $otherTrip = $this->createTrip($user);
        $place = $this->createPlace($otherTrip);

        $this->getJson("/api/v1/trips/{$trip->id}/places/{$place->id}")
            ->assertNotFound();
    }

    public function test_place_creation_validates_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => '',
            'latitude' => 100,
            'longitude' => 200,
            'country_code' => 'POL',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'latitude',
                'longitude',
                'country_code',
            ]);
    }

    public function test_user_can_reorder_places(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $first = $this->createPlace($trip, ['name' => 'First', 'position' => 1]);
        $second = $this->createPlace($trip, ['name' => 'Second', 'position' => 2]);
        $third = $this->createPlace($trip, ['name' => 'Third', 'position' => 3]);

        $response = $this->patchJson("/api/v1/trips/{$trip->id}/places/reorder", [
            'place_ids' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $third->id)
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.1.position', 2)
            ->assertJsonPath('data.2.id', $second->id)
            ->assertJsonPath('data.2.position', 3);
    }

    public function test_reorder_rejects_places_not_belonging_to_the_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $otherTrip = $this->createTrip($user);
        $place = $this->createPlace($otherTrip);

        $this->patchJson("/api/v1/trips/{$trip->id}/places/reorder", [
            'place_ids' => [$place->id],
        ])->assertUnprocessable();
    }

    public function test_places_api_requires_authentication(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);

        $this->getJson("/api/v1/trips/{$trip->id}/places")
            ->assertUnauthorized();
    }

    public function test_place_gets_country_code_resolved_from_coordinates(): void
    {
        $this->fakeReverseCountry('AT');
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Wien',
            'latitude' => 48.2082,
            'longitude' => 16.3725,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.country_code', 'AT');

        $this->assertDatabaseHas('places', [
            'trip_id' => $trip->id,
            'name' => 'Wien',
            'country_code' => 'AT',
        ]);
    }

    public function test_submitted_country_code_is_saved_without_calling_the_provider(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Wrocław',
            'latitude' => 51.1079,
            'longitude' => 17.0385,
            'country_code' => 'PL',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.country_code', 'PL');

        Http::assertNothingSent();
    }

    public function test_submitted_country_code_is_normalized_to_upper_case(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Wrocław',
            'latitude' => 51.1079,
            'longitude' => 17.0385,
            'country_code' => 'pl',
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'PL');

        $this->assertDatabaseHas('places', [
            'trip_id' => $trip->id,
            'country_code' => 'PL',
        ]);

        Http::assertNothingSent();
    }

    public function test_submitted_country_code_not_assigned_in_iso_is_rejected(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Nowhere',
            'latitude' => 48.2082,
            'longitude' => 16.3725,
            'country_code' => 'ZZ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['country_code']);

        $this->assertDatabaseMissing('places', [
            'trip_id' => $trip->id,
            'name' => 'Nowhere',
        ]);

        Http::assertNothingSent();
    }

    public function test_submitted_kosovo_code_is_stored(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Prisztina',
            'latitude' => 42.6629,
            'longitude' => 21.1655,
            'country_code' => 'xk',
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'XK');

        Http::assertNothingSent();
    }

    public function test_place_is_created_without_country_code_when_the_provider_fails(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(status: 500),
        ]);
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Wien',
            'latitude' => 48.2082,
            'longitude' => 16.3725,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.country_code', null);

        $this->assertDatabaseHas('places', [
            'trip_id' => $trip->id,
            'name' => 'Wien',
            'country_code' => null,
        ]);
    }

    public function test_place_is_created_without_country_code_when_the_provider_payload_is_unusable(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response([
                'features' => [
                    ['properties' => ['countrycode' => ['AT']]],
                ],
            ]),
        ]);
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Wien',
            'latitude' => 48.2082,
            'longitude' => 16.3725,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.country_code', null);

        $this->assertDatabaseHas('places', [
            'trip_id' => $trip->id,
            'country_code' => null,
        ]);
    }

    public function test_place_is_created_without_country_code_when_the_provider_code_is_not_assigned_in_iso(): void
    {
        $this->fakeReverseCountry('ZZ');
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Nowhere',
            'latitude' => 48.2082,
            'longitude' => 16.3725,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.country_code', null);

        $this->assertDatabaseHas('places', [
            'trip_id' => $trip->id,
            'country_code' => null,
        ]);
    }

    public function test_place_is_created_without_country_code_when_the_lookup_budget_is_exhausted(): void
    {
        config(['services.photon.reverse_lookups_per_minute' => 1]);
        $this->fakeReverseCountry('AT');
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Wien',
            'latitude' => 48.2082,
            'longitude' => 16.3725,
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'AT');

        $this->postJson("/api/v1/trips/{$trip->id}/places", [
            'name' => 'Berlin',
            'latitude' => 52.5200,
            'longitude' => 13.4050,
        ])->assertCreated()
            ->assertJsonPath('data.country_code', null);
    }

    public function test_explicit_null_country_code_is_resolved_again_from_coordinates(): void
    {
        $this->fakeReverseCountry('DE');
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $this->patchJson("/api/v1/trips/{$trip->id}/places/{$place->id}", [
            'country_code' => null,
        ])->assertOk()
            ->assertJsonPath('data.country_code', 'DE');
    }

    public function test_country_code_is_cleared_when_coordinates_change_and_the_provider_is_unavailable(): void
    {
        Http::fake([
            'https://photon.komoot.io/reverse*' => Http::response(status: 500),
        ]);
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $this->patchJson("/api/v1/trips/{$trip->id}/places/{$place->id}", [
            'latitude' => 52.5200,
            'longitude' => 13.4050,
        ])->assertOk()
            ->assertJsonPath('data.country_code', null);

        $this->assertDatabaseHas('places', [
            'id' => $place->id,
            'country_code' => null,
        ]);
    }

    public function test_country_code_is_resolved_again_when_coordinates_change(): void
    {
        $this->fakeReverseCountry('DE');
        Cache::flush();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $response = $this->patchJson("/api/v1/trips/{$trip->id}/places/{$place->id}", [
            'latitude' => 52.5200,
            'longitude' => 13.4050,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.country_code', 'DE');

        $this->assertDatabaseHas('places', [
            'id' => $place->id,
            'country_code' => 'DE',
        ]);
    }

    public function test_country_code_is_kept_when_only_the_name_changes(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $this->patchJson("/api/v1/trips/{$trip->id}/places/{$place->id}", [
            'name' => 'Renamed place',
        ])->assertOk()
            ->assertJsonPath('data.country_code', 'PL');

        Http::assertNothingSent();
    }

    public function test_update_uses_submitted_country_code(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $this->patchJson("/api/v1/trips/{$trip->id}/places/{$place->id}", [
            'country_code' => 'at',
        ])->assertOk()
            ->assertJsonPath('data.country_code', 'AT');

        Http::assertNothingSent();
    }

    public function test_update_rejects_country_code_not_assigned_in_iso(): void
    {
        Http::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $this->patchJson("/api/v1/trips/{$trip->id}/places/{$place->id}", [
            'country_code' => 'XX',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['country_code']);

        $this->assertDatabaseHas('places', [
            'id' => $place->id,
            'country_code' => 'PL',
        ]);
    }
}
