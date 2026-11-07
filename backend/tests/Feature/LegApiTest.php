<?php

namespace Tests\Feature;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegApiTest extends TestCase
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

    private function createLeg(Trip $trip, Place $from, Place $to, array $attributes = []): Leg
    {
        return $trip->legs()->create(array_merge([
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'car',
            'distance' => 100,
            'position' => 1,
        ], $attributes));
    }

    public function test_user_can_list_legs_for_their_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $from = $this->createPlace($trip, ['position' => 1]);
        $to = $this->createPlace($trip, ['position' => 2]);
        $leg = $this->createLeg($trip, $from, $to);

        $this->getJson("/api/v1/trips/{$trip->id}/legs")
            ->assertOk()
            ->assertJsonPath('data.0.id', $leg->id);
    }

    public function test_user_can_create_a_leg_for_their_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $from = $this->createPlace($trip, ['position' => 1]);
        $to = $this->createPlace($trip, ['position' => 2]);

        $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'train',
            'distance' => 250,
        ])->assertCreated()
            ->assertJsonPath('data.transport', 'train')
            ->assertJsonPath('data.position', 1);

        $this->assertDatabaseHas('legs', [
            'trip_id' => $trip->id,
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'train',
            'position' => 1,
        ]);
    }

    public function test_user_can_create_a_walking_leg_for_their_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $from = $this->createPlace($trip, ['position' => 1]);
        $to = $this->createPlace($trip, ['position' => 2]);

        $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'walking',
        ])->assertCreated()
            ->assertJsonPath('data.transport', 'walking');
    }

    public function test_user_can_show_update_and_delete_a_leg(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $from = $this->createPlace($trip, ['position' => 1]);
        $to = $this->createPlace($trip, ['position' => 2]);
        $leg = $this->createLeg($trip, $from, $to);

        $this->getJson("/api/v1/trips/{$trip->id}/legs/{$leg->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $leg->id);

        $this->patchJson("/api/v1/trips/{$trip->id}/legs/{$leg->id}", [
            'transport' => 'plane',
        ])->assertOk()
            ->assertJsonPath('data.transport', 'plane');

        $this->patchJson("/api/v1/trips/{$trip->id}/legs/{$leg->id}", [
            'transport' => 'walking',
        ])->assertOk()
            ->assertJsonPath('data.transport', 'walking');

        $this->deleteJson("/api/v1/trips/{$trip->id}/legs/{$leg->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('legs', ['id' => $leg->id]);
    }

    public function test_user_cannot_access_another_users_trip_legs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $trip = $this->createTrip($otherUser);

        $this->getJson("/api/v1/trips/{$trip->id}/legs")
            ->assertForbidden();
    }

    public function test_leg_must_belong_to_the_trip_in_the_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $otherTrip = $this->createTrip($user);

        $from = $this->createPlace($otherTrip, ['position' => 1]);
        $to = $this->createPlace($otherTrip, ['position' => 2]);
        $leg = $this->createLeg($otherTrip, $from, $to);

        $this->getJson("/api/v1/trips/{$trip->id}/legs/{$leg->id}")
            ->assertNotFound();
    }

    public function test_leg_creation_validates_transport_and_places(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $place = $this->createPlace($trip);

        $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $place->id,
            'to_place_id' => $place->id,
            'transport' => 'boat',
            'distance' => -10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_place_id',
                'transport',
                'distance',
            ]);
    }

    public function test_leg_creation_rejects_places_from_another_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $otherTrip = $this->createTrip($user);

        $from = $this->createPlace($trip, ['position' => 1]);
        $to = $this->createPlace($otherTrip, ['position' => 1]);

        $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'car',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['to_place_id']);
    }

    public function test_legs_api_requires_authentication(): void
    {
        $user = User::factory()->create();
        $trip = $this->createTrip($user);

        $this->getJson("/api/v1/trips/{$trip->id}/legs")
            ->assertUnauthorized();
    }
}
