<?php

namespace Tests\Feature;

use App\Models\Leg;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Services\DistanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to_place_id',
                'transport',
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

    public function test_creating_train_leg_automatically_calculates_distance_and_geometry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $from = $this->createPlace($trip, [
            'position' => 1,
            'latitude' => 51.1000000,
            'longitude' => 17.0000000,
        ]);

        $to = $this->createPlace($trip, [
            'position' => 2,
            'latitude' => 52.0000000,
            'longitude' => 18.0000000,
        ]);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'train',
        ])->assertCreated();

        $expectedDistance = app(DistanceService::class)
            ->calculateStraightLineDistance(
                51.1,
                17.0,
                52.0,
                18.0
            );

        $this->assertEquals(
            $expectedDistance,
            (float) $response->json('data.distance')
        );

        $response->assertJsonPath('data.geometry.type', 'LineString');

        $this->assertEquals(
            [
                [17.0, 51.1],
                [18.0, 52.0],
            ],
            $response->json('data.geometry.coordinates')
        );

        $this->assertDatabaseHas('legs', [
            'trip_id' => $trip->id,
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'train',
            'distance' => $expectedDistance,
        ]);
    }

    public function test_creating_plane_leg_uses_haversine_distance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $from = $this->createPlace($trip, [
            'position' => 1,
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);

        $to = $this->createPlace($trip, [
            'position' => 2,
            'latitude' => 52.0,
            'longitude' => 18.0,
        ]);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'plane',
        ])->assertCreated();

        $expectedDistance = app(DistanceService::class)
            ->calculateHaversineDistance(51.1, 17.0, 52.0, 18.0);

        $this->assertEquals(
            $expectedDistance,
            (float) $response->json('data.distance')
        );

        $response->assertJsonPath('data.transport', 'plane')
            ->assertJsonPath('data.geometry.type', 'LineString');
    }

    public function test_creating_car_leg_uses_routing_service_and_saves_geometry(): void
    {
        config([
            'services.routing.url' => 'https://api.openrouteservice.org/v2/directions/driving-car',
            'services.routing.api_key' => 'test-api-key',
            'services.routing.timeout' => 10,
        ]);

        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'properties' => [
                            'summary' => [
                                'distance' => 12500.5,
                                'duration' => 900,
                            ],
                        ],
                        'geometry' => [
                            'type' => 'LineString',
                            'coordinates' => [
                                [17.0, 51.1],
                                [17.5, 51.5],
                                [18.0, 52.0],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $from = $this->createPlace($trip, [
            'position' => 1,
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);

        $to = $this->createPlace($trip, [
            'position' => 2,
            'latitude' => 52.0,
            'longitude' => 18.0,
        ]);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'car',
        ])->assertCreated();

        $this->assertEquals(
            12.50,
            (float) $response->json('data.distance')
        );

        $this->assertEquals(
            [
                [17.0, 51.1],
                [17.5, 51.5],
                [18.0, 52.0],
            ],
            $response->json('data.geometry.coordinates')
        );

        $response->assertJsonPath('data.transport', 'car')
            ->assertJsonPath('data.geometry.type', 'LineString');

        $this->assertDatabaseHas('legs', [
            'trip_id' => $trip->id,
            'transport' => 'car',
            'distance' => 12.50,
        ]);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'test-api-key')
        );
    }

    public function test_updating_leg_transport_recalculates_distance_and_geometry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $from = $this->createPlace($trip, [
            'position' => 1,
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);

        $to = $this->createPlace($trip, [
            'position' => 2,
            'latitude' => 52.0,
            'longitude' => 18.0,
        ]);

        $leg = $this->createLeg($trip, $from, $to, [
            'transport' => 'train',
            'distance' => 100,
        ]);

        $response = $this->patchJson(
            "/api/v1/trips/{$trip->id}/legs/{$leg->id}",
            ['transport' => 'plane']
        )->assertOk();

        $expectedDistance = app(DistanceService::class)
            ->calculateHaversineDistance(51.1, 17.0, 52.0, 18.0);

        $this->assertEquals(
            $expectedDistance,
            (float) $response->json('data.distance')
        );

        $response->assertJsonPath('data.transport', 'plane')
            ->assertJsonPath('data.geometry.type', 'LineString');

        $this->assertDatabaseHas('legs', [
            'id' => $leg->id,
            'transport' => 'plane',
            'distance' => $expectedDistance,
        ]);
    }

    public function test_updating_leg_place_recalculates_distance_and_geometry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $from = $this->createPlace($trip, [
            'position' => 1,
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);

        $to = $this->createPlace($trip, [
            'position' => 2,
            'latitude' => 52.0,
            'longitude' => 18.0,
        ]);

        $newTo = $this->createPlace($trip, [
            'name' => 'New destination',
            'position' => 3,
            'latitude' => 53.0,
            'longitude' => 19.0,
        ]);

        $leg = $this->createLeg($trip, $from, $to, [
            'transport' => 'train',
            'distance' => 100,
        ]);

        $response = $this->patchJson(
            "/api/v1/trips/{$trip->id}/legs/{$leg->id}",
            ['to_place_id' => $newTo->id]
        )->assertOk();

        $expectedDistance = app(DistanceService::class)
            ->calculateStraightLineDistance(51.1, 17.0, 53.0, 19.0);

        $this->assertEquals(
            $expectedDistance,
            (float) $response->json('data.distance')
        );

        $this->assertEquals(
            [
                [17.0, 51.1],
                [19.0, 53.0],
            ],
            $response->json('data.geometry.coordinates')
        );

        $response->assertJsonPath('data.to_place_id', $newTo->id);

        $this->assertDatabaseHas('legs', [
            'id' => $leg->id,
            'to_place_id' => $newTo->id,
            'distance' => $expectedDistance,
        ]);
    }

    public function test_api_ignores_manually_provided_distance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $from = $this->createPlace($trip, [
            'position' => 1,
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);

        $to = $this->createPlace($trip, [
            'position' => 2,
            'latitude' => 52.0,
            'longitude' => 18.0,
        ]);

        $response = $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'train',
            'distance' => 999999,
        ])->assertCreated();

        $expectedDistance = app(DistanceService::class)
            ->calculateStraightLineDistance(51.1, 17.0, 52.0, 18.0);

        $this->assertEquals(
            $expectedDistance,
            (float) $response->json('data.distance')
        );

        $this->assertNotEquals(
            999999,
            (float) $response->json('data.distance')
        );
    }

    public function test_car_leg_is_not_saved_when_routing_provider_fails(): void
    {
        config([
            'services.routing.url' => 'https://api.openrouteservice.org/v2/directions/driving-car',
            'services.routing.api_key' => 'test-api-key',
            'services.routing.timeout' => 10,
        ]);

        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'error' => 'Internal server error',
            ], 500),
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);

        $from = $this->createPlace($trip, [
            'position' => 1,
            'latitude' => 51.1,
            'longitude' => 17.0,
        ]);

        $to = $this->createPlace($trip, [
            'position' => 2,
            'latitude' => 52.0,
            'longitude' => 18.0,
        ]);

        $this->postJson("/api/v1/trips/{$trip->id}/legs", [
            'from_place_id' => $from->id,
            'to_place_id' => $to->id,
            'transport' => 'car',
        ])->assertStatus(502);

        $this->assertDatabaseCount('legs', 0);
    }

    public function test_user_cannot_delete_leg_from_another_trip(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $trip = $this->createTrip($user);
        $otherTrip = $this->createTrip($user);

        $from = $this->createPlace($otherTrip, ['position' => 1]);
        $to = $this->createPlace($otherTrip, ['position' => 2]);

        $leg = $this->createLeg($otherTrip, $from, $to);

        $this->deleteJson("/api/v1/trips/{$trip->id}/legs/{$leg->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('legs', [
            'id' => $leg->id,
            'trip_id' => $otherTrip->id,
        ]);
    }
}
