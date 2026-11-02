<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
